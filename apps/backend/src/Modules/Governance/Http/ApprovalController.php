<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Http;

use App\Http\Api\Authorization\ApprovalAccessFactory;
use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Modules\Governance\Application\ApprovalReadService;
use Atlas\Modules\Governance\Application\ApprovalReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ApiOperation('Consultar propostas pendentes de aprovação', ['Governança'], [200 => 'Propostas com proveniência', 403 => 'Escopo não autorizado'])]
final class ApprovalController
{
    public function __construct(private readonly ApprovalReadService $approvals, private readonly ApprovalReviewService $reviews, private readonly ApprovalAccessFactory $accessFactory) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->approvals->pending($this->accessFactory->fromRequest($request)));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json($this->approvals->detail($this->accessFactory->fromRequest($request), $id));
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['justification' => ['required', 'string', 'min:1', 'max:4000']]);
        $access = $this->accessFactory->fromRequest($request);
        $approval = $this->approvals->commandContext($access, $id);
        $commit = (string) ($approval['proposal']['commit_message'] ?? 'docs(knowledge): revisão humana aprovada')."\n\n".$validated['justification'];
        $reference = $this->reviews->approve($id, $access->actorId, $validated['justification'], (string) $request->header('Idempotency-Key'));

        return response()->json(['approval_id' => $id, 'decision' => 'approved', 'pull_request' => ['reference' => $reference], 'commit' => $commit, 'reindexing' => ['status' => 'scheduled', 'target' => '40s']], 201);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:1', 'max:4000']]);
        $access = $this->accessFactory->fromRequest($request);
        $this->approvals->commandContext($access, $id);
        $this->reviews->reject($id, $access->actorId, $validated['reason'], (string) $request->header('Idempotency-Key'));

        return response()->json(['approval_id' => $id, 'decision' => 'rejected']);
    }

    public function requestAiRevision(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['instruction' => ['required', 'string', 'min:1', 'max:4000']]);
        $access = $this->accessFactory->fromRequest($request);
        $this->approvals->commandContext($access, $id);
        $this->reviews->requestAiAdjustment($id, $access->actorId, $validated['instruction']);

        return response()->json(['approval_id' => $id, 'status' => 'ai_revision_requested']);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['proposed_text' => ['required', 'string', 'min:1', 'max:20000']]);
        $access = $this->accessFactory->fromRequest($request);
        $this->approvals->commandContext($access, $id);
        $this->reviews->editText($id, $access->actorId, $validated['proposed_text']);

        return response()->json(['approval_id' => $id, 'status' => 'proposal_text_updated', 'proposed_text' => $validated['proposed_text']]);
    }
}
