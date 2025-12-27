<?php

declare(strict_types=1);

namespace Domain\Reporting\Entity;

use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\HashChain\ContentHash;

/**
 * Represents a closed accounting period for a company.
 * Once a period is closed, no transactions can be posted within that date range.
 */
final class ClosedPeriod
{
    public function __construct(
        private readonly string $id,
        private readonly CompanyId $companyId,
        private readonly \DateTimeImmutable $startDate,
        private readonly \DateTimeImmutable $endDate,
        private readonly UserId $closedBy,
        private readonly \DateTimeImmutable $closedAt,
        private readonly ?string $approvalId,
        private readonly int $netIncomeCents,
        private readonly ?ContentHash $chainHash = null,
    ) {
    }

    public static function create(
        CompanyId $companyId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        UserId $closedBy,
        ?string $approvalId = null,
        int $netIncomeCents = 0,
        ?ContentHash $chainHash = null,
    ): self {
        return new self(
            id: \Ramsey\Uuid\Uuid::uuid4()->toString(),
            companyId: $companyId,
            startDate: $startDate,
            endDate: $endDate,
            closedBy: $closedBy,
            closedAt: new \DateTimeImmutable(),
            approvalId: $approvalId,
            netIncomeCents: $netIncomeCents,
            chainHash: $chainHash,
        );
    }

    public function id(): string { return $this->id; }
    public function companyId(): CompanyId { return $this->companyId; }
    public function startDate(): \DateTimeImmutable { return $this->startDate; }
    public function endDate(): \DateTimeImmutable { return $this->endDate; }
    public function closedBy(): UserId { return $this->closedBy; }
    public function closedAt(): \DateTimeImmutable { return $this->closedAt; }
    public function approvalId(): ?string { return $this->approvalId; }
    public function netIncomeCents(): int { return $this->netIncomeCents; }
    public function chainHash(): ?ContentHash { return $this->chainHash; }

    /**
     * Check if a given date falls within this closed period.
     */
    public function containsDate(\DateTimeImmutable $date): bool
    {
        $dateOnly = $date->format('Y-m-d');
        return $dateOnly >= $this->startDate->format('Y-m-d') 
            && $dateOnly <= $this->endDate->format('Y-m-d');
    }
}
