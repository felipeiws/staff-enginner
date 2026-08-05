<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Domain;

use DomainException;

/** Immutable sandbox policy, derived solely from an approved manifest. */
final readonly class PluginIsolationPolicy
{
    /** @param list<string> $egressAllowlist */
    private function __construct(
        public array $egressAllowlist,
        public int $timeoutSeconds,
        public int $maxPayloadBytes,
        public int $cpuMillicores,
        public int $memoryMebibytes,
        public bool $ephemeralFilesystem,
        public bool $mayReadSecretReferences,
    ) {}

    /** @param array<string, mixed> $manifest */
    public static function fromManifest(array $manifest): self
    {
        $permissions = $manifest['permissions'] ?? [];
        $networkEnabled = in_array('network.egress', $permissions, true);
        $allowlist = $manifest['egress_allowlist'] ?? [];
        if ($networkEnabled && $allowlist === []) {
            throw new DomainException('Plugin com egress deve declarar destinos permitidos.');
        }
        if (! $networkEnabled && $allowlist !== []) {
            throw new DomainException('Plugin sem network.egress não pode declarar destinos.');
        }
        $limits = $manifest['limits'] ?? [];

        return new self(
            $networkEnabled ? array_values($allowlist) : [],
            (int) ($limits['timeout_seconds'] ?? 0),
            (int) ($limits['max_payload_bytes'] ?? 0),
            (int) ($limits['cpu_millicores'] ?? 0),
            (int) ($limits['memory_mebibytes'] ?? 0),
            true,
            in_array('secrets.read', $permissions, true),
        );
    }
}
