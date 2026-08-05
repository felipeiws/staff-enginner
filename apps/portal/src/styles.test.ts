import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const sourceDirectory = resolve(import.meta.dirname);
const styles = readFileSync(resolve(sourceDirectory, 'styles.css'), 'utf8');
const fontsDirectory = resolve(sourceDirectory, 'assets/fonts');

describe('tokens globais do Portal', () => {
  it('reproduz os tokens dos temas escuro e claro do protótipo', () => {
    const darkTokens = [
      '--bg: #08090b;',
      '--surface: #0f1114;',
      '--surface2: #14171b;',
      '--sel: #1b1f24;',
      '--line: rgba(255, 255, 255, 0.09);',
      '--line2: rgba(255, 255, 255, 0.05);',
      '--tx: #e8eaed;',
      '--tx2: #98a0aa;',
      '--tx3: #9aa3ad;',
      '--acc: #4ee1a0;',
      '--accSoft: rgba(78, 225, 160, 0.11);',
      '--warn: #f5b547;',
      '--danger: #ff6b6b;',
      '--info: #6aa8ff;',
      '--violet: #a78bfa;',
      '--pink: #f472b6;',
      '--sky: #38bdf8;',
      '--shadow: 0 12px 40px rgba(0, 0, 0, 0.5);',
    ];
    expect(darkTokens.every((token) => styles.includes(token))).toBe(true);

    expect(styles).toContain(":root[data-theme='light']");
    const lightTokens = [
      '--bg: #fafaf9;',
      '--surface: #ffffff;',
      '--surface2: #f4f5f6;',
      '--sel: #eceef0;',
      '--line: rgba(0, 0, 0, 0.11);',
      '--line2: rgba(0, 0, 0, 0.06);',
      '--tx: #14171a;',
      '--tx2: #5b646e;',
      '--tx3: #59636e;',
      '--acc: #0fa36b;',
      '--accSoft: rgba(15, 163, 107, 0.09);',
      '--warn: #b26a00;',
      '--danger: #d6323f;',
      '--info: #2563eb;',
      '--violet: #7c4de0;',
      '--pink: #db2777;',
      '--sky: #0284c7;',
      '--shadow: 0 12px 32px rgba(0, 0, 0, 0.12);',
    ];
    expect(lightTokens.every((token) => styles.includes(token))).toBe(true);
  });

  it('mantém fontes locais e os comportamentos globais do protótipo', () => {
    expect(styles).toContain("font-family: 'Space Grotesk';");
    expect(styles).toContain("font-family: 'IBM Plex Sans';");
    expect(styles).toContain("font-family: 'IBM Plex Mono';");
    expect(styles).toContain('font-weight: 700;');
    expect(styles).toContain('font-weight: 600;');
    expect(styles).toContain('font-weight: 500;');
    expect(styles).toContain('@keyframes fadeUp');
    expect(styles).toContain('@keyframes blink');
    expect(styles).toContain('@keyframes dash');
    expect(styles).toContain('::-webkit-scrollbar');
    expect(styles).toContain('text-wrap: pretty;');
    expect(existsSync(resolve(fontsDirectory, 'space-grotesk-latin-700-normal.woff2'))).toBe(true);
    expect(existsSync(resolve(fontsDirectory, 'ibm-plex-sans-latin-600-normal.woff2'))).toBe(true);
    expect(existsSync(resolve(fontsDirectory, 'ibm-plex-mono-latin-500-normal.woff2'))).toBe(true);
  });
});
