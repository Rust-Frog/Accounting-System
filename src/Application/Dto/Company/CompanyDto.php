<?php

declare(strict_types=1);

namespace Application\Dto\Company;

use Application\Dto\DtoInterface;

/**
 * DTO representing a company for external consumption.
 */
final readonly class CompanyDto implements DtoInterface
{
    public function __construct(
        public string $id,
        public string $name,
        public string $taxId,
        public string $currency,
        public string $status,
        public ?string $address,
        public ?string $fiscalYearStart,
        public string $createdAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tax_id' => $this->taxId,
            'currency' => $this->currency,
            'status' => $this->status,
            'address' => $this->address,
            'fiscal_year_start' => $this->fiscalYearStart,
            'created_at' => $this->createdAt,
        ];
    }
}
