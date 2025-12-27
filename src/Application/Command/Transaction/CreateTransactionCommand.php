<?php

declare(strict_types=1);

namespace Application\Command\Transaction;

use Application\Command\CommandInterface;

/**
 * Command to create a new transaction.
 */
final readonly class CreateTransactionCommand implements CommandInterface
{
    /**
     * @param TransactionLineData[] $lines
     */
    public function __construct(
        public string $companyId,
        public string $createdBy,
        public string $description,
        public string $currency,
        public array $lines,
        public ?string $transactionDate = null,
        public ?string $referenceNumber = null,
    ) {
    }
}
