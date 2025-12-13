<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\ValueObject;

use Domain\Transaction\ValueObject\LineType;
use PHPUnit\Framework\TestCase;

final class LineTypeTest extends TestCase
{
    public function test_has_debit_type(): void
    {
        $type = LineType::DEBIT;
        $this->assertEquals('debit', $type->value);
    }

    public function test_has_credit_type(): void
    {
        $type = LineType::CREDIT;
        $this->assertEquals('credit', $type->value);
    }

    public function test_is_debit_returns_true_for_debit(): void
    {
        $this->assertTrue(LineType::DEBIT->isDebit());
        $this->assertFalse(LineType::CREDIT->isDebit());
    }

    public function test_is_credit_returns_true_for_credit(): void
    {
        $this->assertTrue(LineType::CREDIT->isCredit());
        $this->assertFalse(LineType::DEBIT->isCredit());
    }

    public function test_opposite_returns_correct_type(): void
    {
        $this->assertEquals(LineType::CREDIT, LineType::DEBIT->opposite());
        $this->assertEquals(LineType::DEBIT, LineType::CREDIT->opposite());
    }
}
