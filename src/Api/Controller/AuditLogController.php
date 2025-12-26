<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Controller\Traits\SafeExceptionHandlerTrait;

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
    use SafeExceptionHandlerTrait;

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
            $filters = $this->parseListFilters($request->getQueryParams());
            $companyIdVo = CompanyId::fromString($companyId);

            $logs = $this->fetchLogs($companyIdVo, $filters);
            $logs = $this->applyInMemoryFilters($logs, $filters);

            $totalCount = $this->activityLogRepository->countByCompany($companyIdVo);
            $data = array_map([$this, 'formatLogSummary'], array_values($logs));

            return JsonResponse::success([
                'logs' => $data,
                'pagination' => [
                    'total' => $totalCount,
                    'limit' => $filters['limit'],
                    'offset' => $filters['offset'],
                    'has_more' => ($filters['offset'] + count($data)) < $totalCount,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), 400);
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * Parse and validate filter parameters from query string.
     * @return array{limit: int, offset: int, sortOrder: string, fromDate: ?\DateTimeImmutable, toDate: ?\DateTimeImmutable, activityType: ?string, entityType: ?string, severity: ?string}
     */
    private function parseListFilters(array $queryParams): array
    {
        return [
            'limit' => min((int) ($queryParams['limit'] ?? 100), 500),
            'offset' => (int) ($queryParams['offset'] ?? 0),
            'sortOrder' => strtoupper($queryParams['sort'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC',
            'fromDate' => isset($queryParams['from_date'])
                ? new \DateTimeImmutable($queryParams['from_date'])
                : null,
            'toDate' => isset($queryParams['to_date'])
                ? new \DateTimeImmutable($queryParams['to_date'] . ' 23:59:59')
                : null,
            'activityType' => $queryParams['activity_type'] ?? null,
            'entityType' => $queryParams['entity_type'] ?? null,
            'severity' => $queryParams['severity'] ?? null,
        ];
    }

    /**
     * Fetch logs from repository based on filters.
     */
    private function fetchLogs(CompanyId $companyId, array $filters): array
    {
        if ($filters['fromDate'] !== null && $filters['toDate'] !== null) {
            $logs = $this->activityLogRepository->findByCompanyAndDateRange(
                $companyId,
                $filters['fromDate'],
                $filters['toDate']
            );
            return array_slice($logs, $filters['offset'], $filters['limit']);
        }

        return $this->activityLogRepository->findByCompany(
            $companyId,
            $filters['limit'],
            $filters['offset'],
            $filters['sortOrder']
        );
    }

    /**
     * Apply in-memory filters that can't be pushed to database.
     */
    private function applyInMemoryFilters(array $logs, array $filters): array
    {
        if ($filters['activityType'] !== null) {
            $logs = array_filter($logs, fn($log) => $log->activityType()->value === $filters['activityType']);
        }
        if ($filters['entityType'] !== null) {
            $logs = array_filter($logs, fn($log) => $log->entityType() === $filters['entityType']);
        }
        if ($filters['severity'] !== null) {
            $logs = array_filter($logs, fn($log) => $log->severity()->value === $filters['severity']);
        }
        return $logs;
    }

    /**
     * Format a single log entry for list responses.
     */
    private function formatLogSummary(mixed $log): array
    {
        return [
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
        ];
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
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
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
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }
}
