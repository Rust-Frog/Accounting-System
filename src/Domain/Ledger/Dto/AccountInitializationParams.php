<?php

declare(strict_types=1);

namespace Domain\Ledger\Dto;

use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\ChartOfAccounts\ValueObject\AccountType;
use Domain\Company\ValueObject\CompanyId;
use Domain\Shared\ValueObject\Currency;

final readonly class AccountInitializationParams
{
    public function __construct(
        public AccountId $accountId,
        public CompanyId $companyId,
        public AccountType $accountType,
        public Currency $currency,
        public int $openingBalanceCents = 0
    ) {
    }
}
