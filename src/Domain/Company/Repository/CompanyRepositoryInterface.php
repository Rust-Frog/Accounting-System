<?php

declare(strict_types=1);

namespace Domain\Company\Repository;

use Domain\Company\Entity\Company;
use Domain\Company\ValueObject\CompanyId;
use Domain\Company\ValueObject\TaxIdentifier;

interface CompanyRepositoryInterface
{
    public function save(Company $company): void;

    public function findById(CompanyId $companyId): ?Company;

    public function findByTaxId(TaxIdentifier $taxId): ?Company;

    public function existsByTaxId(TaxIdentifier $taxId): bool;

    /**
     * @return array<Company>
     */
    public function findPendingCompanies(): array;

    /**
     * @return array<Company>
     */
    public function findAll(int $limit = 100, int $offset = 0): array;

    /**
     * Find only active companies (excludes voided, suspended, pending).
     * @return array<Company>
     */
    public function findActiveCompanies(int $limit = 100, int $offset = 0): array;
}
