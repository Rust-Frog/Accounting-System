<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use Domain\Audit\Repository\ActivityLogRepositoryInterface;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for audit log viewing (read-only).
 * BR-AT-001: Audit logs are append-only, no modifications allowed.
 */
final class AuditLogController
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $activityLogRepository,
    ) {
    }

    /**
     * GET /api/v1/companies/{companyId}/audit-logs
     * List audit logs for a company with optional filters.
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        
        if ($companyId === null) {
            return JsonResponse::error('Company ID is required', 400);
        }

        try {
            $queryParams = $request->getQueryParams();
            $limit = min((int) ($queryParams['limit'] ?? 100), 500);
            $offset = (int) ($queryParams['offset'] ?? 0);
            $sortOrder = strtoupper($queryParams['sort'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

            // Optional date filters
            $fromDate = isset($queryParams['from_date']) 
                ? new \DateTimeImmutable($queryParams['from_date']) 
                : null;
            $toDate = isset($queryParams['to_date']) 
                ? new \DateTimeImmutable($queryParams['to_date'] . ' 23:59:59') 
                : null;

            // Optional filters
            $activityType = $queryParams['activity_type'] ?? null;
            $entityType = $queryParams['entity_type'] ?? null;
            $severity = $queryParams['severity'] ?? null;

            $companyIdVo = CompanyId::fromString($companyId);

            // Get logs based on filters
            if ($fromDate !== null && $toDate !== null) {
                $logs = $this->activityLogRepository->findByCompanyAndDateRange(
                    $companyIdVo,
                    $fromDate,
                    $toDate
                );
                // Apply pagination manually for date range queries
                $logs = array_slice($logs, $offset, $limit);
            } else {
                $logs = $this->activityLogRepository->findByCompany(
                    $companyIdVo,
                    $limit,
                    $offset,
                    $sortOrder
                );
            }

            // Apply additional filters if provided
            if ($activityType !== null) {
                $logs = array_filter($logs, fn($log) => $log->activityType()->value === $activityType);
            }
            if ($entityType !== null) {
                $logs = array_filter($logs, fn($log) => $log->entityType() === $entityType);
            }
            if ($severity !== null) {
                $logs = array_filter($logs, fn($log) => $log->severity()->value === $severity);
            }

            // Get total count for pagination
            $totalCount = $this->activityLogRepository->countByCompany($companyIdVo);

            $data = array_map(fn($log) => [
                'id' => $log->id()->toString(),
                'company_id' => $log->companyId()->toString(),
                'actor' => [
                    'user_id' => $log->actor()->userId(),
                    'username' => $log->actor()->actorName(),
                    'type' => $log->actor()->actorType(),
                ],
                'activity_type' => $log->activityType()->value,
                'category' => $log->category(),
                'severity' => $log->severity()->value,
                'entity_type' => $log->entityType(),
                'entity_id' => $log->entityId(),
                'action' => $log->action(),
                'changes' => array_map(fn($c) => $c->toArray(), $log->changes()),
                'context' => [
                    'ip_address' => $log->context()->ipAddress(),
                    'user_agent' => $log->context()->userAgent(),
                    'request_id' => $log->context()->requestId(),
                ],
                'occurred_at' => $log->occurredAt()->format('Y-m-d\TH:i:s\Z'),
                'content_hash' => $log->contentHash()?->toString(),
            ], array_values($logs));

            return JsonResponse::success([
                'logs' => $data,
                'pagination' => [
                    'total' => $totalCount,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + count($data)) < $totalCount,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error('Invalid parameter: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            return JsonResponse::error('Failed to fetch audit logs: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/companies/{companyId}/audit-logs/{id}
     * Get a single audit log entry.
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $logId = $request->getAttribute('id');
        
        if ($logId === null) {
            return JsonResponse::error('Log ID is required', 400);
        }

        try {
            $log = $this->activityLogRepository->findById(
                \Domain\Audit\ValueObject\ActivityId::fromString($logId)
            );

            if ($log === null) {
                return JsonResponse::error('Audit log entry not found', 404);
            }

            return JsonResponse::success([
                'id' => $log->id()->toString(),
                'company_id' => $log->companyId()->toString(),
                'actor' => [
                    'user_id' => $log->actor()->userId(),
                    'username' => $log->actor()->actorName(),
                    'type' => $log->actor()->actorType(),
                ],
                'activity_type' => $log->activityType()->value,
                'category' => $log->category(),
                'severity' => $log->severity()->value,
                'entity_type' => $log->entityType(),
                'entity_id' => $log->entityId(),
                'action' => $log->action(),
                'previous_state' => $log->previousState(),
                'new_state' => $log->newState(),
                'changes' => array_map(fn($c) => $c->toArray(), $log->changes()),
                'context' => [
                    'ip_address' => $log->context()->ipAddress(),
                    'user_agent' => $log->context()->userAgent(),
                    'request_id' => $log->context()->requestId(),
                    'correlation_id' => $log->context()->correlationId(),
                    'session_id' => $log->context()->sessionId(),
                    'endpoint' => $log->context()->endpoint(),
                    'http_method' => $log->context()->httpMethod(),
                ],
                'occurred_at' => $log->occurredAt()->format('Y-m-d\TH:i:s\Z'),
                'content_hash' => $log->contentHash()?->toString(),
                'previous_hash' => $log->previousHash()?->toString(),
                'chain_hash' => $log->chainLink()?->computeHash()->toString(),
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error('Failed to fetch audit log: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/companies/{companyId}/audit-logs/stats
     * Get audit log statistics.
     */
    public function stats(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        
        if ($companyId === null) {
            return JsonResponse::error('Company ID is required', 400);
        }

        try {
            $companyIdVo = CompanyId::fromString($companyId);
            $totalCount = $this->activityLogRepository->countByCompany($companyIdVo);

            // Get recent logs for category breakdown
            $recentLogs = $this->activityLogRepository->getRecent($companyIdVo, 1000);

            $byCategory = [];
            $bySeverity = [];
            $byActivityType = [];

            foreach ($recentLogs as $log) {
                $category = $log->category();
                $severity = $log->severity()->value;
                $activityType = $log->activityType()->value;

                $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
                $bySeverity[$severity] = ($bySeverity[$severity] ?? 0) + 1;
                $byActivityType[$activityType] = ($byActivityType[$activityType] ?? 0) + 1;
            }

            return JsonResponse::success([
                'total_count' => $totalCount,
                'by_category' => $byCategory,
                'by_severity' => $bySeverity,
                'by_activity_type' => $byActivityType,
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error('Failed to fetch audit stats: ' . $e->getMessage(), 500);
        }
    }
}
