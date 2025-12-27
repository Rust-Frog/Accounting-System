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

    /**
     * BR-TXN-005: Posted/voided transactions cannot be edited.
     */
    public function canEdit(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * BR-TXN-004: Only draft can be posted.
     */
    public function canPost(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * BR-TXN-004: Posted transactions can be voided.
     */
    public function canVoid(): bool
    {
        return $this === self::POSTED;
    }

    /**
     * BR-TXN-004: Valid state transitions.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::DRAFT => in_array($newStatus, [self::POSTED, self::VOIDED], true),
            self::POSTED => $newStatus === self::VOIDED,
            self::VOIDED => false, // Terminal state
        };
    }
}

