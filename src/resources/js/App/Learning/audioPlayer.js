const frenchVoices = voices => voices.filter(({lang}) => /^fr(?:[-_]|$)/i.test(lang));
const voiceKey = voice => voice.voiceURI || voice.name + ':' + voice.lang;
const preferencesKey = 'practice-audio-preferences';

function readPreferences(env) {
  try {
    const {voice, pace, recordingVoice} = JSON.parse(env.localStorage?.getItem(preferencesKey) ?? '{}') ?? {};
    return {
      recordingVoice: recordingVoice === 'paul' ? 'paul' : 'lea',
      voice: typeof voice === 'string' ? voice : '',
      pace: pace === 'deliberate' ? 'deliberate' : 'normal',
    };
  } catch {
    return {voice: '', recordingVoice: 'lea', pace: 'normal'};
  }
}

export const createAudioPlayer = ({ environment = () => globalThis, resolveAsset = () => null, lookupRecording = null, timeoutMs = 10000 } = {}) => {
  let cancel = null;
  let currentPreferences;
  const preferences = () => currentPreferences ??= readPreferences(environment());
  const configure = next => {
    currentPreferences = {...preferences(), ...next};
    try { environment().localStorage?.setItem(preferencesKey, JSON.stringify(currentPreferences)); } catch {}
  };
  const watchVoices = callback => {
    const speech = environment().speechSynthesis;
    const notify = () => callback(frenchVoices(speech?.getVoices() ?? []).map(voice => ({id: voiceKey(voice), name: voice.name, lang: voice.lang})));
    speech?.addEventListener?.('voiceschanged', notify);
    notify();
    return () => speech?.removeEventListener?.('voiceschanged', notify);
  };
  const stop = () => { if (cancel) cancel(); cancel = null; };
  const play = (audio, { slow = false, onState = () => {} } = {}) => {
    stop();
    const env = environment();
    const {voice: preferredVoice, pace, recordingVoice} = preferences();
    const deliberate = pace === 'deliberate';
    let done = false;
    let media;
    let ownsSpeech = false;
    let timer;
    const finish = (state, message = '') => {
      if (done) return;
      done = true;
      env.clearTimeout(timer);
      if (media) { media.pause(); media.removeAttribute?.('src'); media.load?.(); }
      if (ownsSpeech) env.speechSynthesis.cancel();
      cancel = null;
      onState({ state, message });
    };
    cancel = () => finish('idle');
    onState({ state: 'loading', message: '' });
    const failed = () => finish('error', 'Звук недоступен.');
    const recordings = audio?.recordings;
    const recording = recordings?.[recordingVoice] ?? recordings?.lea ?? recordings?.paul;
    if (audio?.asset_id || recording) {
      const url = audio?.asset_id ? resolveAsset(audio.asset_id) : recording;
      if (!url || !env.Audio) { failed(); return; }
      media = new env.Audio(url);
      media.preload = 'none';
      media.playbackRate = slow ? (deliberate ? 0.6 : 0.7) : (deliberate ? 0.85 : 1);
      media.onplaying = () => { if (!done) { env.clearTimeout(timer); onState({ state: 'playing', message: '' }); } };
      media.onended = () => finish('idle');
      media.onerror = failed;
      media.onwaiting = media.onstalled = () => { if (!done) { env.clearTimeout(timer); timer = env.setTimeout(failed, timeoutMs); } };
      timer = env.setTimeout(failed, timeoutMs);
      try { Promise.resolve(media.play()).catch(failed); } catch { failed(); }
      return;
    }
    const speech = env.speechSynthesis;
    if (!audio?.text || !speech || !env.SpeechSynthesisUtterance) { failed(); return; }
    const inventory = speech.getVoices();
    const voices = frenchVoices(inventory);
    const language = (audio.lang || 'fr-FR').replace('_', '-');
    const voice = voices.find(item => voiceKey(item) === preferredVoice)
      ?? voices.find(item => item.lang.toLowerCase().replace('_', '-') === language.toLowerCase())
      ?? voices.find(item => item.default) ?? voices[0];
    const missingVoice = () => finish('error', 'Французский голос недоступен. Проверьте настройки синтеза речи на устройстве.');
    if (inventory.length && !voice) { missingVoice(); return; }

    // A mobile browser can require speak() to run during the tap, before voiceschanged.
    const utterance = new env.SpeechSynthesisUtterance(audio.text);
    utterance.lang = voice?.lang ?? language;
    if (voice) utterance.voice = voice;
    utterance.rate = slow ? (deliberate ? 0.55 : 0.65) : (deliberate ? 0.8 : 0.9);
    utterance.onstart = () => { if (!done) { env.clearTimeout(timer); onState({ state: 'playing', message: '' }); } };
    utterance.onend = () => { ownsSpeech = false; finish('idle'); };
    utterance.onerror = ({ error } = {}) => {
      ownsSpeech = false;
      if (error === 'language-unavailable' || error === 'voice-unavailable') missingVoice();
      else failed();
    };
    timer = env.setTimeout(failed, timeoutMs);
    ownsSpeech = true;
    try { speech.speak(utterance); } catch { failed(); }
  };
  return { play, stop, preferences, configure, watchVoices, lookupRecording };
};
