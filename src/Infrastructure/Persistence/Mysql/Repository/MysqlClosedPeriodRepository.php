<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Reporting\Entity\ClosedPeriod;
use Domain\Reporting\Repository\ClosedPeriodRepositoryInterface;
use Domain\Shared\ValueObject\HashChain\ContentHash;
use PDO;

final class MysqlClosedPeriodRepository extends AbstractMysqlRepository implements ClosedPeriodRepositoryInterface
{
    public function save(ClosedPeriod $closedPeriod): void
    {
        $exists = $this->exists(
            'SELECT 1 FROM closed_periods WHERE id = :id',
            ['id' => $closedPeriod->id()]
        );

        if ($exists) {
            // Closed periods are immutable once created, no update needed
            return;
        }

        $sql = <<<SQL
            INSERT INTO closed_periods (
                id, company_id, start_date, end_date, closed_by, closed_at,
                approval_id, net_income_cents, chain_hash, created_at
            ) VALUES (
                :id, :company_id, :start_date, :end_date, :closed_by, :closed_at,
                :approval_id, :net_income_cents, :chain_hash, :created_at
            )
        SQL;

        $this->execute($sql, [
            'id' => $closedPeriod->id(),
            'company_id' => $closedPeriod->companyId()->toString(),
            'start_date' => $closedPeriod->startDate()->format('Y-m-d'),
            'end_date' => $closedPeriod->endDate()->format('Y-m-d'),
            'closed_by' => $closedPeriod->closedBy()->toString(),
            'closed_at' => $closedPeriod->closedAt()->format('Y-m-d H:i:s'),
            'approval_id' => $closedPeriod->approvalId(),
            'net_income_cents' => $closedPeriod->netIncomeCents(),
            'chain_hash' => $closedPeriod->chainHash()?->toString(),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?ClosedPeriod
    {
        $row = $this->fetchOne(
            'SELECT * FROM closed_periods WHERE id = :id',
            ['id' => $id]
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findByCompany(CompanyId $companyId): array
    {
        $rows = $this->fetchAll(
            'SELECT * FROM closed_periods WHERE company_id = :company_id ORDER BY end_date DESC',
            ['company_id' => $companyId->toString()]
        );

        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function isDateInClosedPeriod(CompanyId $companyId, \DateTimeImmutable $date): bool
    {
        $dateStr = $date->format('Y-m-d');
        
        return $this->exists(
            'SELECT 1 FROM closed_periods WHERE company_id = :company_id AND :date BETWEEN start_date AND end_date',
            ['company_id' => $companyId->toString(), 'date' => $dateStr]
        );
    }

    public function findClosedPeriodContainingDate(CompanyId $companyId, \DateTimeImmutable $date): ?ClosedPeriod
    {
        $dateStr = $date->format('Y-m-d');
        
        $row = $this->fetchOne(
            'SELECT * FROM closed_periods WHERE company_id = :company_id AND :date BETWEEN start_date AND end_date',
            ['company_id' => $companyId->toString(), 'date' => $dateStr]
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): ClosedPeriod
    {
        return new ClosedPeriod(
            id: $row['id'],
            companyId: CompanyId::fromString($row['company_id']),
            startDate: new \DateTimeImmutable($row['start_date']),
            endDate: new \DateTimeImmutable($row['end_date']),
            closedBy: UserId::fromString($row['closed_by']),
            closedAt: new \DateTimeImmutable($row['closed_at']),
            approvalId: $row['approval_id'],
            netIncomeCents: (int) $row['net_income_cents'],
            chainHash: $row['chain_hash'] ? ContentHash::fromString($row['chain_hash']) : null,
        );
    }
}
