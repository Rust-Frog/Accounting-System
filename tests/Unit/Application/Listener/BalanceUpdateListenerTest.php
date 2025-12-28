<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Listener;

use Application\Listener\BalanceUpdateListener;
use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\ChartOfAccounts\ValueObject\AccountCode;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Ledger\Entity\AccountBalance;
use Domain\Ledger\Repository\LedgerRepositoryInterface;
use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;
use Domain\Transaction\Entity\Transaction;
use Domain\Transaction\Entity\TransactionLine;
use Domain\Transaction\Event\TransactionPosted;
use Domain\Transaction\Event\TransactionVoided;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\ValueObject\LineType;
use Domain\Transaction\ValueObject\TransactionId;
use Domain\Transaction\ValueObject\TransactionStatus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class BalanceUpdateListenerTest extends TestCase
{
    private BalanceUpdateListener $listener;
    private MockObject&TransactionRepositoryInterface $transactionRepo;
    private MockObject&LedgerRepositoryInterface $ledgerRepo;
    private MockObject&AccountRepositoryInterface $accountRepo;

    protected function setUp(): void
    {
        $this->transactionRepo = $this->createMock(TransactionRepositoryInterface::class);
        $this->ledgerRepo = $this->createMock(LedgerRepositoryInterface::class);
        $this->accountRepo = $this->createMock(AccountRepositoryInterface::class);

        $this->listener = new BalanceUpdateListener(
            $this->transactionRepo,
            $this->ledgerRepo,
            $this->accountRepo
        );
    }

    public function test_creates_account_balance_on_first_transaction(): void
    {
        $companyId = CompanyId::generate();
        $transactionId = TransactionId::generate();
        $accountId = AccountId::generate();
        $userId = UserId::generate();

        // Create real account
        $account = Account::create(
            AccountCode::fromInt(1000),
            'Cash',
            $companyId
        );

        // Create real transaction line
        $line = TransactionLine::create(
            $accountId,
            LineType::DEBIT,
            Money::fromCents(50000, Currency::USD),
            'Test line'
        );

        // Create real transaction using reconstitute
        $transaction = Transaction::reconstitute(
            id: $transactionId,
            companyId: $companyId,
            transactionDate: new \DateTimeImmutable(),
            description: 'Test transaction',
            createdBy: $userId,
            createdAt: new \DateTimeImmutable(),
            status: TransactionStatus::POSTED,
            referenceNumber: null,
            lines: [$line]
        );

        // Event
        $event = new TransactionPosted(
            $transactionId->toString(),
            $companyId->toString(),
            $userId->toString(),
            new \DateTimeImmutable()
        );

        // Setup expectations
        $this->transactionRepo
            ->expects($this->once())
            ->method('findById')
            ->with($transactionId)
            ->willReturn($transaction);

        $this->accountRepo
            ->expects($this->once())
            ->method('findById')
            ->with($accountId)
            ->willReturn($account);

        $this->ledgerRepo
            ->expects($this->once())
            ->method('getAccountBalance')
            ->willReturn(null); // No existing balance

        $this->ledgerRepo
            ->expects($this->once())
            ->method('saveBalance')
            ->with($this->isInstanceOf(AccountBalance::class));

        // Execute
        ($this->listener)($event);
    }

    public function test_ignores_non_transaction_events(): void
    {
        // Create a non-transaction event
        $event = new class implements \Domain\Shared\Event\DomainEvent {
            public function eventName(): string { return 'other.event'; }
            public function occurredOn(): \DateTimeImmutable { return new \DateTimeImmutable(); }
            public function toArray(): array { return []; }
        };

        $this->transactionRepo
            ->expects($this->never())
            ->method('findById');

        ($this->listener)($event);
    }

    public function test_skips_if_transaction_not_found(): void
    {
        $transactionId = TransactionId::generate();
        $companyId = CompanyId::generate();

        $event = new TransactionPosted(
            $transactionId->toString(),
            $companyId->toString(),
            'user-123',
            new \DateTimeImmutable()
        );

        $this->transactionRepo
            ->method('findById')
            ->willReturn(null);

        // Should not try to save any balance
        $this->ledgerRepo
            ->expects($this->never())
            ->method('saveBalance');

        // Execute - should not throw
        ($this->listener)($event);
    }

    public function test_skips_if_account_not_found(): void
    {
        $companyId = CompanyId::generate();
        $transactionId = TransactionId::generate();
        $accountId = AccountId::generate();
        $userId = UserId::generate();

        $line = TransactionLine::create(
            $accountId,
            LineType::DEBIT,
            Money::fromCents(50000, Currency::USD),
            'Test line'
        );

        $transaction = Transaction::reconstitute(
            id: $transactionId,
            companyId: $companyId,
            transactionDate: new \DateTimeImmutable(),
            description: 'Test transaction',
            createdBy: $userId,
            createdAt: new \DateTimeImmutable(),
            status: TransactionStatus::POSTED,
            referenceNumber: null,
            lines: [$line]
        );

        $event = new TransactionPosted(
            $transactionId->toString(),
            $companyId->toString(),
            $userId->toString(),
            new \DateTimeImmutable()
        );

        $this->transactionRepo
            ->method('findById')
            ->willReturn($transaction);

        $this->accountRepo
            ->method('findById')
            ->willReturn(null); // Account not found

        // Should not save balance
        $this->ledgerRepo
            ->expects($this->never())
            ->method('saveBalance');

        ($this->listener)($event);
    }
}
