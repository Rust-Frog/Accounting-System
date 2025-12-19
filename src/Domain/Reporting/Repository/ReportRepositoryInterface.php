<?php

declare(strict_types=1);

namespace Domain\Reporting\Repository;

use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\Entity\Report;
use Domain\Reporting\ValueObject\ReportId;
use Domain\Reporting\ValueObject\ReportPeriod;

/**
 * Repository interface for persisting generated reports.
 */
interface ReportRepositoryInterface
{
    /**
     * Save generated report for history.
     */
    public function save(Report $report): void;

    /**
     * Find report by ID.
     */
    public function findById(ReportId $id): ?Report;

    /**
     * @return array<Report>
     */
    public function findByCompany(CompanyId $companyId): array;

    /**
     * @return array<Report>
     */
    public function findByCompanyAndType(CompanyId $companyId, string $reportType, int $limit = 10): array;
}
