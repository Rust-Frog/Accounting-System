<?php

declare(strict_types=1);

namespace Domain\ChartOfAccounts\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;

final readonly class AccountCreated implements DomainEvent
{
    public function __construct(
        private string $accountId,
        private string $accountCode,
        private string $accountName,
        private string $accountType,
        private string $companyId,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function eventName(): string
    {
        return 'account.created';
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'account_code' => $this->accountCode,
            'account_name' => $this->accountName,
            'account_type' => $this->accountType,
            'company_id' => $this->companyId,
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
