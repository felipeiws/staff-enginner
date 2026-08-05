<?php

declare(strict_types=1);

use App\Http\Api\Copilot\CopilotAskController;
use App\Http\Api\Copilot\KnowledgeCandidateController;
use App\Http\Api\Governance\HomeInsightsController;
use App\Http\Api\Governance\KnowledgeGapController;
use App\Http\Api\Governance\KnowledgeHealthController;
use App\Http\Api\OpenApi\OpenApiController;
use App\Http\Api\Workers\WorkerEmbeddingResultController;
use App\Http\Controllers\MetricsController;
use Atlas\Modules\Governance\Http\ApprovalController;
use Atlas\Modules\Identity\Http\CurrentPrincipalController;
use Atlas\Modules\Identity\Http\SessionController;
use Atlas\Modules\Ingestion\Http\VisualArtifactController;
use Atlas\Modules\KnowledgeEngine\Http\ApplicationReadController;
use Atlas\Modules\KnowledgeEngine\Http\EntityDetailController;
use Atlas\Modules\KnowledgeEngine\Http\GraphReadController;
use Atlas\Modules\KnowledgeEngine\Http\HealthController;
use Atlas\Modules\KnowledgeEngine\Http\UxDecisionController;
use Atlas\Modules\Search\Http\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');
Route::get('/metrics', MetricsController::class)->name('metrics');
Route::get('/auth/csrf', [SessionController::class, 'csrf'])->name('identity.csrf');
Route::post('/auth/session', [SessionController::class, 'store'])->name('identity.session.store');
Route::delete('/auth/session', [SessionController::class, 'destroy'])->middleware('identity.principal')->name('identity.session.destroy');
Route::get('/api/me', CurrentPrincipalController::class)->middleware('identity.principal')->name('identity.current');

Route::prefix('api/v1')->middleware(['api.correlation', 'api.observe', 'api.negotiate'])->group(function (): void {
    Route::get('/openapi.json', OpenApiController::class)->middleware('api.rate-limit')->name('api.v1.openapi');
    Route::middleware(['identity.principal', 'api.rate-limit'])->group(function (): void {
        Route::get('/me', CurrentPrincipalController::class)->name('api.v1.identity.current');
        Route::get('/organizations/{id}', [ApplicationReadController::class, 'organization'])->name('api.v1.organizations.show');
        Route::get('/applications', [ApplicationReadController::class, 'applications'])->name('api.v1.applications.index');
        Route::get('/home', [ApplicationReadController::class, 'home'])->name('api.v1.home.show');
        Route::get('/applications/{id}', [ApplicationReadController::class, 'application'])->name('api.v1.applications.show');
        Route::get('/applications/{id}/modules', [ApplicationReadController::class, 'modules'])->name('api.v1.applications.modules.index');
        Route::get('/applications/{id}/tree', [ApplicationReadController::class, 'tree'])->name('api.v1.applications.tree.show');
        Route::get('/entities/{id}', EntityDetailController::class)->name('api.v1.entities.show');
        Route::get('/ux-decisions', UxDecisionController::class)->name('api.v1.ux-decisions.index');
        Route::get('/graph/neighborhood', [GraphReadController::class, 'neighborhood'])->name('api.v1.graph.neighborhood');
        Route::get('/graph/orphans', [GraphReadController::class, 'orphans'])->name('api.v1.graph.orphans');
        Route::get('/graph/nodes/{id}', [GraphReadController::class, 'node'])->name('api.v1.graph.nodes.show');
        Route::get('/graph/diagnosis', [GraphReadController::class, 'diagnosis'])->name('api.v1.graph.diagnosis');
        Route::get('/search', SearchController::class)->name('api.v1.search.index');
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('api.v1.approvals.index');
        Route::get('/approvals/{id}', [ApprovalController::class, 'show'])->name('api.v1.approvals.show');
        Route::get('/knowledge-gaps', [KnowledgeGapController::class, 'index'])->name('api.v1.knowledge-gaps.index');
        Route::get('/knowledge-health', KnowledgeHealthController::class)->name('api.v1.knowledge-health.show');
        Route::get('/insights', HomeInsightsController::class)->name('api.v1.insights.index');
        Route::middleware('api.idempotency')->group(function (): void {
            Route::post('/worker-results/embeddings', WorkerEmbeddingResultController::class)->name('api.v1.worker-results.embeddings.store');
            Route::post('/copilot/ask', CopilotAskController::class)->name('api.v1.copilot.ask');
            Route::post('/copilot/knowledge-candidates', KnowledgeCandidateController::class)->name('api.v1.copilot.knowledge-candidates.store');
            Route::post('/knowledge-gaps/{id}/assign', [KnowledgeGapController::class, 'assign'])->name('api.v1.knowledge-gaps.assign');
            Route::post('/visual-artifacts', VisualArtifactController::class)->name('api.v1.visual-artifacts.store');
            Route::post('/worker-results/visual-artifacts', VisualArtifactController::class)->name('api.v1.worker-results.visual-artifacts.store');
            Route::post('/approvals/{id}/approve', [ApprovalController::class, 'approve'])->name('api.v1.approvals.approve');
            Route::post('/approvals/{id}/reject', [ApprovalController::class, 'reject'])->name('api.v1.approvals.reject');
            Route::post('/approvals/{id}/request-ai-revision', [ApprovalController::class, 'requestAiRevision'])->name('api.v1.approvals.request-ai-revision');
            Route::patch('/approvals/{id}', [ApprovalController::class, 'update'])->name('api.v1.approvals.update');
        });
    });
});
