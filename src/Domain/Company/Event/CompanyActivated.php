<?php

declare(strict_types=1);

namespace Domain\Company\Event;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\DomainEvent;

final class CompanyActivated implements DomainEvent
{
    private DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly CompanyId $companyId,
        private readonly UserId $activatedBy
    ) {
        $this->occurredOn = new DateTimeImmutable();
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'company.activated';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId->toString(),
            'activated_by' => $this->activatedBy->toString(),
            'occurred_on' => $this->occurredOn->format('Y-m-d H:i:s'),
        ];
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function activatedBy(): UserId
    {
        return $this->activatedBy;
    }
}
