<?php

declare(strict_types=1);

namespace Application\Dto\Transaction;

use Application\Dto\DtoInterface;

/**
 * DTO representing a transaction line for external consumption.
 */
final readonly class TransactionLineDto implements DtoInterface
{
    public function __construct(
        public string $id,
        public string $accountId,
        public string $accountCode,
        public string $accountName,
        public string $lineType,
        public int $amountCents,
        public int $lineOrder,
        public string $description,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->accountId,
            'account_code' => $this->accountCode,
            'account_name' => $this->accountName,
            'line_type' => $this->lineType,
            'amount_cents' => $this->amountCents,
            'line_order' => $this->lineOrder,
            'description' => $this->description,
        ];
    }
}
