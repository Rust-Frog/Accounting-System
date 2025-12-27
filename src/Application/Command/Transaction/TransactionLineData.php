<?php

declare(strict_types=1);

namespace Application\Command\Transaction;

/**
 * Represents a line item in a transaction.
 */
final readonly class TransactionLineData
{
    public function __construct(
        public string $accountId,
        public string $lineType,
        public int $amountCents,
        public string $description,
    ) {
    }
}
