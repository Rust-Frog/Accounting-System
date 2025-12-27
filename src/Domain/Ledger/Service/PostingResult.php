<?php

declare(strict_types=1);

namespace Domain\Ledger\Service;

use Domain\Identity\ValueObject\UserId;
use Domain\Ledger\Entity\BalanceChange;
use Domain\Transaction\ValueObject\TransactionId;

/**
 * Result of a ledger posting operation.
 */
final readonly class PostingResult
{
    /**
     * @param array<BalanceChange> $balanceChanges
     * @param array<string> $errors
     */
    private function __construct(
        private bool $success,
        private array $balanceChanges,
        private bool $requiresApproval,
        private ?string $approvalReason,
        private array $errors
    ) {
    }

    /**
     * @param array<BalanceChange> $balanceChanges
     */
    public static function success(array $balanceChanges): self
    {
        return new self(true, $balanceChanges, false, null, []);
    }

    public static function needsApproval(string $reason): self
    {
        return new self(false, [], true, $reason, []);
    }

    /**
     * @param array<string> $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, [], false, null, $errors);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return array<BalanceChange>
     */
    public function balanceChanges(): array
    {
        return $this->balanceChanges;
    }

    public function requiresApproval(): bool
    {
        return $this->requiresApproval;
    }

    public function approvalReason(): ?string
    {
        return $this->approvalReason;
    }

    /**
     * @return array<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
