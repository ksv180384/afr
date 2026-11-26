import axios from 'axios';

// Controls mounted together (e.g. a vocabulary page) share one filesystem lookup.
const pending = new Map();
const cache = new Map();
let scheduled = false;

async function flush() {
  scheduled = false;
  const entries = [...pending];
  pending.clear();
  for (let start = 0; start < entries.length; start += 50) {
    const batch = entries.slice(start, start + 50);
    let files;
    try {
      const response = await axios.post('/daily/api/content/recordings', {texts: batch.map(([text]) => text)}, {timeout: 4000});
      files = response.data.files;
    } catch { /* Speech remains available when the site is offline. */ }
    batch.forEach(([text, resolve], index) => {
      if (!files) cache.delete(text);
      resolve(files?.[index] ?? {});
    });
  }
}

export function lookupRecording(text) {
  const cached = cache.get(text);
  if (cached && cached.expires > Date.now()) return cached.promise;
  const promise = new Promise(resolve => pending.set(text, resolve));
  cache.set(text, {promise, expires: Date.now() + 60000});
  if (!scheduled) {
    scheduled = true;
    queueMicrotask(flush);
  }
  return promise;
}
