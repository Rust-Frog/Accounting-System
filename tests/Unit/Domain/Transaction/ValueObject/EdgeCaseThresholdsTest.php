<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\ValueObject;

use Domain\Transaction\ValueObject\EdgeCaseThresholds;
use PHPUnit\Framework\TestCase;

final class EdgeCaseThresholdsTest extends TestCase
{
    public function test_creates_with_defaults(): void
    {
        $thresholds = EdgeCaseThresholds::defaults();

        $this->assertSame(1_000_000, $thresholds->largeTransactionThresholdCents());
        $this->assertSame(30, $thresholds->backdatedDaysThreshold());
        $this->assertTrue($thresholds->futureDatedAllowed());
        $this->assertTrue($thresholds->requireApprovalContraEntry());
        $this->assertTrue($thresholds->requireApprovalEquityAdjustment());
        $this->assertTrue($thresholds->requireApprovalNegativeBalance());
        $this->assertFalse($thresholds->flagRoundNumbers());
        $this->assertFalse($thresholds->flagPeriodEndEntries());
        $this->assertSame(90, $thresholds->dormantAccountDaysThreshold());
    }

    public function test_creates_from_database_row(): void
    {
        $row = [
            'large_transaction_threshold_cents' => 5_000_000,
            'backdated_days_threshold' => 60,
            'future_dated_allowed' => 0,
            'require_approval_contra_entry' => 1,
            'require_approval_equity_adjustment' => 0,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 1,
            'flag_period_end_entries' => 1,
            'dormant_account_days_threshold' => 180,
        ];

        $thresholds = EdgeCaseThresholds::fromDatabaseRow($row);

        $this->assertSame(5_000_000, $thresholds->largeTransactionThresholdCents());
        $this->assertSame(60, $thresholds->backdatedDaysThreshold());
        $this->assertFalse($thresholds->futureDatedAllowed());
        $this->assertTrue($thresholds->requireApprovalContraEntry());
        $this->assertFalse($thresholds->requireApprovalEquityAdjustment());
        $this->assertTrue($thresholds->requireApprovalNegativeBalance());
        $this->assertTrue($thresholds->flagRoundNumbers());
        $this->assertTrue($thresholds->flagPeriodEndEntries());
        $this->assertSame(180, $thresholds->dormantAccountDaysThreshold());
    }

    public function test_calculates_below_threshold_range(): void
    {
        $thresholds = EdgeCaseThresholds::defaults();

        // 90% of 1,000,000 = 900,000
        $this->assertSame(900_000, $thresholds->belowThresholdFloorCents());
    }
}
