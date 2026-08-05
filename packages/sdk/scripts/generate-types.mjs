import { mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { compileFromFile } from 'json-schema-to-typescript';

const sdkDirectory = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const schemasDirectory = path.resolve(sdkDirectory, '../contracts/schemas/v1');
const outputDirectory = path.join(sdkDirectory, 'src/generated');

await rm(outputDirectory, { recursive: true, force: true });
await mkdir(outputDirectory, { recursive: true });

const schemaFiles = (await readdir(schemasDirectory))
  .filter((file) => file.endsWith('.json'))
  .sort();
const exports = [];
for (const schemaFile of schemaFiles) {
  const basename = path.basename(schemaFile, '.json');
  const source = await compileFromFile(path.join(schemasDirectory, schemaFile), {
    bannerComment: `/* Gerado de packages/contracts/schemas/v1/${schemaFile}. Não edite manualmente. */`,
    strictIndexSignatures: true,
    style: {
      printWidth: 100,
      singleQuote: true,
      tabWidth: 2,
      trailingComma: 'all',
    },
  });
  await writeFile(path.join(outputDirectory, `${basename}.ts`), source);
  const schema = JSON.parse(await readFile(path.join(schemasDirectory, schemaFile), 'utf8'));
  const rootType = schema.title
    .split(/[^A-Za-z0-9]+/)
    .filter(Boolean)
    .map((word) => word[0].toUpperCase() + word.slice(1))
    .join('');
  exports.push(`export type { ${rootType} } from './${basename}.js';`);
}
await writeFile(
  path.join(outputDirectory, 'index.ts'),
  `/* Gerado. Não edite manualmente. */\n${exports.join('\n')}\n`,
);
