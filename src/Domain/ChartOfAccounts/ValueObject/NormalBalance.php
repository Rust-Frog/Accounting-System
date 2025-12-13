<?php

declare(strict_types=1);

namespace Domain\ChartOfAccounts\ValueObject;

enum NormalBalance: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';

    public function isDebit(): bool
    {
        return $this === self::DEBIT;
    }

    public function isCredit(): bool
    {
        return $this === self::CREDIT;
    }
}
