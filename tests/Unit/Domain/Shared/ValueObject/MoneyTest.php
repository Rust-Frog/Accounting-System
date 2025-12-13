<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObject;

use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_creates_money_from_float(): void
    {
        $money = Money::fromFloat(100.50, Currency::PHP);

        $this->assertEquals(100.50, $money->amount());
        $this->assertEquals(Currency::PHP, $money->currency());
    }

    public function test_creates_money_from_cents(): void
    {
        $money = Money::fromCents(10050, Currency::PHP);

        $this->assertEquals(100.50, $money->amount());
        $this->assertEquals(10050, $money->cents());
    }

    public function test_creates_zero_money(): void
    {
        $money = Money::zero(Currency::PHP);

        $this->assertEquals(0.00, $money->amount());
        $this->assertTrue($money->isZero());
    }

    public function test_adds_two_money_objects(): void
    {
        $m1 = Money::fromFloat(100.00, Currency::PHP);
        $m2 = Money::fromFloat(50.00, Currency::PHP);

        $result = $m1->add($m2);

        $this->assertEquals(150.00, $result->amount());
    }

    public function test_subtracts_two_money_objects(): void
    {
        $m1 = Money::fromFloat(100.00, Currency::PHP);
        $m2 = Money::fromFloat(30.00, Currency::PHP);

        $result = $m1->subtract($m2);

        $this->assertEquals(70.00, $result->amount());
    }

    public function test_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromFloat(-10.00, Currency::PHP);
    }

    public function test_is_zero_returns_true_for_zero_amount(): void
    {
        $money = Money::fromFloat(0.00, Currency::PHP);
        $this->assertTrue($money->isZero());
    }

    public function test_is_zero_returns_false_for_non_zero_amount(): void
    {
        $money = Money::fromFloat(0.01, Currency::PHP);
        $this->assertFalse($money->isZero());
    }

    public function test_is_positive_returns_true_for_positive_amount(): void
    {
        $money = Money::fromFloat(100.00, Currency::PHP);
        $this->assertTrue($money->isPositive());
    }

    public function test_is_positive_returns_false_for_zero(): void
    {
        $money = Money::fromFloat(0.00, Currency::PHP);
        $this->assertFalse($money->isPositive());
    }

    public function test_equals_returns_true_for_same_amount_and_currency(): void
    {
        $m1 = Money::fromFloat(100.00, Currency::PHP);
        $m2 = Money::fromFloat(100.00, Currency::PHP);

        $this->assertTrue($m1->equals($m2));
    }

    public function test_equals_returns_false_for_different_amount(): void
    {
        $m1 = Money::fromFloat(100.00, Currency::PHP);
        $m2 = Money::fromFloat(200.00, Currency::PHP);

        $this->assertFalse($m1->equals($m2));
    }

    public function test_equals_returns_false_for_different_currency(): void
    {
        $m1 = Money::fromFloat(100.00, Currency::PHP);
        $m2 = Money::fromFloat(100.00, Currency::USD);

        $this->assertFalse($m1->equals($m2));
    }

    public function test_cannot_add_different_currencies(): void
    {
        $m1 = Money::fromFloat(100.00, Currency::PHP);
        $m2 = Money::fromFloat(50.00, Currency::USD);

        $this->expectException(InvalidArgumentException::class);
        $m1->add($m2);
    }

    public function test_cannot_subtract_different_currencies(): void
    {
        $m1 = Money::fromFloat(100.00, Currency::PHP);
        $m2 = Money::fromFloat(50.00, Currency::USD);

        $this->expectException(InvalidArgumentException::class);
        $m1->subtract($m2);
    }

    public function test_multiply_returns_correct_result(): void
    {
        $money = Money::fromFloat(100.00, Currency::PHP);

        $result = $money->multiply(2.5);

        $this->assertEquals(250.00, $result->amount());
    }

    public function test_handles_floating_point_precision(): void
    {
        $m1 = Money::fromFloat(0.10, Currency::PHP);
        $m2 = Money::fromFloat(0.20, Currency::PHP);

        $result = $m1->add($m2);

        $this->assertEquals(0.30, $result->amount());
    }

    public function test_subtract_cannot_result_in_negative(): void
    {
        $m1 = Money::fromFloat(50.00, Currency::PHP);
        $m2 = Money::fromFloat(100.00, Currency::PHP);

        $this->expectException(InvalidArgumentException::class);
        $m1->subtract($m2);
    }
}
