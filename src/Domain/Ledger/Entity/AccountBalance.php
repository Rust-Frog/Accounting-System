<?php

declare(strict_types=1);

namespace Domain\Ledger\Entity;

use DateTimeImmutable;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\ChartOfAccounts\ValueObject\AccountType;
use Domain\ChartOfAccounts\ValueObject\NormalBalance;
use Domain\Company\ValueObject\CompanyId;
use Domain\Ledger\Dto\AccountInitializationParams;
use Domain\Ledger\Event\AccountBalanceChanged;
use Domain\Ledger\ValueObject\AccountBalanceId;
use Domain\Ledger\ValueObject\BalanceChangeId;
use Domain\Ledger\ValueObject\BalanceMetrics;
use Domain\Shared\Event\DomainEvent;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;
use Domain\Transaction\ValueObject\LineType;
use Domain\Transaction\ValueObject\TransactionId;

/**
 * Entity representing the balance of a single account.
 * Tracks current balance, totals, and transaction history.
 */
final class AccountBalance
{
    /** @var array<DomainEvent> */
    private array $domainEvents = [];

    private function __construct(
        private readonly AccountBalanceId $id,
        private readonly AccountId $accountId,
        private readonly CompanyId $companyId,
        private readonly AccountType $accountType,
        private readonly NormalBalance $normalBalance,
        private readonly Currency $currency,
        private BalanceMetrics $metrics
    ) {
    }

    public static function initialize(AccountInitializationParams $params): self
    {
        return new self(
            id: AccountBalanceId::generate(),
            accountId: $params->accountId,
            companyId: $params->companyId,
            accountType: $params->accountType,
            normalBalance: $params->accountType->normalBalance(),
            currency: $params->currency,
            metrics: BalanceMetrics::initialize($params->openingBalanceCents)
        );
    }

    public static function reconstruct(
        AccountBalanceId $id,
        AccountId $accountId,
        CompanyId $companyId,
        AccountType $accountType,
        NormalBalance $normalBalance,
        Currency $currency,
        BalanceMetrics $metrics
    ): self {
        return new self(
            id: $id,
            accountId: $accountId,
            companyId: $companyId,
            accountType: $accountType,
            normalBalance: $normalBalance,
            currency: $currency,
            metrics: $metrics
        );
    }

    /**
     * Apply a balance change from a transaction line.
     * BR-LP-001: Balance change calculation based on normal balance.
     */
    public function applyChange(BalanceChange $change): void
    {
        $previousBalance = $this->metrics->currentBalanceCents();
        $now = new DateTimeImmutable();

        $debitAmount = $change->lineType() === LineType::DEBIT ? $change->amountCents() : 0;
        $creditAmount = $change->lineType() === LineType::CREDIT ? $change->amountCents() : 0;

        $this->metrics = $this->metrics->withUpdate(
            $change->changeCents(),
            $debitAmount,
            $creditAmount,
            $now
        );

        $this->recordEvent(new AccountBalanceChanged(
            accountId: $this->accountId->toString(),
            companyId: $this->companyId->toString(),
            accountType: $this->accountType->value,
            previousBalanceCents: $previousBalance,
            newBalanceCents: $this->metrics->currentBalanceCents(),
            changeCents: $change->changeCents(),
            transactionId: $change->transactionId()->toString(),
            isReversal: $change->isReversal(),
            occurredAt: $now,
        ));
    }

    /**
     * Reverse a previous balance change.
     * Creates opposite effect on balance.
     */
    public function reverseChange(BalanceChange $originalChange): BalanceChange
    {
        $reversalChange = BalanceChange::createReversal(
            accountId: $this->accountId,
            transactionId: $originalChange->transactionId(),
            lineType: $originalChange->lineType()->opposite(),
            amountCents: $originalChange->amountCents(),
            previousBalanceCents: $this->metrics->currentBalanceCents(),
            normalBalance: $this->normalBalance,
        );

        $this->applyChange($reversalChange);

        return $reversalChange;
    }

    /**
     * BR-LP-002: Check if balance can go negative.
     * Only Equity accounts can have negative balance (with approval).
     */
    public function wouldBeNegativeAfterChange(int $changeCents): bool
    {
        return ($this->metrics->currentBalanceCents() + $changeCents) < 0;
    }

    /**
     * BR-LP-002: Check if negative balance is allowed for this account type.
     */
    public function canHaveNegativeBalance(): bool
    {
        return $this->accountType === AccountType::EQUITY;
    }

    // Getters

    public function id(): AccountBalanceId
    {
        return $this->id;
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function accountType(): AccountType
    {
        return $this->accountType;
    }

    public function normalBalance(): NormalBalance
    {
        return $this->normalBalance;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function currentBalanceCents(): int
    {
        return $this->metrics->currentBalanceCents();
    }

    public function currentBalance(): Money
    {
        $cents = $this->metrics->currentBalanceCents();
        if ($cents < 0) {
            return Money::fromCents(0, $this->currency);
        }
        return Money::fromCents($cents, $this->currency);
    }

    public function openingBalanceCents(): int
    {
        return $this->metrics->openingBalanceCents();
    }

    public function totalDebitsCents(): int
    {
        return $this->metrics->totalDebitsCents();
    }

    public function totalCreditsCents(): int
    {
        return $this->metrics->totalCreditsCents();
    }

    public function transactionCount(): int
    {
        return $this->metrics->transactionCount();
    }

    public function lastTransactionAt(): ?DateTimeImmutable
    {
        return $this->metrics->lastTransactionAt();
    }

    public function version(): int
    {
        return $this->metrics->version();
    }

    public function metrics(): BalanceMetrics
    {
        return $this->metrics;
    }

    private function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * @return array<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}

