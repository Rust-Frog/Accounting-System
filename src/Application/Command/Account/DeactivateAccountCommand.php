<?php

declare(strict_types=1);

namespace Application\Command\Account;

use Application\Command\CommandInterface;

/**
 * Command to deactivate an account.
 */
final readonly class DeactivateAccountCommand implements CommandInterface
{
    public function __construct(
        public string $accountId,
        public string $deactivatedBy,
        public string $reason,
    ) {
    }
}
