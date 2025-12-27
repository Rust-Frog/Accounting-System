<?php

declare(strict_types=1);

namespace Domain\Transaction\ValueObject;

/**
 * Immutable value object holding company-specific edge case detection thresholds.
 * Used by EdgeCaseDetectionService to determine which rules to apply.
 */
final readonly class EdgeCaseThresholds
{
    private function __construct(
        private int $largeTransactionThresholdCents,
        private int $backdatedDaysThreshold,
        private bool $futureDatedAllowed,
        private bool $requireApprovalContraEntry,
        private bool $requireApprovalEquityAdjustment,
        private bool $requireApprovalNegativeBalance,
        private bool $flagRoundNumbers,
        private bool $flagPeriodEndEntries,
        private int $dormantAccountDaysThreshold,
    ) {
    }

    public static function defaults(): self
    {
        return new self(
            largeTransactionThresholdCents: 1_000_000, // $10,000.00
            backdatedDaysThreshold: 30,
            futureDatedAllowed: true,
            requireApprovalContraEntry: true,
            requireApprovalEquityAdjustment: true,
            requireApprovalNegativeBalance: true,
            flagRoundNumbers: false,
            flagPeriodEndEntries: false,
            dormantAccountDaysThreshold: 90,
        );
    }

    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            largeTransactionThresholdCents: (int) ($row['large_transaction_threshold_cents'] ?? 1_000_000),
            backdatedDaysThreshold: (int) ($row['backdated_days_threshold'] ?? 30),
            futureDatedAllowed: (bool) ($row['future_dated_allowed'] ?? true),
            requireApprovalContraEntry: (bool) ($row['require_approval_contra_entry'] ?? true),
            requireApprovalEquityAdjustment: (bool) ($row['require_approval_equity_adjustment'] ?? true),
            requireApprovalNegativeBalance: (bool) ($row['require_approval_negative_balance'] ?? true),
            flagRoundNumbers: (bool) ($row['flag_round_numbers'] ?? false),
            flagPeriodEndEntries: (bool) ($row['flag_period_end_entries'] ?? false),
            dormantAccountDaysThreshold: (int) ($row['dormant_account_days_threshold'] ?? 90),
        );
    }

    public function largeTransactionThresholdCents(): int
    {
        return $this->largeTransactionThresholdCents;
    }

    public function backdatedDaysThreshold(): int
    {
        return $this->backdatedDaysThreshold;
    }

    public function futureDatedAllowed(): bool
    {
        return $this->futureDatedAllowed;
    }

    public function requireApprovalContraEntry(): bool
    {
        return $this->requireApprovalContraEntry;
    }

    public function requireApprovalEquityAdjustment(): bool
    {
        return $this->requireApprovalEquityAdjustment;
    }

    public function requireApprovalNegativeBalance(): bool
    {
        return $this->requireApprovalNegativeBalance;
    }

    public function flagRoundNumbers(): bool
    {
        return $this->flagRoundNumbers;
    }

    public function flagPeriodEndEntries(): bool
    {
        return $this->flagPeriodEndEntries;
    }

    public function dormantAccountDaysThreshold(): int
    {
        return $this->dormantAccountDaysThreshold;
    }

    /**
     * Returns 90% of large transaction threshold for "just below threshold" detection.
     */
    public function belowThresholdFloorCents(): int
    {
        return (int) ($this->largeTransactionThresholdCents * 0.9);
    }
}
