const frenchVoices = speech => (speech?.getVoices() ?? []).filter(({lang}) => /^fr(?:[-_]|$)/i.test(lang));
const voiceKey = voice => voice.voiceURI || voice.name + ':' + voice.lang;
const preferencesKey = 'practice-audio-preferences';

function readPreferences(env) {
  try {
    const {voice, pace} = JSON.parse(env.localStorage?.getItem(preferencesKey) ?? '{}') ?? {};
    return {voice: typeof voice === 'string' ? voice : '', pace: pace === 'deliberate' ? 'deliberate' : 'normal'};
  } catch {
    return {voice: '', pace: 'normal'};
  }
}

export const createAudioPlayer = ({ environment = () => globalThis, resolveAsset = () => null, timeoutMs = 10000 } = {}) => {
  let cancel = null;
  let currentPreferences;
  const preferences = () => currentPreferences ??= readPreferences(environment());
  const configure = next => {
    currentPreferences = {...preferences(), ...next};
    try { environment().localStorage?.setItem(preferencesKey, JSON.stringify(currentPreferences)); } catch {}
  };
  const watchVoices = callback => {
    const speech = environment().speechSynthesis;
    const notify = () => callback(frenchVoices(speech).map(voice => ({id: voiceKey(voice), name: voice.name, lang: voice.lang})));
    speech?.addEventListener?.('voiceschanged', notify);
    notify();
    return () => speech?.removeEventListener?.('voiceschanged', notify);
  };
  const stop = () => { if (cancel) cancel(); cancel = null; };
  const play = (audio, { slow = false, onState = () => {} } = {}) => {
    stop();
    const env = environment();
    const {voice: preferredVoice, pace} = preferences();
    const deliberate = pace === 'deliberate';
    let done = false;
    let media;
    let ownsSpeech = false;
    let voiceListener;
    let timer;
    const finish = (state, message = '') => {
      if (done) return;
      done = true;
      env.clearTimeout(timer);
      if (voiceListener) env.speechSynthesis?.removeEventListener('voiceschanged', voiceListener);
      if (media) { media.pause(); media.removeAttribute?.('src'); media.load?.(); }
      if (ownsSpeech) env.speechSynthesis.cancel();
      cancel = null;
      onState({ state, message });
    };
    cancel = () => finish('idle');
    onState({ state: 'loading', message: '' });
    const failed = () => finish('error', 'Звук недоступен.');
    if (audio?.asset_id) {
      const url = resolveAsset(audio.asset_id);
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
    if (!audio?.text || !env.speechSynthesis || !env.SpeechSynthesisUtterance) { failed(); return; }
    const speak = () => {
      if (done) return;
      const voices = frenchVoices(env.speechSynthesis);
      const language = audio.lang?.toLowerCase().replace('_', '-');
      const voice = voices.find(item => voiceKey(item) === preferredVoice)
        ?? voices.find(item => item.lang.toLowerCase().replace('_', '-') === language)
        ?? voices.find(item => item.default) ?? voices[0];
      if (!voice) return;
      if (voiceListener) env.speechSynthesis.removeEventListener('voiceschanged', voiceListener);
      const utterance = new env.SpeechSynthesisUtterance(audio.text);
      utterance.voice = voice;
      utterance.lang = voice.lang;
      utterance.rate = slow ? (deliberate ? 0.55 : 0.65) : (deliberate ? 0.8 : 0.9);
      utterance.onstart = () => { if (!done) { env.clearTimeout(timer); onState({ state: 'playing', message: '' }); } };
      utterance.onend = () => finish('idle');
      utterance.onerror = failed;
      ownsSpeech = true;
      try { env.speechSynthesis.speak(utterance); } catch { failed(); }
    };
    timer = env.setTimeout(failed, timeoutMs);
    voiceListener = speak;
    env.speechSynthesis.addEventListener('voiceschanged', voiceListener);
    speak();
  };
  return { play, stop, preferences, configure, watchVoices };
};
