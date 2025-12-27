<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\ValueObject;

use Domain\Transaction\ValueObject\EdgeCaseFlag;
use PHPUnit\Framework\TestCase;

final class EdgeCaseFlagTest extends TestCase
{
    public function test_creates_future_dated_flag(): void
    {
        $flag = EdgeCaseFlag::futureDated('2025-01-15', '2024-12-27');

        $this->assertSame('future_dated', $flag->type());
        $this->assertTrue($flag->requiresApproval());
        $this->assertStringContainsString('2025-01-15', $flag->description());
    }

    public function test_creates_backdated_flag(): void
    {
        $flag = EdgeCaseFlag::backdated('2024-11-01', 56);

        $this->assertSame('backdated', $flag->type());
        $this->assertTrue($flag->requiresApproval());
        $this->assertStringContainsString('56 days', $flag->description());
    }

    public function test_creates_large_amount_flag(): void
    {
        $flag = EdgeCaseFlag::largeAmount(5_000_000, 1_000_000);

        $this->assertSame('large_amount', $flag->type());
        $this->assertTrue($flag->requiresApproval());
        $this->assertStringContainsString('50,000.00', $flag->description());
    }

    public function test_creates_contra_revenue_flag(): void
    {
        $flag = EdgeCaseFlag::contraRevenue('Sales Revenue', 100_000);

        $this->assertSame('contra_revenue', $flag->type());
        $this->assertTrue($flag->requiresApproval());
        $this->assertStringContainsString('Sales Revenue', $flag->description());
    }

    public function test_creates_contra_expense_flag(): void
    {
        $flag = EdgeCaseFlag::contraExpense('Office Supplies', 50_000);

        $this->assertSame('contra_expense', $flag->type());
        $this->assertTrue($flag->requiresApproval());
    }

    public function test_creates_asset_writedown_flag(): void
    {
        $flag = EdgeCaseFlag::assetWritedown('Equipment', 200_000);

        $this->assertSame('asset_writedown', $flag->type());
        $this->assertTrue($flag->requiresApproval());
    }

    public function test_creates_equity_adjustment_flag(): void
    {
        $flag = EdgeCaseFlag::equityAdjustment('Retained Earnings', 300_000, 'debit');

        $this->assertSame('equity_adjustment', $flag->type());
        $this->assertTrue($flag->requiresApproval());
    }

    public function test_creates_negative_balance_flag(): void
    {
        $flag = EdgeCaseFlag::negativeBalance('Cash', 10_000, -5_000);

        $this->assertSame('negative_balance', $flag->type());
        $this->assertTrue($flag->requiresApproval());
        $this->assertStringContainsString('-50.00', $flag->description());
    }

    public function test_creates_round_number_flag_as_review_only(): void
    {
        $flag = EdgeCaseFlag::roundNumber(10_000_000);

        $this->assertSame('round_number', $flag->type());
        $this->assertFalse($flag->requiresApproval());
        $this->assertTrue($flag->isReviewOnly());
    }

    public function test_creates_period_end_flag_as_review_only(): void
    {
        $flag = EdgeCaseFlag::periodEnd('2024-12-31', 'year');

        $this->assertSame('period_end', $flag->type());
        $this->assertFalse($flag->requiresApproval());
        $this->assertTrue($flag->isReviewOnly());
    }

    public function test_serializes_to_array(): void
    {
        $flag = EdgeCaseFlag::largeAmount(5_000_000, 1_000_000);
        $array = $flag->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('requires_approval', $array);
        $this->assertArrayHasKey('context', $array);
    }
}
