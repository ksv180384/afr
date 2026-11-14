export const openCookiePreferences = () => {
  window.dispatchEvent(new Event('afr:cookie-preferences'));
};

export function clearMetrikaStorage() {
  for (const cookie of document.cookie.split(';')) {
    const name = cookie.split('=', 1)[0].trim();
    if (!name.startsWith('_ym') && name !== 'gdpr') continue;

    const expired = name + '=; Max-Age=0; Path=/; SameSite=Lax';
    document.cookie = expired;
    if (location.hostname === 'apprendrefr.ru' || location.hostname.endsWith('.apprendrefr.ru')) {
      document.cookie = expired + '; Domain=apprendrefr.ru';
    }
  }

  for (const name of ['localStorage', 'sessionStorage']) {
    try {
      const storage = window[name];
      for (const key of Object.keys(storage)) {
        if (key.startsWith('_ym') || key === 'zz') storage.removeItem(key);
      }
    } catch {
      // Storage access can be blocked by the browser.
    }
  }
}
