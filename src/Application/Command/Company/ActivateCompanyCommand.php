<?php

declare(strict_types=1);

namespace Application\Command\Company;

use Application\Command\CommandInterface;

/**
 * Command to activate a pending company.
 */
final readonly class ActivateCompanyCommand implements CommandInterface
{
    public function __construct(
        public string $companyId,
        public string $activatedBy,
    ) {
    }
}
