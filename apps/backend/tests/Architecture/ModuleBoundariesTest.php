<?php

declare(strict_types=1);

$modules = [
    'KnowledgeEngine',
    'Search',
    'Governance',
    'Ingestion',
    'Copilot',
    'Plugins',
    'Identity',
];

foreach ($modules as $module) {
    $otherModules = array_map(
        static fn (string $otherModule): string => "Atlas\\Modules\\{$otherModule}",
        array_values(array_filter($modules, static fn (string $candidate): bool => $candidate !== $module)),
    );

    arch("{$module} não depende de detalhes de outro módulo")
        ->expect("Atlas\\Modules\\{$module}")
        ->not->toUse($otherModules);
}

arch('camadas HTTP não acessam Eloquent diretamente')
    ->expect('Atlas\\Modules\\*\\Http')
    ->not->toUse([
        'Illuminate\\Database\\Eloquent\\Model',
        'Illuminate\\Database\\Eloquent\\Builder',
        'Illuminate\\Database\\Eloquent\\Relations',
    ]);

arch('domínio do Knowledge Engine não depende de Eloquent')
    ->expect('Atlas\\Modules\\KnowledgeEngine\\Domain')
    ->not->toUse([
        'Illuminate\\Database\\Eloquent\\Model',
        'Illuminate\\Database\\Eloquent\\Builder',
        'Illuminate\\Database\\DatabaseManager',
    ]);

arch('modelos Eloquent do grafo permanecem na infraestrutura do Knowledge Engine')
    ->expect('Atlas\\Modules\\KnowledgeEngine\\Infrastructure\\Persistence\\Models')
    ->toExtend('Illuminate\\Database\\Eloquent\\Model');
