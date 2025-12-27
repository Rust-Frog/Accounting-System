<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use Domain\Transaction\Service\EdgeCaseDetector\TimingAnomalyDetector;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TimingAnomalyDetectorTest extends TestCase
{
    private TimingAnomalyDetector $detector;
    private EdgeCaseThresholds $thresholds;

    protected function setUp(): void
    {
        $this->detector = new TimingAnomalyDetector();
        $this->thresholds = EdgeCaseThresholds::defaults();
    }

    public function test_detects_future_dated_transaction(): void
    {
        $today = new DateTimeImmutable('2024-12-27');
        $futureDate = new DateTimeImmutable('2025-01-15');

        $result = $this->detector->detect($futureDate, $today, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertTrue($result->requiresApproval());
        $this->assertSame('future_dated', $result->flags()[0]->type());
    }

    public function test_allows_today_date(): void
    {
        $today = new DateTimeImmutable('2024-12-27');

        $result = $this->detector->detect($today, $today, $this->thresholds);

        $this->assertFalse($result->hasFlags());
    }

    public function test_detects_backdated_beyond_threshold(): void
    {
        $today = new DateTimeImmutable('2024-12-27');
        $backdated = new DateTimeImmutable('2024-11-01'); // 56 days ago

        $result = $this->detector->detect($backdated, $today, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertTrue($result->requiresApproval());
        $this->assertSame('backdated', $result->flags()[0]->type());
    }

    public function test_allows_backdated_within_threshold(): void
    {
        $today = new DateTimeImmutable('2024-12-27');
        $recentPast = new DateTimeImmutable('2024-12-10'); // 17 days ago

        $result = $this->detector->detect($recentPast, $today, $this->thresholds);

        $this->assertFalse($result->hasFlags());
    }

    public function test_respects_custom_backdate_threshold(): void
    {
        $thresholds = EdgeCaseThresholds::fromDatabaseRow([
            'backdated_days_threshold' => 10,
            'large_transaction_threshold_cents' => 1_000_000,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 1,
            'require_approval_equity_adjustment' => 1,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 0,
            'flag_period_end_entries' => 0,
            'dormant_account_days_threshold' => 90,
        ]);

        $today = new DateTimeImmutable('2024-12-27');
        $fifteenDaysAgo = new DateTimeImmutable('2024-12-12');

        $result = $this->detector->detect($fifteenDaysAgo, $today, $thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('backdated', $result->flags()[0]->type());
    }

    public function test_detects_period_end_when_enabled(): void
    {
        $thresholds = EdgeCaseThresholds::fromDatabaseRow([
            'backdated_days_threshold' => 30,
            'large_transaction_threshold_cents' => 1_000_000,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 1,
            'require_approval_equity_adjustment' => 1,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 0,
            'flag_period_end_entries' => 1,
            'dormant_account_days_threshold' => 90,
        ]);

        $yearEnd = new DateTimeImmutable('2024-12-31');

        $result = $this->detector->detect($yearEnd, $yearEnd, $thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('period_end', $result->flags()[0]->type());
        $this->assertFalse($result->requiresApproval());
    }
}
