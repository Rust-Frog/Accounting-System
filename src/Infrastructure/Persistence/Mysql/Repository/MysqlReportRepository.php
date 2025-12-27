<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use Domain\Reporting\Entity\Report;
use Domain\Reporting\Repository\ReportRepositoryInterface;
use Domain\Reporting\ValueObject\ReportId;
use Domain\Company\ValueObject\CompanyId;

class MysqlReportRepository extends AbstractMysqlRepository implements ReportRepositoryInterface
{
    public function save(Report $report): void
    {
        $sql = "INSERT INTO reports (
            id,
            company_id,
            report_type,
            period_type,
            period_start,
            period_end,
            generated_at,
            generated_by,
            data_json
        ) VALUES (
            :id,
            :company_id,
            :report_type,
            :period_type,
            :period_start,
            :period_end,
            :generated_at,
            :generated_by,
            :data_json
        ) ON DUPLICATE KEY UPDATE
            data_json = VALUES(data_json),
            generated_at = VALUES(generated_at)";

        // Note: Report entity doesn't track generated_by, using placeholder system UUID
        $systemUserId = '00000000-0000-0000-0000-000000000000';

        $params = [
            'id' => $report->id()->toString(),
            'company_id' => $report->companyId()->toString(),
            'report_type' => $report->type(),
            'period_type' => $report->period()->type()->value,
            'period_start' => $report->period()->startDate()->format('Y-m-d H:i:s'),
            'period_end' => $report->period()->endDate()->format('Y-m-d H:i:s'),
            'generated_at' => $report->generatedAt()->format('Y-m-d H:i:s'),
            'generated_by' => $systemUserId,
            'data_json' => json_encode($report->data(), JSON_THROW_ON_ERROR),
        ];

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
    }

    public function findById(ReportId $id): ?Report
    {
        $sql = "SELECT * FROM reports WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id->toString()]);
        
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrateReport($row);
    }

    public function findByCompany(CompanyId $companyId, int $limit = 100, int $offset = 0): array
    {
        $rows = $this->fetchPaged(
            'SELECT * FROM reports WHERE company_id = :company_id ORDER BY generated_at DESC',
            ['company_id' => $companyId->toString()],
            new \Domain\Shared\ValueObject\Pagination($limit, $offset)
        );
        
        return array_map(fn(array $row) => $this->hydrateReport($row), $rows);
    }

    public function findByCompanyAndType(CompanyId $companyId, string $reportType, int $limit = 10): array
    {
        $sql = "SELECT * FROM reports WHERE company_id = :company_id AND report_type = :report_type ORDER BY generated_at DESC LIMIT :limit";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('company_id', $companyId->toString());
        $stmt->bindValue('report_type', $reportType);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrateReport($row);
        }
        
        return $results;
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $rows = $this->fetchPaged(
            'SELECT * FROM reports ORDER BY generated_at DESC',
            [],
            new \Domain\Shared\ValueObject\Pagination($limit, $offset)
        );

        return array_map(fn(array $row) => $this->hydrateReport($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateReport(array $row): Report
    {
        return Report::reconstruct(
            \Domain\Reporting\ValueObject\ReportId::fromString($row['id']),
            \Domain\Company\ValueObject\CompanyId::fromString($row['company_id']),
            \Domain\Reporting\ValueObject\ReportPeriod::custom(
                new \DateTimeImmutable($row['period_start']),
                new \DateTimeImmutable($row['period_end'])
            ),
            $row['report_type'],
            json_decode($row['data_json'], true, 512, JSON_THROW_ON_ERROR),
            new \DateTimeImmutable($row['generated_at'])
        );
    }
}
