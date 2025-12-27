<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use Domain\Approval\Entity\Approval;
use Domain\Approval\Repository\ApprovalRepositoryInterface;
use Domain\Approval\ValueObject\ApprovalId;
use Domain\Approval\ValueObject\ApprovalStatus;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Infrastructure\Persistence\Mysql\Hydrator\ApprovalHydrator;
use PDO;

/**
 * MySQL implementation of ApprovalRepositoryInterface.
 */
final class MysqlApprovalRepository extends AbstractMysqlRepository implements ApprovalRepositoryInterface
{
    private ApprovalHydrator $hydrator;

    public function __construct(?PDO $connection = null)
    {
        parent::__construct($connection);
        $this->hydrator = new ApprovalHydrator();
    }

    public function save(Approval $approval): void
    {
        $data = $this->hydrator->extract($approval);
        $this->upsert($data);
    }

    public function findById(ApprovalId $id): ?Approval
    {
        $row = $this->fetchOne(
            'SELECT * FROM approvals WHERE id = :id',
            ['id' => $id->toString()]
        );

        return $row !== null ? $this->hydrator->hydrate($row) : null;
    }

    public function findByEntity(string $entityType, string $entityId): ?Approval
    {
        $row = $this->fetchOne(
            'SELECT * FROM approvals WHERE entity_type = :entity_type AND entity_id = :entity_id',
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]
        );

        return $row !== null ? $this->hydrator->hydrate($row) : null;
    }

    /**
     * @return array<Approval>
     */
    public function findPendingByCompany(CompanyId $companyId, int $limit = 20, int $offset = 0): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM approvals 
             WHERE company_id = :company_id AND status = 'pending'
             ORDER BY priority DESC, requested_at ASC
             LIMIT :limit OFFSET :offset",
            [
                'company_id' => $companyId->toString(),
                'limit' => $limit,
                'offset' => $offset
            ]
        );

        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    /**
     * @return array<Approval>
     */
    public function findPendingForApprover(UserId $approverId): array
    {
        // Note: In a real system, you'd have approver assignments
        // For now, return all pending approvals (admins can approve any)
        $rows = $this->fetchAll(
            "SELECT * FROM approvals 
             WHERE status = 'pending'
             ORDER BY priority DESC, requested_at ASC"
        );

        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    /**
     * @return array<Approval>
     */
    public function findByStatus(CompanyId $companyId, ApprovalStatus $status): array
    {
        $rows = $this->fetchAll(
            'SELECT * FROM approvals 
             WHERE company_id = :company_id AND status = :status
             ORDER BY requested_at DESC',
            [
                'company_id' => $companyId->toString(),
                'status' => $status->value,
            ]
        );

        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    /**
     * @return array<Approval>
     */
    public function findExpired(): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM approvals 
             WHERE status = 'pending' 
             AND expires_at IS NOT NULL 
             AND expires_at < NOW()
             ORDER BY expires_at ASC"
        );

        return array_map(fn(array $row) => $this->hydrator->hydrate($row), $rows);
    }

    public function countPendingByCompany(CompanyId $companyId): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(*) as count FROM approvals 
             WHERE company_id = :company_id AND status = 'pending'",
            ['company_id' => $companyId->toString()]
        );

        return $result !== null ? (int) $result['count'] : 0;
    }

    public function countPending(): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(*) as count FROM approvals WHERE status = 'pending'"
        );

        return $result !== null ? (int) $result['count'] : 0;
    }

    public function findRecentPending(int $limit = 5): array
    {
        $sql = "SELECT a.*, c.company_name as company_name 
                FROM approvals a
                LEFT JOIN companies c ON a.company_id = c.id
                WHERE a.status = 'pending'
                ORDER BY a.requested_at DESC
                LIMIT :limit";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert or update an approval.
     *
     * @param array<string, mixed> $data
     */
    private function upsert(array $data): void
    {
        $exists = $this->exists(
            'SELECT 1 FROM approvals WHERE id = :id',
            ['id' => $data['id']]
        );

        if ($exists) {
            $sql = <<<SQL
                UPDATE approvals SET
                    status = :status,
                    reviewed_by = :reviewed_by,
                    reviewed_at = :reviewed_at,
                    review_notes = :review_notes,
                    proof_json = :proof_json
                WHERE id = :id
            SQL;

            $this->execute($sql, [
                'id' => $data['id'],
                'status' => $data['status'],
                'reviewed_by' => $data['reviewed_by'],
                'reviewed_at' => $data['reviewed_at'],
                'review_notes' => $data['review_notes'],
                'proof_json' => $data['proof_json'],
            ]);
        } else {
            $sql = <<<SQL
                INSERT INTO approvals (
                    id, company_id, approval_type, entity_type, entity_id, reason,
                    requested_by, requested_at, amount_cents, priority, expires_at, status,
                    reviewed_by, reviewed_at, review_notes, proof_json
                ) VALUES (
                    :id, :company_id, :approval_type, :entity_type, :entity_id, :reason,
                    :requested_by, :requested_at, :amount_cents, :priority, :expires_at, :status,
                    :reviewed_by, :reviewed_at, :review_notes, :proof_json
                )
            SQL;

            $this->execute($sql, $data);
        }
    }
}
