<?php

declare(strict_types=1);

namespace Application\Dto\Transaction;

use Application\Dto\DtoInterface;

/**
 * DTO representing a transaction for external consumption.
 */
final readonly class TransactionDto implements DtoInterface
{
    /**
     * @param TransactionLineDto[] $lines
     * @param array<array<string, mixed>>|null $edgeCaseFlags
     */
    public function __construct(
        public string $id,
        public string $transactionNumber,
        public string $companyId,
        public string $status,
        public string $description,
        public int $totalDebitsCents,
        public int $totalCreditsCents,
        public array $lines,
        public ?string $referenceNumber,
        public string $transactionDate,
        public string $createdAt,
        public ?string $postedAt,
        public bool $requiresApproval = false,
        public ?string $approvalId = null,
        public ?array $edgeCaseFlags = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'transaction_number' => $this->transactionNumber,
            'company_id' => $this->companyId,
            'status' => $this->status,
            'description' => $this->description,
            'total_debits_cents' => $this->totalDebitsCents,
            'total_credits_cents' => $this->totalCreditsCents,
            'lines' => array_map(fn($line) => $line->toArray(), $this->lines),
            'reference_number' => $this->referenceNumber,
            'transaction_date' => $this->transactionDate,
            'created_at' => $this->createdAt,
            'posted_at' => $this->postedAt,
            'requires_approval' => $this->requiresApproval,
            'approval_id' => $this->approvalId,
            'edge_case_flags' => $this->edgeCaseFlags,
        ];
    }
}
