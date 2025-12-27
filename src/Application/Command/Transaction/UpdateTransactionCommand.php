<?php

declare(strict_types=1);

namespace Application\Command\Transaction;

use Application\Command\CommandInterface;

/**
 * Command to update an existing draft transaction.
 */
final readonly class UpdateTransactionCommand implements CommandInterface
{
    /**
     * @param TransactionLineData[] $lines
     */
    public function __construct(
        public string $transactionId,
        public string $companyId,
        public string $updatedBy,
        public string $description,
        public string $currency,
        public array $lines,
        public ?string $transactionDate = null,
        public ?string $referenceNumber = null,
    ) {
    }
}
