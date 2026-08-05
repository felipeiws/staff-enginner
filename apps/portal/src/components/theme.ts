export type PortalTheme = 'dark' | 'light';
const storageKey = 'atlas.theme';
export const preferredTheme = (): PortalTheme => {
  const stored = window.localStorage.getItem(storageKey);
  if (stored === 'dark' || stored === 'light') return stored;
  return window.matchMedia?.('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
};
export const applyTheme = (theme: PortalTheme) => {
  document.documentElement.dataset.theme = theme;
  window.localStorage.setItem(storageKey, theme);
};
