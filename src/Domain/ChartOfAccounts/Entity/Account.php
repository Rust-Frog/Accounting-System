<?php

declare(strict_types=1);

namespace Domain\ChartOfAccounts\Entity;

use DateTimeImmutable;
use Domain\ChartOfAccounts\Event\AccountCreated;
use Domain\ChartOfAccounts\ValueObject\AccountCode;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\ChartOfAccounts\ValueObject\AccountType;
use Domain\ChartOfAccounts\ValueObject\NormalBalance;
use Domain\Company\ValueObject\CompanyId;
use Domain\Shared\Event\DomainEvent;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;

final class Account
{
    /** @var array<DomainEvent> */
    private array $domainEvents = [];

    private function __construct(
        private readonly AccountId $id,
        private readonly AccountCode $code,
        private string $name,
        private readonly CompanyId $companyId,
        private ?string $description,
        private readonly ?AccountId $parentAccountId,
        private bool $isActive,
        private Money $balance,
    ) {
    }

    public static function create(
        AccountCode $accountCode,
        string $accountName,
        CompanyId $companyId,
        ?string $description = null,
        ?AccountId $parentAccountId = null,
    ): self {
        $id = AccountId::generate();
        $balance = Money::fromCents(0, Currency::PHP);

        $account = new self(
            id: $id,
            code: $accountCode,
            name: $accountName,
            companyId: $companyId,
            description: $description,
            parentAccountId: $parentAccountId,
            isActive: true,
            balance: $balance,
        );

        $account->recordEvent(new AccountCreated(
            accountId: $id->toString(),
            accountCode: $accountCode->toString(),
            accountName: $accountName,
            accountType: $accountCode->accountType()->value,
            companyId: $companyId->toString(),
            occurredAt: new DateTimeImmutable(),
        ));

        return $account;
    }

    public function id(): AccountId
    {
        return $this->id;
    }

    public function code(): AccountCode
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function parentAccountId(): ?AccountId
    {
        return $this->parentAccountId;
    }

    public function accountType(): AccountType
    {
        return $this->code->accountType();
    }

    public function normalBalance(): NormalBalance
    {
        return $this->accountType()->normalBalance();
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function balance(): Money
    {
        return $this->balance;
    }

    public function recordBalance(Money $balance): void
    {
        $this->balance = $balance;
    }

    public function deactivate(): void
    {
        if ($this->balance->cents() !== 0) {
            throw new BusinessRuleException('Cannot deactivate account with non-zero balance');
        }

        $this->isActive = false;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function updateDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Apply a debit to this account.
     * For debit-normal accounts (Assets, Expenses): increases balance
     * For credit-normal accounts (Liabilities, Equity, Revenue): decreases balance
     */
    public function applyDebit(Money $amount): void
    {
        if ($this->normalBalance()->value === 'debit') {
            $this->balance = Money::fromSignedCents(
                $this->balance->cents() + $amount->cents(),
                $this->balance->currency()
            );
        } else {
            $this->balance = Money::fromSignedCents(
                $this->balance->cents() - $amount->cents(),
                $this->balance->currency()
            );
        }
    }

    /**
     * Apply a credit to this account.
     * For credit-normal accounts (Liabilities, Equity, Revenue): increases balance
     * For debit-normal accounts (Assets, Expenses): decreases balance
     */
    public function applyCredit(Money $amount): void
    {
        if ($this->normalBalance()->value === 'credit') {
            $this->balance = Money::fromSignedCents(
                $this->balance->cents() + $amount->cents(),
                $this->balance->currency()
            );
        } else {
            $this->balance = Money::fromSignedCents(
                $this->balance->cents() - $amount->cents(),
                $this->balance->currency()
            );
        }
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
