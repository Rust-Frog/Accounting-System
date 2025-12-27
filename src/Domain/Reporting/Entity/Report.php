<?php

declare(strict_types=1);

namespace Domain\Reporting\Entity;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\ValueObject\ReportId;
use Domain\Reporting\ValueObject\ReportPeriod;

/**
 * Generic Report Entity.
 * Stores report data as a flexible JSON structure.
 */
final class Report
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly ReportId $id,
        private readonly CompanyId $companyId,
        private readonly ReportPeriod $period,
        private readonly string $type,
        private readonly array $data,
        private readonly DateTimeImmutable $generatedAt
    ) {
    }

    public static function reconstruct(
        ReportId $id,
        CompanyId $companyId,
        ReportPeriod $period,
        string $type,
        array $data,
        DateTimeImmutable $generatedAt
    ): self {
        return new self(
            id: $id,
            companyId: $companyId,
            period: $period,
            type: $type,
            data: $data,
            generatedAt: $generatedAt
        );
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

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function generatedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }
}
