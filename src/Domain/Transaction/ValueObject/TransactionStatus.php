<?php

declare(strict_types=1);

namespace Domain\Transaction\ValueObject;

enum TransactionStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case VOIDED = 'voided';

    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    public function isPosted(): bool
    {
        return $this === self::POSTED;
    }

    public function isVoided(): bool
    {
        return $this === self::VOIDED;
    }

    public function isTerminal(): bool
    {
        return $this === self::VOIDED;
    }

    public function canEdit(): bool
    {
        return $this === self::DRAFT;
    }
}
