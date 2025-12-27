<?php

declare(strict_types=1);

namespace Application\Command\Account;

use Application\Command\CommandInterface;

/**
 * Command to create a new account in the chart of accounts.
 */
final readonly class CreateAccountCommand implements CommandInterface
{
    public function __construct(
        public string $companyId,
        public string $code,
        public string $name,
        public ?string $description = null,
        public ?string $parentAccountId = null,
    ) {
    }
}
