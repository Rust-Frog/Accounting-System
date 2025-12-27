<?php

declare(strict_types=1);

namespace Domain\Ledger\Service;

use Domain\Ledger\Entity\BalanceChange;
use Domain\Transaction\ValueObject\TransactionId;

/**
 * Result of a transaction reversal operation.
 */
final readonly class ReversalResult
{
    /**
     * @param array<BalanceChange> $reversalChanges
     * @param array<string> $errors
     */
    private function __construct(
        private bool $success,
        private TransactionId $transactionId,
        private array $reversalChanges,
        private array $errors
    ) {
    }

    /**
     * @param array<BalanceChange> $reversalChanges
     */
    public static function success(TransactionId $transactionId, array $reversalChanges): self
    {
        return new self(true, $transactionId, $reversalChanges, []);
    }

    /**
     * @param array<string> $errors
     */
    public static function failure(TransactionId $transactionId, array $errors): self
    {
        return new self(false, $transactionId, [], $errors);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function transactionId(): TransactionId
    {
        return $this->transactionId;
    }

    /**
     * @return array<BalanceChange>
     */
    public function reversalChanges(): array
    {
        return $this->reversalChanges;
    }

    /**
     * @return array<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
