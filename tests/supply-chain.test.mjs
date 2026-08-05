import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import test from 'node:test';

const run = (script, args, cwd = process.cwd()) =>
  spawnSync('node', [script, ...args], { cwd, encoding: 'utf8' });

test('verifica que os lockfiles obrigatórios existem', () => {
  const result = run('scripts/verify-lockfiles.mjs', []);
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, /apps\/worker\/uv\.lock/);
});

test('aceita SBOM CycloneDX com licença compatível', () => {
  const directory = mkdtempSync(join(tmpdir(), 'atlas-sbom-'));
  const sbom = join(directory, 'bom.json');
  writeFileSync(
    sbom,
    JSON.stringify({
      bomFormat: 'CycloneDX',
      components: [{ name: 'example', licenses: [{ license: { id: 'MIT' } }] }],
    }),
  );
  const result = run('scripts/check-sbom-licenses.mjs', [sbom]);
  rmSync(directory, { recursive: true, force: true });
  assert.equal(result.status, 0, result.stderr);
});

test('rejeita SBOM CycloneDX com licença incompatível', () => {
  const directory = mkdtempSync(join(tmpdir(), 'atlas-sbom-'));
  const sbom = join(directory, 'bom.json');
  writeFileSync(
    sbom,
    JSON.stringify({
      bomFormat: 'CycloneDX',
      components: [{ name: 'example', licenses: [{ license: { id: 'GPL-3.0-only' } }] }],
    }),
  );
  const result = run('scripts/check-sbom-licenses.mjs', [sbom]);
  rmSync(directory, { recursive: true, force: true });
  assert.equal(result.status, 1);
  assert.match(result.stderr, /Licenças não aprovadas/);
});

test('rejeita expressão SPDX que exige licença incompatível', () => {
  const directory = mkdtempSync(join(tmpdir(), 'atlas-sbom-'));
  const sbom = join(directory, 'bom.json');
  writeFileSync(
    sbom,
    JSON.stringify({
      bomFormat: 'CycloneDX',
      components: [{ name: 'example', licenses: [{ expression: 'MIT AND GPL-3.0-only' }] }],
    }),
  );
  const result = run('scripts/check-sbom-licenses.mjs', [sbom]);
  rmSync(directory, { recursive: true, force: true });
  assert.equal(result.status, 1);
});

test('declara bloqueios de supply chain no CI e na publicação de imagens', () => {
  const ci = readFileSync('.github/workflows/ci.yml', 'utf8');
  const release = readFileSync('.github/workflows/container-release.yml', 'utf8');
  const dependabot = readFileSync('.github/dependabot.yml', 'utf8');

  for (const expected of [
    'gitleaks/gitleaks-action',
    'pnpm install --frozen-lockfile',
    'composer --working-dir=apps/backend validate --strict',
    'uv lock --check --project apps/worker',
    'aquasecurity/trivy-action',
    'anchore/sbom-action',
    'check-sbom-licenses.mjs',
  ]) {
    assert.ok(ci.includes(expected), `CI deve conter ${expected}`);
  }
  assert.match(release, /trivy image --exit-code 1 --severity CRITICAL/);
  assert.match(release, /cosign sign --yes/);
  assert.match(dependabot, /npm-low-risk/);
  assert.match(dependabot, /python-low-risk/);
});
