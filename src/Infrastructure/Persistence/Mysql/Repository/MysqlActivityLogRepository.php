<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use DateTimeImmutable;
use Domain\Audit\Entity\ActivityLog;
use Domain\Audit\Repository\ActivityLogRepositoryInterface;
use Domain\Audit\ValueObject\ActivityId;
use Domain\Audit\ValueObject\ActivityType;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Infrastructure\Persistence\Mysql\Hydrator\ActivityLogHydrator;
use PDO;

/**
 * MySQL implementation of ActivityLogRepositoryInterface.
 * Note: ActivityLog is append-only. No UPDATE or DELETE operations.
 */
final class MysqlActivityLogRepository extends AbstractMysqlRepository implements ActivityLogRepositoryInterface
{
    private ActivityLogHydrator $hydrator;

    public function __construct(?PDO $connection = null)
    {
        parent::__construct($connection);
        $this->hydrator = new ActivityLogHydrator();
    }

    /**
     * Save (insert only) an activity log entry.
     */
    public function save(ActivityLog $log): void
    {
        $data = $this->hydrator->extract($log);

        $sql = <<<SQL
            INSERT INTO activity_logs (
                id, company_id, actor_user_id, actor_username, actor_ip_address,
                actor_user_agent, activity_type, severity, entity_type, entity_id,
                changes_json, request_id, correlation_id, occurred_at,
                content_hash, previous_hash, chain_hash
            ) VALUES (
                :id, :company_id, :actor_user_id, :actor_username, :actor_ip_address,
                :actor_user_agent, :activity_type, :severity, :entity_type, :entity_id,
                :changes_json, :request_id, :correlation_id, :occurred_at,
                :content_hash, :previous_hash, :chain_hash
            )
        SQL;

        $this->execute($sql, $data);
    }

    public function findById(ActivityId $id): ?ActivityLog
    {
        $row = $this->fetchOne(
            'SELECT * FROM activity_logs WHERE id = :id',
            ['id' => $id->toString()]
        );

        return $row !== null ? $this->hydrator->hydrate($row) : null;
    }

    /**
     * @return array<ActivityLog>
     */
    public function findByEntity(string $entityType, string $entityId): array
    {
        return $this->findWhere(
            'entity_type = :entity_type AND entity_id = :entity_id',
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]
        );
    }

    /**
     * @return array<ActivityLog>
     */
    public function findByUser(UserId $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->findWhere(
            'actor_user_id = :user_id AND occurred_at >= :from_date AND occurred_at <= :to_date',
            [
                'user_id' => $userId->toString(),
                'from_date' => $from->format('Y-m-d H:i:s'),
                'to_date' => $to->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * @return array<ActivityLog>
     */
    public function findByCompanyAndDateRange(
        CompanyId $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): array {
        return $this->findWhere(
            'company_id = :company_id AND occurred_at >= :from_date AND occurred_at <= :to_date',
            [
                'company_id' => $companyId->toString(),
                'from_date' => $from->format('Y-m-d H:i:s'),
                'to_date' => $to->format('Y-m-d H:i:s'),
            ],
            null,
            0,
            'occurred_at ASC'
        );
    }

    /**
     * @return array<ActivityLog>
     */
    public function getRecent(CompanyId $companyId, int $limit = 100): array
    {
        return $this->findWhere(
            'company_id = :company_id',
            ['company_id' => $companyId->toString()],
            $limit
        );
    }

    /**
     * @return array<ActivityLog>
     */
    public function findByCompany(
        CompanyId $companyId,
        int $limit = 100,
        int $offset = 0,
        string $sortOrder = 'DESC'
    ): array {
        return $this->findWhere(
            'company_id = :company_id',
            ['company_id' => $companyId->toString()],
            $limit,
            $offset,
            'occurred_at ' . $sortOrder
        );
    }

    /**
     * @return array<ActivityLog>
     */
    public function findByActivityType(CompanyId $companyId, ActivityType $type): array
    {
        return $this->findWhere(
            'company_id = :company_id AND activity_type = :activity_type',
            [
                'company_id' => $companyId->toString(),
                'activity_type' => $type->value,
            ]
        );
    }

    public function countByCompany(CompanyId $companyId): int
    {
        $result = $this->fetchOne(
            'SELECT COUNT(*) as count FROM activity_logs WHERE company_id = :company_id',
            ['company_id' => $companyId->toString()]
        );

        return $result !== null ? (int) $result['count'] : 0;
    }

    /**
     * Execute a query and hydrate results into ActivityLog entities.
     *
     * @param string $sql
     * @param array<string, mixed> $params
     * @return array<ActivityLog>
     */
    private function queryLogs(string $sql, array $params): array
    {
        $rows = $this->fetchAll($sql, $params);
        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    /**
     * Helper to find logs with common logic.
     *
     * @param string $condition WHERE clause snippet
     * @param array<string, mixed> $params Query parameters
     * @param int|null $limit
     * @param int $offset
     * @param string $orderBy ORDER BY clause
     * @return array<ActivityLog>
     */
    private function findWhere(
        string $condition,
        array $params,
        ?int $limit = null,
        int $offset = 0,
        string $orderBy = 'occurred_at DESC'
    ): array {
        $sql = "SELECT * FROM activity_logs WHERE {$condition} ORDER BY {$orderBy}";

        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params['limit'] = (int) $limit;
            $params['offset'] = (int) $offset;
        }

        return $this->queryLogs($sql, $params);
    }
}
