<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

use Atlas\Contracts\Dto\Actor;
use Atlas\Contracts\Dto\AuditRecord;
use Atlas\Contracts\Port\AuditTrail;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/** Loads a versioned policy document; existing policy versions are never overwritten. */
final readonly class YamlPolicyLoader
{
    public function __construct(private DatabaseManager $database, private PolicySchemaValidator $validator, private AuditTrail $auditTrail) {}

    /** @return list<string> inserted policy IDs */
    public function load(string $yaml, string $actorId): array
    {
        $document = Yaml::parse($yaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        if (! is_array($document)) {
            throw new \InvalidArgumentException('O YAML de políticas deve conter um documento-mapa.');
        }
        $this->validator->validate($document);

        return $this->database->transaction(function () use ($document, $yaml, $actorId): array {
            $ids = [];
            foreach ($document['policies'] as $policy) {
                $exists = $this->database->table('policies')->where('policy_key', $policy['policy_key'])->where('version', $policy['version'])->exists();
                if ($exists) {
                    throw new \DomainException("A versão {$policy['version']} da política {$policy['policy_key']} já foi carregada.");
                }
                $id = (string) Str::uuid();
                $this->database->table('policies')->insert([
                    'id' => $id, 'policy_key' => $policy['policy_key'], 'version' => $policy['version'], 'schema_version' => '1.0.0',
                    'definition_checksum' => hash('sha256', $yaml), 'origin_reference' => 'inline:governance-policy-yaml', 'origin_revision' => (string) $policy['version'], 'derivation_method' => 'human_declared', 'confidence' => 1,
                    'name' => $policy['name'].' @'.$policy['version'], 'priority' => $policy['priority'],
                    'active' => $policy['active'] ?? true, 'rules' => json_encode($policy, JSON_THROW_ON_ERROR), 'effective_from' => $policy['effective_from'] ?? null,
                    'effective_to' => $policy['effective_to'] ?? null, 'created_by_id' => $actorId, 'created_by_type' => 'human', 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->auditTrail->record(new AuditRecord(new Actor($actorId, 'human'), 'governance.policy.created', 'policy', $id, null, null, null, 'created', 'Nova versão de política carregada.', null, [], null, ['policy_key' => $policy['policy_key'], 'version' => $policy['version'], 'checksum' => hash('sha256', $yaml)], null, 'human_declared', 1.0));
                $ids[] = $id;
            }

            return $ids;
        });
    }
}
