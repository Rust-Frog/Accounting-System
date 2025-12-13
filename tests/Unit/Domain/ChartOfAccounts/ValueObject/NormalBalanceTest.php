<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ChartOfAccounts\ValueObject;

use Domain\ChartOfAccounts\ValueObject\NormalBalance;
use PHPUnit\Framework\TestCase;

final class NormalBalanceTest extends TestCase
{
    public function test_has_debit_balance(): void
    {
        $balance = NormalBalance::DEBIT;
        $this->assertEquals('debit', $balance->value);
    }

    public function test_has_credit_balance(): void
    {
        $balance = NormalBalance::CREDIT;
        $this->assertEquals('credit', $balance->value);
    }

    public function test_is_debit_returns_true_for_debit(): void
    {
        $this->assertTrue(NormalBalance::DEBIT->isDebit());
        $this->assertFalse(NormalBalance::CREDIT->isDebit());
    }

    public function test_is_credit_returns_true_for_credit(): void
    {
        $this->assertTrue(NormalBalance::CREDIT->isCredit());
        $this->assertFalse(NormalBalance::DEBIT->isCredit());
    }
}
