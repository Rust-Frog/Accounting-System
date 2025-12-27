<?php

declare(strict_types=1);

namespace Domain\Reporting\Repository;

use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\Entity\ClosedPeriod;

interface ClosedPeriodRepositoryInterface
{
    public function save(ClosedPeriod $closedPeriod): void;
    
    public function findById(string $id): ?ClosedPeriod;
    
    /**
     * Find all closed periods for a company.
     * @return array<ClosedPeriod>
     */
    public function findByCompany(CompanyId $companyId): array;
    
    /**
     * Check if a date falls within any closed period for a company.
     */
    public function isDateInClosedPeriod(CompanyId $companyId, \DateTimeImmutable $date): bool;
    
    /**
     * Find the closed period containing a specific date for a company.
     */
    public function findClosedPeriodContainingDate(CompanyId $companyId, \DateTimeImmutable $date): ?ClosedPeriod;
}
