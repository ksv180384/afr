"""Render a take using a reference selected in the V3 voice workflow."""
import json
import os
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import Request, urlopen

BASE = os.environ.get("COMFYUI_URL", "http://127.0.0.1:8188").rstrip("/")
REFERENCES = {
    "lea": ("Lea_reference.flac", "Bonjour, je m’appelle Léa. Aujourd’hui, je suis contente de te rencontrer. J’aime apprendre les langues et parler avec mes amis. Et toi, comment vas-tu ?"),
    "paul": ("Paul_reference.flac", "Bonjour, je m’appelle Paul. Je suis français et j’habite à Lyon. J’aime découvrir de nouvelles choses et apprendre les langues étrangères. Je suis ravi de faire ta connaissance."),
}


def request(path, data=None):
    body = json.dumps(data).encode() if data is not None else None
    req = Request(BASE + path, body, {"Content-Type": "application/json"})
    try:
        with urlopen(req, timeout=30) as result:
            return result.read()
    except HTTPError as exc:
        raise ValueError(f"ComfyUI HTTP {exc.code}: {exc.read(1200).decode(errors='replace')}") from exc


def submit(text, voice, seed):
    filename, transcript = REFERENCES[voice]
    graph = {
        "1": {"class_type": "LoadAudio", "inputs": {"audio": filename}},
        "2": {"class_type": "FB_Qwen3TTSVoiceClone", "inputs": {
            "ref_audio": ["1", 0], "ref_text": transcript, "target_text": text,
            "model_choice": "1.7B", "device": "cuda", "precision": "bf16", "attention": "sdpa",
            "language": "French", "x_vector_only": False, "seed": seed, "max_new_tokens": 2048,
            "top_p": 0.85, "top_k": 20, "temperature": 0.6, "repetition_penalty": 1.05,
        }},
        "3": {"class_type": "PreviewAudio", "inputs": {"audio": ["2", 0]}},
    }
    return json.loads(request("/prompt", {"prompt": graph}))["prompt_id"]


def result(prompt):
    entry = json.loads(request("/history/" + prompt)).get(prompt)
    if not entry:
        return None
    audio = entry.get("outputs", {}).get("3", {}).get("audio", [])
    if not audio:
        events = entry.get("status", {}).get("messages", [])
        detail = next((info.get("exception_message") for event, info in events if event == "execution_error"), None)
        raise ValueError(detail or "ComfyUI завершил задание без аудио.")
    file = audio[0]
    return request("/view?" + urlencode({key: file[key] for key in ("filename", "subfolder", "type")}))
