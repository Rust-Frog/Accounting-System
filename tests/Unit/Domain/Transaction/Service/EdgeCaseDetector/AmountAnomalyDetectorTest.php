<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use Domain\Transaction\Service\EdgeCaseDetector\AmountAnomalyDetector;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;
use PHPUnit\Framework\TestCase;

final class AmountAnomalyDetectorTest extends TestCase
{
    private AmountAnomalyDetector $detector;
    private EdgeCaseThresholds $thresholds;

    protected function setUp(): void
    {
        $this->detector = new AmountAnomalyDetector();
        $this->thresholds = EdgeCaseThresholds::defaults(); // 1,000,000 cents = $10,000
    }

    public function test_detects_large_transaction(): void
    {
        // Total amount = $15,000 (exceeds $10,000 threshold)
        $lines = [
            ['debit_cents' => 1_500_000, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 1_500_000],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertTrue($result->requiresApproval());
        $this->assertSame('large_amount', $result->flags()[0]->type());
    }

    public function test_allows_transaction_below_threshold(): void
    {
        // Total amount = $5,000 (below $10,000 threshold)
        $lines = [
            ['debit_cents' => 500_000, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 500_000],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        // Should not flag as large_amount
        $largeFlagsCount = count(array_filter(
            $result->flags(),
            fn($f) => $f->type() === 'large_amount'
        ));
        $this->assertSame(0, $largeFlagsCount);
    }

    public function test_detects_just_below_threshold(): void
    {
        // Total amount = $9,999 (90-99% of threshold)
        $lines = [
            ['debit_cents' => 999_900, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 999_900],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $this->assertTrue($result->hasFlags());
        $this->assertSame('below_threshold', $result->flags()[0]->type());
        $this->assertFalse($result->requiresApproval()); // Review only
    }

    public function test_detects_round_number_when_enabled(): void
    {
        $thresholds = EdgeCaseThresholds::fromDatabaseRow([
            'large_transaction_threshold_cents' => 1_000_000,
            'backdated_days_threshold' => 30,
            'future_dated_allowed' => 1,
            'require_approval_contra_entry' => 1,
            'require_approval_equity_adjustment' => 1,
            'require_approval_negative_balance' => 1,
            'flag_round_numbers' => 1,
            'flag_period_end_entries' => 0,
            'dormant_account_days_threshold' => 90,
        ]);

        // Exactly $10,000.00 - suspiciously round
        $lines = [
            ['debit_cents' => 1_000_000, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 1_000_000],
        ];

        $result = $this->detector->detect($lines, $thresholds);

        $roundFlags = array_filter($result->flags(), fn($f) => $f->type() === 'round_number');
        $this->assertNotEmpty($roundFlags);
    }

    public function test_ignores_round_numbers_when_disabled(): void
    {
        // Default thresholds have flag_round_numbers = false
        $lines = [
            ['debit_cents' => 1_000_000, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 1_000_000],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $roundFlags = array_filter($result->flags(), fn($f) => $f->type() === 'round_number');
        $this->assertEmpty($roundFlags);
    }

    public function test_calculates_total_from_debits(): void
    {
        // Multiple debit lines totaling $12,000
        $lines = [
            ['debit_cents' => 600_000, 'credit_cents' => 0],
            ['debit_cents' => 600_000, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 1_200_000],
        ];

        $result = $this->detector->detect($lines, $this->thresholds);

        $this->assertTrue($result->requiresApproval());
    }
}
