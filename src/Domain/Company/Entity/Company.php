<?php

declare(strict_types=1);

namespace Domain\Company\Entity;

use DateTimeImmutable;
use Domain\Company\Event\CompanyActivated;
use Domain\Company\Event\CompanyCreated;
use Domain\Company\ValueObject\Address;
use Domain\Company\ValueObject\CompanyId;
use Domain\Company\ValueObject\CompanyStatus;
use Domain\Company\ValueObject\TaxIdentifier;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\DomainEvent;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\ValueObject\Currency;

final class Company
{
    /** @var array<DomainEvent> */
    private array $domainEvents = [];

    private function __construct(
        private readonly CompanyId $companyId,
        private string $companyName,
        private string $legalName,
        private TaxIdentifier $taxId,
        private Address $address,
        private Currency $currency,
        private CompanyStatus $status,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(
        string $companyName,
        string $legalName,
        TaxIdentifier $taxId,
        Address $address,
        Currency $currency
    ): self {
        $now = new DateTimeImmutable();

        $company = new self(
            companyId: CompanyId::generate(),
            companyName: $companyName,
            legalName: $legalName,
            taxId: $taxId,
            address: $address,
            currency: $currency,
            status: CompanyStatus::ACTIVE,
            createdAt: $now,
            updatedAt: $now
        );

        $company->recordEvent(new CompanyCreated($company->companyId, $company->companyName));

        return $company;
    }

    public function activate(UserId $activatedBy): void
    {
        if ($this->status !== CompanyStatus::PENDING) {
            throw new BusinessRuleException('Only pending companies can be activated');
        }

        $this->status = CompanyStatus::ACTIVE;
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new CompanyActivated($this->companyId, $activatedBy));
    }

    public function suspend(UserId $suspendedBy, string $reason): void
    {
        if ($this->status !== CompanyStatus::ACTIVE) {
            throw new BusinessRuleException('Only active companies can be suspended');
        }

        $this->status = CompanyStatus::SUSPENDED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function reactivate(UserId $reactivatedBy): void
    {
        if ($this->status !== CompanyStatus::SUSPENDED) {
            throw new BusinessRuleException('Only suspended companies can be reactivated');
        }

        $this->status = CompanyStatus::ACTIVE;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(UserId $deactivatedBy, string $reason): void
    {
        if (!$this->status->canBeDeactivated()) {
            throw new BusinessRuleException('Only active or suspended companies can be deactivated');
        }

        $this->status = CompanyStatus::DEACTIVATED;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateName(string $companyName, string $legalName): void
    {
        $this->companyName = $companyName;
        $this->legalName = $legalName;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateAddress(Address $address): void
    {
        $this->address = $address;
        $this->updatedAt = new DateTimeImmutable();
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

    // Getters
    public function id(): CompanyId
    {
        return $this->companyId;
    }

    public function companyName(): string
    {
        return $this->companyName;
    }

    public function legalName(): string
    {
        return $this->legalName;
    }

    public function taxId(): TaxIdentifier
    {
        return $this->taxId;
    }

    public function address(): Address
    {
        return $this->address;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function status(): CompanyStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
