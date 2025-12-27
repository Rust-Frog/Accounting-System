<?php

declare(strict_types=1);

namespace Application\Command\Transaction;

use Application\Command\CommandInterface;

/**
 * Command to void a transaction.
 */
final readonly class VoidTransactionCommand implements CommandInterface
{
    public function __construct(
        public string $transactionId,
        public string $voidedBy,
        public string $reason,
    ) {
    }
}
