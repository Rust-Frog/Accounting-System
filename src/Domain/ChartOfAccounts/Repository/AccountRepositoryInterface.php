<?php

declare(strict_types=1);

namespace Domain\ChartOfAccounts\Repository;

use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\ValueObject\AccountCode;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;

interface AccountRepositoryInterface
{
    public function save(Account $account): void;

    public function findById(AccountId $accountId): ?Account;

    public function findByCode(AccountCode $code, CompanyId $companyId): ?Account;

    public function existsByCode(AccountCode $code, CompanyId $companyId): bool;

    /**
     * @return array<Account>
     */
    public function findByCompany(CompanyId $companyId): array;

    /**
     * @return array<Account>
     */
    public function findActiveByCompany(CompanyId $companyId): array;

    /**
     * @return array<Account>
     */
    public function findByParent(AccountId $parentAccountId): array;

    public function delete(AccountId $accountId): void;

    /**
     * Count all active accounts system-wide.
     */
    public function countActive(): int;
}
