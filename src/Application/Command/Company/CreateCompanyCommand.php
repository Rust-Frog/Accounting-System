<?php

declare(strict_types=1);

namespace Application\Command\Company;

use Application\Command\CommandInterface;

/**
 * Command to create a new company.
 */
final readonly class CreateCompanyCommand implements CommandInterface
{
    public function __construct(
        public string $name,
        public string $taxId,
        public string $currency,
        public ?string $address = null,
        public ?string $fiscalYearStart = null,
    ) {
    }
}
