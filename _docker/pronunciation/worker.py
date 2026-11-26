"""Private Compose service. One GPU consumer, a durable queue and atomic MP3 publication."""
import hashlib
import json
import os
from pathlib import Path
import secrets
import sqlite3
import tempfile
import threading
import time
from contextlib import closing
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.error import URLError
from urllib.parse import parse_qs, urlsplit

import lameenc
import miniaudio

import comfy

OUTPUT = Path("/recordings")
DATABASE = "/queue/jobs.sqlite3"
wake = threading.Event()
connection_error = ""


def connect():
    db = sqlite3.connect(DATABASE, timeout=15)
    db.row_factory = sqlite3.Row
    return db


def destination(key, voice):
    return OUTPUT / key[:2] / f"{key}-{voice}.mp3"


def enqueue(items, replace):
    added = 0
    with closing(connect()) as db, db:
        db.execute("BEGIN IMMEDIATE")
        for item in items:
            text = item["text"]
            key = hashlib.sha256(text.encode()).hexdigest()
            for voice in item["voices"]:
                if voice not in comfy.REFERENCES:
                    raise ValueError("Неизвестный голос.")
                target = destination(key, voice)
                if not replace and target.is_file() and target.stat().st_size:
                    continue
                added += db.execute("""
                    INSERT INTO jobs (key, voice, text, seed) VALUES (?, ?, ?, ?)
                    ON CONFLICT(key, voice) DO UPDATE SET prompt=NULL, error=NULL, seed=excluded.seed
                    WHERE jobs.error IS NOT NULL
                """, (key, voice, text, secrets.randbits(32))).rowcount
    wake.set()
    return added


def encode(audio, path):
    pcm = miniaudio.decode(audio, output_format=miniaudio.SampleFormat.SIGNED16, nchannels=1, sample_rate=24000)
    if not pcm.num_frames or not any(pcm.samples):
        raise ValueError("ComfyUI вернул пустую или беззвучную запись.")
    encoder = lameenc.Encoder()
    encoder.set_channels(1)
    encoder.set_in_sample_rate(24000)
    encoder.set_bit_rate(48)
    encoder.set_quality(2)
    encoded = encoder.encode(pcm.samples.tobytes()) + encoder.flush()
    path.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile(dir=path.parent, suffix=".part", delete=False) as tmp:
        staged = Path(tmp.name)
    try:
        staged.write_bytes(encoded)
        staged.chmod(0o644)
        os.replace(staged, path)
    finally:
        staged.unlink(missing_ok=True)


def render(db, job):
    keys = (job["key"], job["voice"])
    prompt = job["prompt"]
    if not prompt:
        prompt = comfy.submit(job["text"], job["voice"], job["seed"])
        with db:
            db.execute("UPDATE jobs SET prompt=? WHERE key=? AND voice=?", (prompt, *keys))
    while True:
        audio = comfy.result(prompt)
        if audio is not None:
            break
        # A ComfyUI restart can discard its history and queue.
        queue = json.loads(comfy.request("/queue"))
        if not any(row[1] == prompt for name in ("queue_running", "queue_pending") for row in queue[name]):
            audio = comfy.result(prompt)
            if audio is None:
                raise ValueError("Задание исчезло из очереди ComfyUI. Повторите генерацию.")
            break
        time.sleep(2)
    encode(audio, destination(*keys))
    with db:
        db.execute("DELETE FROM jobs WHERE key=? AND voice=?", keys)


def consume():
    global connection_error
    with closing(connect()) as db:
        while True:
            job = db.execute("SELECT * FROM jobs WHERE error IS NULL ORDER BY rowid LIMIT 1").fetchone()
            if job is None:
                wake.wait(3)
                wake.clear()
                continue
            try:
                render(db, job)
                connection_error = ""
            except (URLError, ConnectionError, TimeoutError) as exc:
                connection_error = f"Нет связи с ComfyUI. Очередь ждёт подключения. {exc}"
                time.sleep(8)
            except Exception as exc:
                connection_error = ""
                with db:
                    db.execute("UPDATE jobs SET error=? WHERE key=? AND voice=?",
                               (str(exc)[:1000], job["key"], job["voice"]))


class Api(BaseHTTPRequestHandler):
    def reply(self, code, data):
        body = json.dumps(data, ensure_ascii=False).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        url = urlsplit(self.path)
        if url.path != "/jobs":
            return self.reply(404, {})
        keys = parse_qs(url.query).get("keys", [""])[0].split(",")[:50]
        with closing(connect()) as db:
            marks = ",".join("?" for _ in keys)
            jobs = db.execute(f"SELECT key, voice, prompt IS NOT NULL AS running, error FROM jobs WHERE key IN ({marks})", keys)
            totals = db.execute("SELECT COUNT(*) AS total, COUNT(error) AS failed FROM jobs").fetchone()
            self.reply(200, {"jobs": [dict(row) for row in jobs], "pending": totals["total"] - totals["failed"],
                             "failed": totals["failed"], "message": connection_error})

    def do_POST(self):
        if self.path != "/jobs":
            return self.reply(404, {})
        try:
            size = int(self.headers.get("Content-Length", 0))
            if not 0 < size <= 16 * 1024 * 1024:
                return self.reply(413, {})
            data = json.loads(self.rfile.read(size))
            self.reply(202, {"added": enqueue(data["items"], data.get("replace") is True)})
        except (ValueError, KeyError, TypeError) as exc:
            self.reply(422, {"message": str(exc)})

    def log_message(self, *args):
        pass


if __name__ == "__main__":
    with closing(connect()) as db, db:
        db.execute("""CREATE TABLE IF NOT EXISTS jobs (
            key TEXT NOT NULL, voice TEXT NOT NULL, text TEXT NOT NULL, seed INTEGER NOT NULL,
            prompt TEXT, error TEXT, UNIQUE(key, voice))""")
    threading.Thread(target=consume, daemon=True).start()
    ThreadingHTTPServer(("", 8080), Api).serve_forever()
