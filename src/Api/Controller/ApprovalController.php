<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use Domain\Approval\Entity\Approval;
use Domain\Approval\Repository\ApprovalRepositoryInterface;
use Domain\Approval\ValueObject\ApprovalId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Approval controller for approval workflow management.
 */
final class ApprovalController
{
    public function __construct(
        private readonly ApprovalRepositoryInterface $approvalRepository
    ) {
    }

    /**
     * GET /api/v1/companies/{companyId}/approvals/pending
     */
    public function pending(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        if ($companyId === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        try {
            $queryParams = $request->getQueryParams();
            $page = max(1, (int)($queryParams['page'] ?? 1)); 
            $limit = min(100, max(1, (int)($queryParams['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            $approvals = $this->approvalRepository->findPendingByCompany(
                CompanyId::fromString($companyId),
                $limit,
                $offset
            );

            $data = array_map(fn(Approval $a) => $this->formatApproval($a), $approvals);

            return JsonResponse::success([
                'data' => $data,
                'meta' => [
                    'page' => $page,
                    'limit' => $limit,
                ]
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/companies/{companyId}/approvals/{id}/approve
     */
    public function approve(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if ($id === null || $userId === null) {
            return JsonResponse::error('Approval ID and authentication required', 400);
        }

        try {
            $approval = $this->approvalRepository->findById(
                ApprovalId::fromString($id)
            );

            if ($approval === null) {
                return JsonResponse::error('Approval not found', 404);
            }

            $approval->approve(
                UserId::fromString($userId),
                $body['notes'] ?? null
            );

            $this->approvalRepository->save($approval);

            return JsonResponse::success($this->formatApproval($approval));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/companies/{companyId}/approvals/{id}/reject
     */
    public function reject(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if ($id === null || $userId === null) {
            return JsonResponse::error('Approval ID and authentication required', 400);
        }

        if (empty($body['reason'])) {
            return JsonResponse::error('Rejection reason is required', 422);
        }

        try {
            $approval = $this->approvalRepository->findById(
                ApprovalId::fromString($id)
            );

            if ($approval === null) {
                return JsonResponse::error('Approval not found', 404);
            }

            $approval->reject(
                UserId::fromString($userId),
                $body['reason']
            );

            $this->approvalRepository->save($approval);

            return JsonResponse::success($this->formatApproval($approval));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Format approval for API response.
     */
    private function formatApproval(Approval $approval): array
    {
        return [
            'id' => $approval->id()->toString(),
            'company_id' => $approval->companyId()->toString(),
            'type' => $approval->approvalType()->value,
            'entity_type' => $approval->entityType(),
            'entity_id' => $approval->entityId(),
            'status' => $approval->status()->value,
            'priority' => $approval->priority(),
            'requested_at' => $approval->requestedAt()->format('Y-m-d\TH:i:s\Z'),
            'expires_at' => $approval->expiresAt()->format('Y-m-d\TH:i:s\Z'),
            'reviewed_at' => $approval->reviewedAt()?->format('Y-m-d\TH:i:s\Z'),
            'review_notes' => $approval->reviewNotes(),
        ];
    }
}
