<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service;

use DateTimeImmutable;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;
use Domain\Transaction\Entity\Transaction;
use Domain\Transaction\Service\TransactionValidator;
use Domain\Transaction\ValueObject\LineType;
use PHPUnit\Framework\TestCase;

final class TransactionValidatorTest extends TestCase
{
    private TransactionValidator $validator;
    private CompanyId $companyId;
    private UserId $createdBy;

    protected function setUp(): void
    {
        $this->validator = new TransactionValidator();
        $this->companyId = CompanyId::generate();
        $this->createdBy = UserId::generate();
    }

    public function test_validates_balanced_transaction(): void
    {
        $transaction = $this->createTransaction();
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(10000, Currency::PHP));

        $result = $this->validator->validate($transaction);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors());
    }

    public function test_rejects_transaction_with_less_than_two_lines(): void
    {
        $transaction = $this->createTransaction();
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));

        $result = $this->validator->validate($transaction);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('Transaction must have at least 2 lines'));
    }

    public function test_rejects_transaction_without_lines(): void
    {
        $transaction = $this->createTransaction();

        $result = $this->validator->validate($transaction);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('Transaction must have at least 2 lines'));
    }

    public function test_rejects_unbalanced_transaction(): void
    {
        $transaction = $this->createTransaction();
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(5000, Currency::PHP));

        $result = $this->validator->validate($transaction);

        $this->assertFalse($result->isValid());
        $this->assertCount(1, array_filter($result->errors(), fn($e) => str_contains($e, 'not balanced')));
    }

    public function test_rejects_transaction_without_debit_lines(): void
    {
        $transaction = $this->createTransaction();
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(10000, Currency::PHP));

        $result = $this->validator->validate($transaction);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('Transaction must have at least one debit and one credit'));
    }

    public function test_rejects_transaction_without_credit_lines(): void
    {
        $transaction = $this->createTransaction();
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));

        $result = $this->validator->validate($transaction);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('Transaction must have at least one debit and one credit'));
    }

    public function test_validates_complex_balanced_transaction(): void
    {
        $transaction = $this->createTransaction();
        // Multiple debits and credits that balance
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(5000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(3000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(2000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(7000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(3000, Currency::PHP));

        $result = $this->validator->validate($transaction);

        $this->assertTrue($result->isValid());
    }

    public function test_returns_multiple_errors(): void
    {
        $transaction = $this->createTransaction();
        // Only one debit line - fails on minimum lines AND no credit line
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));

        $result = $this->validator->validate($transaction);

        $this->assertFalse($result->isValid());
        $this->assertGreaterThanOrEqual(2, $result->errorCount());
    }

    private function createTransaction(): Transaction
    {
        return Transaction::create(
            companyId: $this->companyId,
            transactionDate: new DateTimeImmutable('2024-01-15'),
            description: 'Test transaction',
            createdBy: $this->createdBy,
        );
    }
}
