<?php

declare(strict_types=1);

namespace Application\Command\Transaction;

use Application\Command\CommandInterface;

/**
 * Command to post a pending transaction.
 */
final readonly class PostTransactionCommand implements CommandInterface
{
    public function __construct(
        public string $transactionId,
        public string $postedBy,
    ) {
    }
}
