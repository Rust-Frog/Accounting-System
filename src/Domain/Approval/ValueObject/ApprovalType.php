<?php

declare(strict_types=1);

namespace Domain\Approval\ValueObject;

enum ApprovalType: string
{
    case TRANSACTION = 'transaction';
    case NEGATIVE_EQUITY = 'negative_equity';
    case HIGH_VALUE = 'high_value';
    case USER_REGISTRATION = 'user_registration';
    case ACCOUNT_DEACTIVATION = 'account_deactivation';
    case VOID_TRANSACTION = 'void_transaction';
    case BACKDATED_TRANSACTION = 'backdated_transaction';
    case TRANSACTION_POSTING = 'transaction_posting';
    case TRANSACTION_APPROVAL = 'transaction_approval'; // Legacy alias
    case PERIOD_CLOSE = 'period_close';

    /**
     * Get default priority for this approval type.
     * 1 = Critical, 5 = Lowest
     */
    public function getDefaultPriority(): int
    {
        return match ($this) {
            self::VOID_TRANSACTION => 1,
            self::NEGATIVE_EQUITY => 2,
            self::HIGH_VALUE => 2,
            self::BACKDATED_TRANSACTION => 3,
            self::TRANSACTION => 3,
            self::TRANSACTION_POSTING, self::TRANSACTION_APPROVAL => 3,
            self::USER_REGISTRATION => 4,
            self::ACCOUNT_DEACTIVATION => 4,
            self::PERIOD_CLOSE => 2,
        };
    }

    /**
     * Get default expiration hours for this approval type.
     */
    public function getDefaultExpirationHours(): int
    {
        return match ($this) {
            self::VOID_TRANSACTION => 4,
            self::NEGATIVE_EQUITY, self::HIGH_VALUE => 24,
            self::TRANSACTION, self::BACKDATED_TRANSACTION => 48,
            self::TRANSACTION_POSTING, self::TRANSACTION_APPROVAL => 48,
            self::USER_REGISTRATION, self::ACCOUNT_DEACTIVATION => 72,
            self::PERIOD_CLOSE => 72,
        };
    }

    public function isTransactionPosting(): bool
    {
        return $this === self::TRANSACTION_POSTING;
    }
}
