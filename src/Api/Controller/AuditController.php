<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Controller\Traits\SafeExceptionHandlerTrait;
use Api\Response\JsonResponse;
use Domain\Audit\Service\SystemActivityService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Audit API Controller.
 * 
 * Provides endpoints for auditors to verify system integrity.
 */
final class AuditController
{
    use SafeExceptionHandlerTrait;

    public function __construct(
        private readonly SystemActivityService $activityService
    ) {
    }

    /**
     * GET /api/v1/audit/verify
     * 
     * Verify the integrity of the audit hash chain.
     * Returns verification status and number of verified entries.
     */
    public function verify(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $result = $this->activityService->verifyIntegrity();

            return JsonResponse::success([
                'verified' => $result->isValid(),
                'entries_verified' => $result->verifiedCount(),
                'broken_at_entry' => $result->brokenAtId(),
                'message' => $result->isValid() 
                    ? "Audit chain integrity verified. {$result->verifiedCount()} entries validated."
                    : "Audit chain integrity BROKEN at entry: {$result->brokenAtId()}",
                'verified_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * GET /api/v1/audit/stats
     * 
     * Get audit activity statistics.
     */
    public function stats(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $totalCount = $this->activityService->getTotalCount();
            $recentActivities = $this->activityService->getRecentActivities(5);

            return JsonResponse::success([
                'total_activities' => $totalCount,
                'recent_activities' => array_map(function ($activity) {
                    return [
                        'id' => $activity->id()->toString(),
                        'type' => $activity->activityType(),
                        'description' => $activity->description(),
                        'occurred_at' => $activity->occurredAt()->format('Y-m-d H:i:s'),
                        'chain_hash' => substr($activity->chainHash()->toString(), 0, 16) . '...',
                    ];
                }, $recentActivities),
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }
}
