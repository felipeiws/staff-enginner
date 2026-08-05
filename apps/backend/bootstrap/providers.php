<?php

use App\Providers\AppServiceProvider;
use Atlas\Modules\Copilot\CopilotServiceProvider;
use Atlas\Modules\Governance\GovernanceServiceProvider;
use Atlas\Modules\Identity\IdentityServiceProvider;
use Atlas\Modules\Ingestion\IngestionServiceProvider;
use Atlas\Modules\KnowledgeEngine\KnowledgeEngineServiceProvider;
use Atlas\Modules\Plugins\PluginsServiceProvider;
use Atlas\Modules\Search\SearchServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    KnowledgeEngineServiceProvider::class,
    SearchServiceProvider::class,
    GovernanceServiceProvider::class,
    IngestionServiceProvider::class,
    CopilotServiceProvider::class,
    PluginsServiceProvider::class,
];
