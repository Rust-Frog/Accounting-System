<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Controller\Traits\SafeExceptionHandlerTrait;

use Api\Response\JsonResponse;
use Domain\Approval\Repository\ApprovalRepositoryInterface;
use Domain\Audit\Service\SystemActivityService;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dashboard controller for system-wide statistics.
 * No company scoping - returns aggregate data across all companies.
 */
final class DashboardController
{
    use SafeExceptionHandlerTrait;

    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly ApprovalRepositoryInterface $approvalRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly ?SystemActivityService $systemActivityService = null
    ) {
    }

    /**
     * GET /api/v1/dashboard/stats
     * Returns system-wide statistics for the dashboard.
     */
    public function stats(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Get counts from repositories
            $pendingCount = $this->approvalRepository->countPending();
            $transactionCount = $this->transactionRepository->countToday();
            $accountCount = $this->accountRepository->countActive();
            
            return JsonResponse::success([
                'pending_approvals' => $pendingCount,
                'todays_transactions' => $transactionCount,
                'gl_accounts' => $accountCount,
                'active_sessions' => 1, // Can be expanded later
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * GET /api/v1/dashboard/recent-approvals
     * Returns the most recent 5 pending approvals system-wide.
     */
    public function recentApprovals(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $approvals = $this->approvalRepository->findRecentPending(5);
            
            $data = array_map(function($approval) {
                return [
                    'id' => $approval['id'],
                    'company_id' => $approval['company_id'],
                    'company_name' => $approval['company_name'] ?? 'Unknown Company',
                    'entity_type' => $approval['entity_type'],
                    'entity_id' => $approval['entity_id'],
                    'reason' => $approval['reason'] ?? 'Pending approval',
                    'status' => $approval['status'],
                    'amount_cents' => (int)($approval['amount_cents'] ?? 0),
                    'priority' => (int)($approval['priority'] ?? 0),
                    'requested_at' => (new \DateTimeImmutable($approval['requested_at']))->format('Y-m-d\TH:i:s\Z'),
                ];
            }, $approvals);
            
            return JsonResponse::success($data);
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * GET /api/v1/activities
     * Returns recent system-wide activities for the dashboard.
     */
    public function recentActivities(ServerRequestInterface $request): ResponseInterface
    {
        try {
            if ($this->systemActivityService === null) {
                return JsonResponse::success(['items' => []]);
            }

            $queryParams = $request->getQueryParams();
            $limit = min((int)($queryParams['limit'] ?? 10), 100);
            $offset = (int)($queryParams['offset'] ?? 0);

            $activities = $this->systemActivityService->getRecent($limit, $offset);
            $totalCount = $this->systemActivityService->getTotalCount();

            $items = array_map(fn($activity) => $activity->toArray(), $activities);

            return JsonResponse::success([
                'items' => $items,
                'pagination' => [
                    'total' => $totalCount,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + count($items)) < $totalCount,
                ]
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }
}
