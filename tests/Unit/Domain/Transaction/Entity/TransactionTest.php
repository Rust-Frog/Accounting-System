<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Entity;

use DateTimeImmutable;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;
use Domain\Transaction\Entity\Transaction;
use Domain\Transaction\ValueObject\LineType;
use Domain\Transaction\ValueObject\TransactionId;
use Domain\Transaction\ValueObject\TransactionStatus;
use PHPUnit\Framework\TestCase;

final class TransactionTest extends TestCase
{
    private CompanyId $companyId;
    private UserId $createdBy;

    protected function setUp(): void
    {
        $this->companyId = CompanyId::generate();
        $this->createdBy = UserId::generate();
    }

    public function test_creates_transaction_with_required_fields(): void
    {
        $transactionDate = new DateTimeImmutable('2024-01-15');

        $transaction = Transaction::create(
            companyId: $this->companyId,
            transactionDate: $transactionDate,
            description: 'Cash sale',
            createdBy: $this->createdBy,
        );

        $this->assertInstanceOf(TransactionId::class, $transaction->id());
        $this->assertTrue($this->companyId->equals($transaction->companyId()));
        $this->assertEquals($transactionDate, $transaction->transactionDate());
        $this->assertEquals('Cash sale', $transaction->description());
        $this->assertTrue($this->createdBy->equals($transaction->createdBy()));
    }

    public function test_transaction_starts_as_draft(): void
    {
        $transaction = $this->createTransaction();

        $this->assertEquals(TransactionStatus::DRAFT, $transaction->status());
        $this->assertTrue($transaction->isDraft());
    }

    public function test_can_add_debit_line(): void
    {
        $transaction = $this->createTransaction();
        $accountId = AccountId::generate();
        $amount = Money::fromCents(10000, Currency::PHP);

        $transaction->addLine(
            accountId: $accountId,
            lineType: LineType::DEBIT,
            amount: $amount,
        );

        $lines = $transaction->lines();
        $this->assertCount(1, $lines);
        $this->assertTrue($lines[0]->isDebit());
        $this->assertTrue($amount->equals($lines[0]->amount()));
    }

    public function test_can_add_credit_line(): void
    {
        $transaction = $this->createTransaction();
        $accountId = AccountId::generate();
        $amount = Money::fromCents(10000, Currency::PHP);

        $transaction->addLine(
            accountId: $accountId,
            lineType: LineType::CREDIT,
            amount: $amount,
        );

        $lines = $transaction->lines();
        $this->assertCount(1, $lines);
        $this->assertTrue($lines[0]->isCredit());
    }

    public function test_can_add_line_with_description(): void
    {
        $transaction = $this->createTransaction();

        $transaction->addLine(
            accountId: AccountId::generate(),
            lineType: LineType::DEBIT,
            amount: Money::fromCents(10000, Currency::PHP),
            description: 'Cash received from customer',
        );

        $lines = $transaction->lines();
        $this->assertEquals('Cash received from customer', $lines[0]->description());
    }

    public function test_calculates_total_debits(): void
    {
        $transaction = $this->createTransaction();

        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(5000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(15000, Currency::PHP));

        $this->assertEquals(15000, $transaction->totalDebits()->cents());
    }

    public function test_calculates_total_credits(): void
    {
        $transaction = $this->createTransaction();

        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(15000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(5000, Currency::PHP));

        $this->assertEquals(15000, $transaction->totalCredits()->cents());
    }

    public function test_is_balanced_when_debits_equal_credits(): void
    {
        $transaction = $this->createTransaction();

        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(10000, Currency::PHP));

        $this->assertTrue($transaction->isBalanced());
    }

    public function test_is_not_balanced_when_debits_not_equal_credits(): void
    {
        $transaction = $this->createTransaction();

        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(5000, Currency::PHP));

        $this->assertFalse($transaction->isBalanced());
    }

    public function test_can_post_valid_transaction(): void
    {
        $transaction = $this->createBalancedTransaction();
        $postedBy = UserId::generate();

        $transaction->post($postedBy);

        $this->assertEquals(TransactionStatus::POSTED, $transaction->status());
        $this->assertTrue($transaction->isPosted());
        $this->assertNotNull($transaction->postedAt());
        $this->assertTrue($postedBy->equals($transaction->postedBy()));
    }

    public function test_cannot_post_unbalanced_transaction(): void
    {
        $transaction = $this->createTransaction();
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(5000, Currency::PHP));

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Transaction is not balanced');

        $transaction->post(UserId::generate());
    }

    public function test_cannot_post_transaction_without_minimum_lines(): void
    {
        $transaction = $this->createTransaction();
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Transaction must have at least 2 lines');

        $transaction->post(UserId::generate());
    }

    public function test_cannot_post_transaction_without_debit_and_credit(): void
    {
        $transaction = $this->createTransaction();
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Transaction must have at least one debit and one credit');

        $transaction->post(UserId::generate());
    }

    public function test_cannot_add_line_to_posted_transaction(): void
    {
        $transaction = $this->createBalancedTransaction();
        $transaction->post(UserId::generate());

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Posted transactions cannot be modified');

        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
    }

    public function test_can_void_posted_transaction(): void
    {
        $transaction = $this->createBalancedTransaction();
        $transaction->post(UserId::generate());
        $voidedBy = UserId::generate();

        $transaction->void('Duplicate entry', $voidedBy);

        $this->assertEquals(TransactionStatus::VOIDED, $transaction->status());
        $this->assertTrue($transaction->isVoided());
        $this->assertEquals('Duplicate entry', $transaction->voidReason());
        $this->assertNotNull($transaction->voidedAt());
        $this->assertTrue($voidedBy->equals($transaction->voidedBy()));
    }

    public function test_cannot_void_draft_transaction(): void
    {
        $transaction = $this->createBalancedTransaction();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Only posted transactions can be voided');

        $transaction->void('Test reason', UserId::generate());
    }

    public function test_cannot_post_voided_transaction(): void
    {
        $transaction = $this->createBalancedTransaction();
        $transaction->post(UserId::generate());
        $transaction->void('Duplicate entry', UserId::generate());

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Voided transactions cannot be modified');

        $transaction->post(UserId::generate());
    }

    public function test_cannot_add_line_to_voided_transaction(): void
    {
        $transaction = $this->createBalancedTransaction();
        $transaction->post(UserId::generate());
        $transaction->void('Duplicate entry', UserId::generate());

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Voided transactions cannot be modified');

        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
    }

    public function test_can_set_reference_number(): void
    {
        $transaction = Transaction::create(
            companyId: $this->companyId,
            transactionDate: new DateTimeImmutable(),
            description: 'Test',
            createdBy: $this->createdBy,
            referenceNumber: 'TXN-2024-001',
        );

        $this->assertEquals('TXN-2024-001', $transaction->referenceNumber());
    }

    public function test_reference_number_is_null_by_default(): void
    {
        $transaction = $this->createTransaction();

        $this->assertNull($transaction->referenceNumber());
    }

    public function test_records_domain_event_on_creation(): void
    {
        $transaction = $this->createTransaction();

        $events = $transaction->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertEquals('transaction.created', $events[0]->eventName());
    }

    public function test_records_domain_event_on_posting(): void
    {
        $transaction = $this->createBalancedTransaction();
        $transaction->releaseEvents(); // Clear creation event

        $transaction->post(UserId::generate());
        $events = $transaction->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertEquals('transaction.posted', $events[0]->eventName());
    }

    public function test_records_domain_event_on_voiding(): void
    {
        $transaction = $this->createBalancedTransaction();
        $transaction->post(UserId::generate());
        $transaction->releaseEvents(); // Clear previous events

        $transaction->void('Test reason', UserId::generate());
        $events = $transaction->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertEquals('transaction.voided', $events[0]->eventName());
    }

    public function test_returns_line_count(): void
    {
        $transaction = $this->createTransaction();
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(10000, Currency::PHP));

        $this->assertEquals(2, $transaction->lineCount());
    }

    public function test_has_debit_lines(): void
    {
        $transaction = $this->createTransaction();
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(10000, Currency::PHP));

        $this->assertTrue($transaction->hasDebitLines());
        $this->assertTrue($transaction->hasCreditLines());
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

    private function createBalancedTransaction(): Transaction
    {
        $transaction = $this->createTransaction();
        $transaction->addLine(AccountId::generate(), LineType::DEBIT, Money::fromCents(10000, Currency::PHP));
        $transaction->addLine(AccountId::generate(), LineType::CREDIT, Money::fromCents(10000, Currency::PHP));

        return $transaction;
    }
}
