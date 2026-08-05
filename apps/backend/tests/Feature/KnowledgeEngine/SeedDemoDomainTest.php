<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('seeds the Nordika prototype domain through versioned observations and is idempotent', function (): void {
    $this->artisan('atlas:seed-demo')->assertSuccessful();
    $this->artisan('atlas:seed-demo')->assertSuccessful();

    expect(DB::table('sources')->where('type', 'atlas_demo_catalog')->where('active', true)->exists())->toBeTrue()
        ->and(DB::table('entities')->where('entity_type', 'Organização')->where('external_natural_key', 'atlas_demo_catalog://nordika')->exists())->toBeTrue()
        ->and(DB::table('entities')->where('entity_type', 'Aplicação')->count())->toBe(4)
        ->and(DB::table('entities')->where('entity_type', 'Módulo')->count())->toBe(6)
        ->and(DB::table('entities')->where('entity_type', 'Responsável')->count())->toBe(8)
        ->and(DB::table('entities')->where('external_natural_key', 'atlas_demo_catalog://rn-142')->join('entity_revisions', 'entity_revisions.entity_id', '=', 'entities.id')->count())->toBe(4)
        ->and(DB::table('entity_revisions')->where('lifecycle_state', 'review')->count())->toBe(7)
        ->and(DB::table('approvals')->whereNull('decision')->count())->toBe(7)
        ->and(DB::table('knowledge_gaps')->count())->toBe(3)
        ->and((int) DB::table('knowledge_gaps')->sum('occurrence_count'))->toBe(61)
        ->and(DB::table('assertion_evidence')->count())->toBeGreaterThan(0)
        ->and(DB::table('observations')->count())->toBe(DB::table('revisions')->count());
});
