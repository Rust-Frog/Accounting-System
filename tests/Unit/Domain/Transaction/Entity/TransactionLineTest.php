<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Entity;

use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;
use Domain\Transaction\Entity\TransactionLine;
use Domain\Transaction\ValueObject\LineType;
use Domain\Transaction\ValueObject\TransactionLineId;
use PHPUnit\Framework\TestCase;

final class TransactionLineTest extends TestCase
{
    public function test_creates_debit_line(): void
    {
        $accountId = AccountId::generate();
        $amount = Money::fromCents(10000, Currency::PHP);

        $line = TransactionLine::create(
            accountId: $accountId,
            lineType: LineType::DEBIT,
            amount: $amount,
        );

        $this->assertInstanceOf(TransactionLineId::class, $line->id());
        $this->assertTrue($accountId->equals($line->accountId()));
        $this->assertEquals(LineType::DEBIT, $line->lineType());
        $this->assertTrue($amount->equals($line->amount()));
    }

    public function test_creates_credit_line(): void
    {
        $accountId = AccountId::generate();
        $amount = Money::fromCents(10000, Currency::PHP);

        $line = TransactionLine::create(
            accountId: $accountId,
            lineType: LineType::CREDIT,
            amount: $amount,
        );

        $this->assertEquals(LineType::CREDIT, $line->lineType());
    }

    public function test_is_debit_returns_true_for_debit_line(): void
    {
        $line = TransactionLine::create(
            accountId: AccountId::generate(),
            lineType: LineType::DEBIT,
            amount: Money::fromCents(10000, Currency::PHP),
        );

        $this->assertTrue($line->isDebit());
        $this->assertFalse($line->isCredit());
    }

    public function test_is_credit_returns_true_for_credit_line(): void
    {
        $line = TransactionLine::create(
            accountId: AccountId::generate(),
            lineType: LineType::CREDIT,
            amount: Money::fromCents(10000, Currency::PHP),
        );

        $this->assertTrue($line->isCredit());
        $this->assertFalse($line->isDebit());
    }

    public function test_can_set_description(): void
    {
        $line = TransactionLine::create(
            accountId: AccountId::generate(),
            lineType: LineType::DEBIT,
            amount: Money::fromCents(10000, Currency::PHP),
            description: 'Cash received',
        );

        $this->assertEquals('Cash received', $line->description());
    }

    public function test_description_is_null_by_default(): void
    {
        $line = TransactionLine::create(
            accountId: AccountId::generate(),
            lineType: LineType::DEBIT,
            amount: Money::fromCents(10000, Currency::PHP),
        );

        $this->assertNull($line->description());
    }

    public function test_equals_returns_true_for_same_id(): void
    {
        $id = TransactionLineId::generate();
        $line1 = TransactionLine::reconstitute(
            id: $id,
            accountId: AccountId::generate(),
            lineType: LineType::DEBIT,
            amount: Money::fromCents(10000, Currency::PHP),
            description: null,
        );
        $line2 = TransactionLine::reconstitute(
            id: $id,
            accountId: AccountId::generate(),
            lineType: LineType::CREDIT,
            amount: Money::fromCents(5000, Currency::PHP),
            description: null,
        );

        $this->assertTrue($line1->equals($line2));
    }

    public function test_equals_returns_false_for_different_id(): void
    {
        $line1 = TransactionLine::create(
            accountId: AccountId::generate(),
            lineType: LineType::DEBIT,
            amount: Money::fromCents(10000, Currency::PHP),
        );
        $line2 = TransactionLine::create(
            accountId: AccountId::generate(),
            lineType: LineType::DEBIT,
            amount: Money::fromCents(10000, Currency::PHP),
        );

        $this->assertFalse($line1->equals($line2));
    }
}
