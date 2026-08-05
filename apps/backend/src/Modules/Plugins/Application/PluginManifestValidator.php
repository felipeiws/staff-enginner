<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Application;

use Atlas\Contracts\Validation\JsonSchemaValidator;
use DomainException;

/** Validates the public manifest and the platform compatibility range. */
final readonly class PluginManifestValidator
{
    public function __construct(private JsonSchemaValidator $schemas) {}

    /** @param array<string, mixed> $manifest */
    public function assertValid(array $manifest): void
    {
        $this->schemas->assertValid('plugin-manifest', $manifest);
        $permissions = $manifest['permissions'];
        $egress = $manifest['egress_allowlist'] ?? [];
        if (in_array('network.egress', $permissions, true) && $egress === []) {
            throw new DomainException('network.egress exige uma allowlist de destinos.');
        }
        if (! in_array('network.egress', $permissions, true) && $egress !== []) {
            throw new DomainException('egress_allowlist exige a permissão network.egress.');
        }
        $this->assertNoInlineSecrets($manifest);
    }

    /** @param array<string, mixed> $value */
    private function assertNoInlineSecrets(array $value, string $path = ''): void
    {
        foreach ($value as $key => $item) {
            $current = $path === '' ? (string) $key : "{$path}.{$key}";
            if (preg_match('/(?:secret|token|password|credential|authorization|api[_-]?key)/i', (string) $key) === 1
                && is_string($item) && ! str_starts_with($item, 'secret://')) {
                throw new DomainException("Segredos no manifesto devem ser referências secret:// ({$current}).");
            }
            if (is_array($item)) {
                $this->assertNoInlineSecrets($item, $current);
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    public function assertCompatible(array $manifest): void
    {
        $platformVersion = (string) config('plugins.platform_version');
        $range = (string) $manifest['platform_compatibility'];
        if (! $this->matches($platformVersion, $range)) {
            throw new PluginIncompatible("O plugin requer a plataforma {$range}; a versão atual é {$platformVersion}.");
        }
    }

    /** @param array<string, mixed> $manifest */
    public function assertPermissionsAllowed(array $manifest): void
    {
        $allowed = (array) config('plugins.allowed_permissions');
        foreach ($manifest['permissions'] as $permission) {
            if (! in_array($permission, $allowed, true)) {
                throw new PluginPermissionDenied("Permissão de plugin não autorizada: {$permission}.");
            }
        }
    }

    private function matches(string $version, string $range): bool
    {
        if (preg_match('/^(\^|~)?(\d+)\.(\d+)\.(\d+)$/', $range, $matches) !== 1) {
            throw new DomainException('platform_compatibility deve usar uma faixa SemVer ^x.y.z, ~x.y.z ou x.y.z.');
        }
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $current) !== 1) {
            throw new DomainException('A versão da plataforma configurada não é SemVer.');
        }
        [, $operator, $major, $minor, $patch] = $matches;
        [, $currentMajor, $currentMinor, $currentPatch] = $current;
        $wanted = [(int) $major, (int) $minor, (int) $patch];
        $actual = [(int) $currentMajor, (int) $currentMinor, (int) $currentPatch];
        if ($operator === '') {
            return $actual === $wanted;
        }
        if ($operator === '^') {
            return $actual[0] === $wanted[0] && $actual >= $wanted;
        }

        return $actual[0] === $wanted[0] && $actual[1] === $wanted[1] && $actual >= $wanted;
    }
}
