<?php

declare(strict_types=1);

namespace Application\Command\Transaction;

use Application\Command\CommandInterface;

/**
 * Command to delete an existing draft transaction.
 */
final readonly class DeleteTransactionCommand implements CommandInterface
{
    public function __construct(
        public string $transactionId,
        public string $companyId,
        public string $deletedBy,
    ) {
    }
}
