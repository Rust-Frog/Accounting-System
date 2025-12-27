<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use DateTimeImmutable;
use Domain\Transaction\Service\EdgeCaseDetector\PeriodEndDetector;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;
use PHPUnit\Framework\TestCase;

final class PeriodEndDetectorTest extends TestCase
{
    private PeriodEndDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new PeriodEndDetector();
    }

    public function test_detects_month_end_transaction(): void
    {
        $thresholds = $this->createThresholdsWithPeriodEndEnabled();

        // December 31st - last day of month
        $date = new DateTimeImmutable('2025-12-31');

        $result = $this->detector->detect($date, $thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('period_end', $result->flags()[0]->type());
        $this->assertStringContainsString('year', $result->flags()[0]->description());
    }

    public function test_detects_quarter_end_transaction(): void
    {
        $thresholds = $this->createThresholdsWithPeriodEndEnabled();

        // March 30th - near end of Q1
        $date = new DateTimeImmutable('2025-03-30');

        $result = $this->detector->detect($date, $thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('period_end', $result->flags()[0]->type());
        $this->assertStringContainsString('quarter', $result->flags()[0]->description());
    }

    public function test_detects_last_days_of_month(): void
    {
        $thresholds = $this->createThresholdsWithPeriodEndEnabled();

        // July 29th - 3rd to last day of July (31 days)
        $date = new DateTimeImmutable('2025-07-29');

        $result = $this->detector->detect($date, $thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('period_end', $result->flags()[0]->type());
    }

    public function test_no_flag_for_mid_month_transaction(): void
    {
        $thresholds = $this->createThresholdsWithPeriodEndEnabled();

        // July 15th - middle of month
        $date = new DateTimeImmutable('2025-07-15');

        $result = $this->detector->detect($date, $thresholds);

        $this->assertFalse($result->hasFlags());
    }

    public function test_respects_disabled_period_end_flag(): void
    {
        $thresholds = EdgeCaseThresholds::defaults(); // flagPeriodEndEntries is false by default

        // December 31st - would normally flag
        $date = new DateTimeImmutable('2025-12-31');

        $result = $this->detector->detect($date, $thresholds);

        $this->assertFalse($result->hasFlags());
    }

    public function test_flags_year_end_with_highest_priority(): void
    {
        $thresholds = $this->createThresholdsWithPeriodEndEnabled();

        // December 31st - year end (also quarter and month end)
        $date = new DateTimeImmutable('2025-12-31');

        $result = $this->detector->detect($date, $thresholds);

        $this->assertTrue($result->hasFlags());
        // Should flag as year-end, not just month-end
        $this->assertStringContainsString('year', $result->flags()[0]->description());
    }

    private function createThresholdsWithPeriodEndEnabled(): EdgeCaseThresholds
    {
        return EdgeCaseThresholds::fromDatabaseRow([
            'large_transaction_threshold_cents' => 1_000_000,
            'backdated_days_threshold' => 30,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 1,
            'require_approval_equity_adjustment' => 1,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 0,
            'flag_period_end_entries' => 1, // Enabled
            'dormant_account_days_threshold' => 90,
        ]);
    }
}
