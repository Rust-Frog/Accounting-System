<?php

declare(strict_types=1);

namespace Domain\Reporting\Entity;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\ValueObject\ReportId;
use Domain\Reporting\ValueObject\ReportPeriod;
use Domain\Reporting\ValueObject\TrialBalanceEntry;

/**
 * Trial Balance report entity.
 * Generated on-demand, validates that debits equal credits.
 */
final readonly class TrialBalance
{
    /**
     * @param array<TrialBalanceEntry> $entries
     */
    public function __construct(
        private ReportId $id,
        private CompanyId $companyId,
        private ReportPeriod $period,
        private DateTimeImmutable $generatedAt,
        private array $entries,
        private int $totalDebitsCents,
        private int $totalCreditsCents
    ) {
    }

    public function id(): ReportId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function period(): ReportPeriod
    {
        return $this->period;
    }

    public function generatedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    /**
     * @return array<TrialBalanceEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function totalDebitsCents(): int
    {
        return $this->totalDebitsCents;
    }

    public function totalCreditsCents(): int
    {
        return $this->totalCreditsCents;
    }

    /**
     * BR-FR-001: Trial balance must be balanced.
     */
    public function isBalanced(): bool
    {
        // Allow 1 cent tolerance for rounding
        return abs($this->totalDebitsCents - $this->totalCreditsCents) <= 1;
    }

    public function differenceCents(): int
    {
        return $this->totalDebitsCents - $this->totalCreditsCents;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'company_id' => $this->companyId->toString(),
            'period' => $this->period->toArray(),
            'generated_at' => $this->generatedAt->format('Y-m-d H:i:s'),
            'entries' => array_map(fn($e) => $e->toArray(), $this->entries),
            'total_debits_cents' => $this->totalDebitsCents,
            'total_credits_cents' => $this->totalCreditsCents,
            'is_balanced' => $this->isBalanced(),
        ];
    }
}
