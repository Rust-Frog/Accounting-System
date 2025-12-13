<?php

declare(strict_types=1);

namespace Domain\Transaction\ValueObject;

enum LineType: string
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

    public function opposite(): self
    {
        return match ($this) {
            self::DEBIT => self::CREDIT,
            self::CREDIT => self::DEBIT,
        };
    }
}
