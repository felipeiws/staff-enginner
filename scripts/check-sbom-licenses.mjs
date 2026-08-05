import { readFileSync } from 'node:fs';

const compatible = new Set([
  '0BSD',
  'Apache-2.0',
  'BSD-2-Clause',
  'BSD-3-Clause',
  'CC0-1.0',
  'ISC',
  'MIT',
  'MPL-2.0',
  'Python-2.0',
  'Unlicense',
  'Zlib',
]);

const files = process.argv.slice(2);
if (files.length === 0) {
  throw new Error('Informe ao menos um SBOM CycloneDX para verificação de licença.');
}

const invalid = [];
const expressionIsCompatible = (expression) =>
  expression
    .replace(/[()]/g, '')
    .split(/\s+OR\s+/)
    .some((alternative) =>
      alternative.split(/\s+AND\s+/).every((license) => compatible.has(license.trim())),
    );

for (const file of files) {
  const bom = JSON.parse(readFileSync(file, 'utf8'));
  if (bom.bomFormat !== 'CycloneDX') {
    invalid.push(`${file}: não é um SBOM CycloneDX`);
    continue;
  }

  for (const component of bom.components ?? []) {
    const licenses = component.licenses ?? [];
    const values = licenses.flatMap((entry) => {
      if (entry.license?.id) return [entry.license.id];
      if (entry.license?.name) return [entry.license.name];
      if (entry.expression) return [entry.expression];
      return [];
    });
    const hasCompatibleLicense = licenses.some(
      (entry) =>
        (entry.license?.id && compatible.has(entry.license.id)) ||
        (entry.license?.name && compatible.has(entry.license.name)) ||
        (entry.expression && expressionIsCompatible(entry.expression)),
    );
    if (values.length === 0 || !hasCompatibleLicense) {
      invalid.push(
        `${file}: ${component.name ?? component.purl ?? 'componente sem nome'} (${values.join(', ') || 'sem licença'})`,
      );
    }
  }
}

if (invalid.length > 0) {
  throw new Error(`Licenças não aprovadas para Apache-2.0:\n${invalid.join('\n')}`);
}

console.log(`Licenças compatíveis com Apache-2.0 verificadas em ${files.length} SBOM(s).`);
