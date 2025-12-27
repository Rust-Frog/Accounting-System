<?php

declare(strict_types=1);

namespace Domain\Ledger\Repository;

use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\ChartOfAccounts\ValueObject\AccountType;
use Domain\Company\ValueObject\CompanyId;
use Domain\Ledger\Entity\AccountBalance;

interface LedgerRepositoryInterface
{
    public function saveBalance(AccountBalance $balance): void;

    public function getAccountBalance(
        CompanyId $companyId,
        AccountId $accountId
    ): ?AccountBalance;

    /**
     * @return array<AccountBalance>
     */
    public function getAllBalances(CompanyId $companyId): array;

    /**
     * @return array<AccountBalance>
     */
    public function getBalancesByType(CompanyId $companyId, AccountType $type): array;

    /**
     * Get the current balance in cents for an account.
     */
    public function getBalanceCents(CompanyId $companyId, AccountId $accountId): int;

    /**
     * Initialize a balance record for a new account.
     */
    public function initializeBalance(AccountBalance $balance): void;
}
