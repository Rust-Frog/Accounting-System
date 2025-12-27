<?php

declare(strict_types=1);

namespace Domain\Approval\ValueObject;

enum ApprovalStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    /**
     * Check if this is a terminal (final) state.
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::REJECTED,
            self::EXPIRED,
            self::CANCELLED,
        ], true);
    }

    /**
     * BR-AW-003: Valid status transitions.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        if ($this->isFinal()) {
            return false;
        }

        return match ($this) {
            self::PENDING => in_array($newStatus, [
                self::APPROVED,
                self::REJECTED,
                self::EXPIRED,
                self::CANCELLED,
            ], true),
            default => false,
        };
    }
}
