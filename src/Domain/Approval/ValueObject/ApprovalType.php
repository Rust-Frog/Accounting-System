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

    // Edge case approval types
    case CONTRA_ENTRY = 'contra_entry';
    case ASSET_WRITEDOWN = 'asset_writedown';
    case FUTURE_DATED = 'future_dated';
    case EDGE_CASE = 'edge_case'; // Generic for multiple flags

    /**
     * Get default priority for this approval type.
     * 1 = Critical, 5 = Lowest
     */
    public function getDefaultPriority(): int
    {
        return match ($this) {
            self::VOID_TRANSACTION => 1,
            self::NEGATIVE_EQUITY, self::ASSET_WRITEDOWN => 2,
            self::HIGH_VALUE => 2,
            self::BACKDATED_TRANSACTION, self::FUTURE_DATED => 3,
            self::TRANSACTION, self::CONTRA_ENTRY, self::EDGE_CASE => 3,
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
            self::NEGATIVE_EQUITY, self::HIGH_VALUE, self::ASSET_WRITEDOWN => 24,
            self::TRANSACTION, self::BACKDATED_TRANSACTION, self::FUTURE_DATED => 48,
            self::CONTRA_ENTRY, self::EDGE_CASE => 48,
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
