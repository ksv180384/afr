export const createAudioPlayer = ({ environment = () => globalThis, resolveAsset = () => null, timeoutMs = 10000 } = {}) => {
  let cancel = null;
  const stop = () => { if (cancel) cancel(); cancel = null; };
  const play = (audio, { slow = false, onState = () => {} } = {}) => {
    stop();
    const env = environment();
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
    const failed = () => finish('error', 'Звук недоступен. Можно открыть текст с произношением или пропустить аудирование без языковой ошибки.');
    if (audio?.asset_id) {
      const url = resolveAsset(audio.asset_id);
      if (!url || !env.Audio) { failed(); return; }
      media = new env.Audio(url);
      media.preload = 'none';
      media.playbackRate = slow ? 0.7 : 1;
      media.onplaying = () => { if (!done) { env.clearTimeout(timer); onState({ state: 'playing', message: '' }); } };
      media.onended = () => finish('idle');
      media.onerror = failed;
      media.onwaiting = media.onstalled = () => { if (!done) { env.clearTimeout(timer); timer = env.setTimeout(failed, timeoutMs); } };
      timer = env.setTimeout(failed, timeoutMs);
      Promise.resolve(media.play()).catch(failed);
      return;
    }
    if (!env.speechSynthesis || !env.SpeechSynthesisUtterance) { failed(); return; }
    const speak = () => {
      if (done) return;
      const voice = env.speechSynthesis.getVoices().find(({ lang }) => /^fr(?:-|_)/i.test(lang) || lang.toLowerCase() === 'fr');
      if (!voice) return;
      if (voiceListener) env.speechSynthesis.removeEventListener('voiceschanged', voiceListener);
      const utterance = new env.SpeechSynthesisUtterance(audio.text);
      utterance.voice = voice;
      utterance.lang = voice.lang;
      utterance.rate = slow ? 0.65 : 0.9;
      utterance.onstart = () => { if (!done) { env.clearTimeout(timer); onState({ state: 'playing', message: '' }); } };
      utterance.onend = () => finish('idle');
      utterance.onerror = failed;
      ownsSpeech = true;
      env.speechSynthesis.speak(utterance);
    };
    timer = env.setTimeout(failed, timeoutMs);
    voiceListener = speak;
    env.speechSynthesis.addEventListener('voiceschanged', voiceListener);
    speak();
  };
  return { play, stop };
};
