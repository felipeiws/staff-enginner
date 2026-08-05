<?php

declare(strict_types=1);

namespace Atlas\Modules\Plugins\Application;

use Atlas\Modules\Plugins\Domain\PluginLifecycleState;
use Atlas\Modules\Plugins\Domain\PluginLifecycleTransitionDenied;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use RuntimeException;

/** Registry and durable lifecycle machine; it deliberately has no graph dependency. */
final readonly class PluginRegistry
{
    public function __construct(private DatabaseManager $database, private PluginManifestValidator $validator) {}

    /** @param array<string, mixed> $manifest */
    public function discover(array $manifest, string $correlationId, string $idempotencyKey): PluginRegistration
    {
        $this->validator->assertValid($manifest);
        $existing = $this->database->table('plugin_registrations')->where('plugin_id', $manifest['id'])->first();
        if ($existing !== null) {
            $registration = $this->hydrate($existing);
            if ($registration->manifest !== $manifest) {
                throw new RuntimeException('O id do plugin é imutável e já está associado a outro manifesto.');
            }

            return $registration;
        }
        $id = (string) Str::uuid();
        $this->database->transaction(function () use ($id, $manifest, $correlationId, $idempotencyKey): void {
            $now = now();
            $this->database->table('plugin_registrations')->insert(['id' => $id, 'plugin_id' => $manifest['id'], 'version' => $manifest['version'], 'lifecycle_state' => PluginLifecycleState::Discovered->value, 'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR), 'configuration' => null, 'created_at' => $now, 'updated_at' => $now]);
            $this->event($id, null, PluginLifecycleState::Discovered, $correlationId, $idempotencyKey, ['manifest_version' => $manifest['version']]);
        });

        return $this->byId($manifest['id']);
    }

    public function validate(string $pluginId, string $correlationId, string $idempotencyKey): PluginRegistration
    {
        $registration = $this->byId($pluginId);
        $this->validator->assertCompatible($registration->manifest);

        return $this->transition($registration, PluginLifecycleState::Validated, $correlationId, $idempotencyKey);
    }

    public function install(string $pluginId, string $correlationId, string $idempotencyKey): PluginRegistration
    {
        $registration = $this->byId($pluginId);
        $this->validator->assertCompatible($registration->manifest);
        $this->validator->assertPermissionsAllowed($registration->manifest);

        return $this->transition($registration, PluginLifecycleState::Installed, $correlationId, $idempotencyKey);
    }

    /** @param array<string, mixed> $configuration */
    public function configure(string $pluginId, array $configuration, string $correlationId, string $idempotencyKey): PluginRegistration
    {
        $registration = $this->byId($pluginId);
        if ($registration->state !== PluginLifecycleState::Installed) {
            throw new PluginLifecycleTransitionDenied('Configuração requer plugin instalado.');
        }

        return $this->transition($registration, PluginLifecycleState::Configured, $correlationId, $idempotencyKey, $configuration);
    }

    public function enable(string $pluginId, string $correlationId, string $idempotencyKey): PluginRegistration
    {
        return $this->transition($this->byId($pluginId), PluginLifecycleState::Enabled, $correlationId, $idempotencyKey);
    }

    public function disable(string $pluginId, string $correlationId, string $idempotencyKey): PluginRegistration
    {
        return $this->transition($this->byId($pluginId), PluginLifecycleState::Disabled, $correlationId, $idempotencyKey);
    }

    public function revoke(string $pluginId, string $correlationId, string $idempotencyKey): PluginRegistration
    {
        return $this->transition($this->byId($pluginId), PluginLifecycleState::Revoked, $correlationId, $idempotencyKey);
    }

    /** @param array<string, mixed> $manifest */
    public function upgrade(string $pluginId, array $manifest, string $correlationId, string $idempotencyKey): PluginRegistration
    {
        $registration = $this->byId($pluginId);
        if (($manifest['id'] ?? null) !== $pluginId) {
            throw new RuntimeException('O id do manifesto não pode mudar durante upgrade.');
        }
        $this->validator->assertValid($manifest);
        $this->validator->assertCompatible($manifest);
        $this->validator->assertPermissionsAllowed($manifest);
        $this->transition($registration, PluginLifecycleState::Upgrading, $correlationId, "{$idempotencyKey}:start");
        $this->database->table('plugin_registrations')->where('id', $registration->id)->update(['manifest' => json_encode($manifest, JSON_THROW_ON_ERROR), 'version' => $manifest['version'], 'updated_at' => now()]);

        return $this->transition($this->byId($pluginId), PluginLifecycleState::Enabled, $correlationId, $idempotencyKey);
    }

    public function byId(string $pluginId): PluginRegistration
    {
        $record = $this->database->table('plugin_registrations')->where('plugin_id', $pluginId)->first();
        if ($record === null) {
            throw new RuntimeException("Plugin não encontrado: {$pluginId}.");
        }

        return $this->hydrate($record);
    }

    /** @param array<string, mixed>|null $configuration */
    private function transition(PluginRegistration $registration, PluginLifecycleState $to, string $correlationId, string $idempotencyKey, ?array $configuration = null): PluginRegistration
    {
        if ($registration->state === $to) {
            return $registration;
        }
        if (! $this->canTransition($registration->state, $to)) {
            throw new PluginLifecycleTransitionDenied("Transição inválida: {$registration->state->value} → {$to->value}.");
        }
        $this->database->transaction(function () use ($registration, $to, $correlationId, $idempotencyKey, $configuration): void {
            $update = ['lifecycle_state' => $to->value, 'updated_at' => now()];
            if ($configuration !== null) {
                $update['configuration'] = json_encode($configuration, JSON_THROW_ON_ERROR);
            }
            $this->database->table('plugin_registrations')->where('id', $registration->id)->update($update);
            $this->event($registration->id, $registration->state, $to, $correlationId, $idempotencyKey, []);
        });

        return $this->byId($registration->pluginId);
    }

    private function canTransition(PluginLifecycleState $from, PluginLifecycleState $to): bool
    {
        return match ($from) {
            PluginLifecycleState::Discovered => $to === PluginLifecycleState::Validated,
            PluginLifecycleState::Validated => $to === PluginLifecycleState::Installed,
            PluginLifecycleState::Installed => $to === PluginLifecycleState::Configured,
            PluginLifecycleState::Configured, PluginLifecycleState::Disabled => $to === PluginLifecycleState::Enabled,
            PluginLifecycleState::Enabled => in_array($to, [PluginLifecycleState::Disabled, PluginLifecycleState::Upgrading, PluginLifecycleState::Revoked], true),
            PluginLifecycleState::Upgrading => $to === PluginLifecycleState::Enabled,
            PluginLifecycleState::Revoked => false,
        };
    }

    /** @param array<string, mixed> $details */
    private function event(string $id, ?PluginLifecycleState $from, PluginLifecycleState $to, string $correlationId, string $idempotencyKey, array $details): void
    {
        $this->database->table('plugin_lifecycle_events')->insert(['id' => (string) Str::uuid(), 'plugin_registration_id' => $id, 'from_state' => $from?->value, 'to_state' => $to->value, 'correlation_id' => $correlationId, 'idempotency_key' => $idempotencyKey, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function hydrate(object $record): PluginRegistration
    {
        $manifest = json_decode((string) $this->recordValue($record, 'manifest'), true, 512, JSON_THROW_ON_ERROR);
        $configurationValue = $this->recordValue($record, 'configuration');
        $configuration = $configurationValue === null ? null : json_decode((string) $configurationValue, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($manifest) || ($configuration !== null && ! is_array($configuration))) {
            throw new RuntimeException('Registro persistido de plugin inválido.');
        }

        return new PluginRegistration((string) $this->recordValue($record, 'id'), (string) $this->recordValue($record, 'plugin_id'), (string) $this->recordValue($record, 'version'), PluginLifecycleState::from((string) $this->recordValue($record, 'lifecycle_state')), $manifest, $configuration);
    }

    private function recordValue(object $record, string $property): mixed
    {
        if (! property_exists($record, $property)) {
            throw new RuntimeException("Registro de plugin sem {$property}.");
        }

        return $record->{$property};
    }
}
