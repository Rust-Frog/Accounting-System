<?php

declare(strict_types=1);

namespace Domain\ChartOfAccounts\ValueObject;

use Domain\Shared\Exception\InvalidArgumentException;

enum AccountType: string
{
    case ASSET = 'asset';
    case LIABILITY = 'liability';
    case EQUITY = 'equity';
    case REVENUE = 'revenue';
    case EXPENSE = 'expense';

    public function normalBalance(): NormalBalance
    {
        return match ($this) {
            self::ASSET, self::EXPENSE => NormalBalance::DEBIT,
            self::LIABILITY, self::EQUITY, self::REVENUE => NormalBalance::CREDIT,
        };
    }

    public static function fromCodeRange(int $code): self
    {
        return match (true) {
            $code >= 1000 && $code <= 1999 => self::ASSET,
            $code >= 2000 && $code <= 2999 => self::LIABILITY,
            $code >= 3000 && $code <= 3999 => self::EQUITY,
            $code >= 4000 && $code <= 4999 => self::REVENUE,
            $code >= 5000 && $code <= 5999 => self::EXPENSE,
            default => throw new InvalidArgumentException(
                sprintf('Invalid account code range: %d', $code)
            ),
        };
    }
}
