<?php

declare(strict_types=1);

namespace Atlas\Contracts\Tests;

use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Contracts\Enum\KnowledgeLifecycleState;
use PHPUnit\Framework\TestCase;

final class EnumSchemaParityTest extends TestCase
{
    public function testGraphPredicateEnumMatchesTheSourceJsonSchema(): void
    {
        $schema = $this->schema('assertion.json');
        self::assertSame($this->values(GraphPredicate::cases()), $schema['$defs']['predicate']['enum']);
    }

    public function testLifecycleEnumMatchesEverySchemaThatDefinesIt(): void
    {
        foreach (['assertion.json', 'knowledge-entity.json', 'knowledge-revision.json'] as $file) {
            $schema = $this->schema($file);
            self::assertSame($this->values(KnowledgeLifecycleState::cases()), $schema['$defs']['lifecycleState']['enum'], $file);
        }
    }

    public function testPackagedSchemasMatchTheCanonicalContracts(): void
    {
        $canonical = dirname(__DIR__, 2) . '/contracts/schemas/v1';
        $packaged = dirname(__DIR__) . '/resources/schemas/v1';
        foreach (glob($canonical . '/*.json') ?: [] as $file) {
            $name = basename($file);
            self::assertFileExists($packaged . '/' . $name);
            self::assertJsonStringEqualsJsonFile($file, (string) file_get_contents($packaged . '/' . $name), $name);
        }
    }

    /** @return array<string, mixed> */
    private function schema(string $file): array
    {
        $path = dirname(__DIR__, 2) . '/contracts/schemas/v1/' . $file;
        $contents = file_get_contents($path);
        self::assertNotFalse($contents, "Schema não encontrado: $path");
        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param list<\BackedEnum> $cases @return list<string> */
    private function values(array $cases): array
    {
        return array_map(static fn (\BackedEnum $case): string => $case->value, $cases);
    }
}
