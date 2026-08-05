import { describe, expect, it } from 'vitest';
import { applyTheme, preferredTheme } from './theme';
describe('tema do Portal', () => {
  it('persiste e aplica data-theme na raiz', () => {
    localStorage.clear();
    applyTheme('light');
    expect(document.documentElement.dataset.theme).toBe('light');
    expect(preferredTheme()).toBe('light');
  });
});
