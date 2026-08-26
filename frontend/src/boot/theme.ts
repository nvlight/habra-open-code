import { defineBoot } from '#q-app';
import { Dark, LocalStorage } from 'quasar';

export type ThemePref = 'auto' | 'light' | 'dark';

const STORAGE_KEY = 'theme';
const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

export function getThemePref(): ThemePref {
  const stored = LocalStorage.getItem(STORAGE_KEY);

  return stored === 'light' || stored === 'dark' || stored === 'auto'
    ? stored
    : 'auto';
}

export function setThemePref(pref: ThemePref): void {
  LocalStorage.set(STORAGE_KEY, pref);
  applyTheme(pref);
}

function applyTheme(pref: ThemePref): void {
  const dark = pref === 'dark' || (pref === 'auto' && mediaQuery.matches);
  Dark.set(dark);
}

export default defineBoot(() => {
  applyTheme(getThemePref());

  mediaQuery.addEventListener('change', () => {
    if (getThemePref() === 'auto') {
      applyTheme('auto');
    }
  });
});
