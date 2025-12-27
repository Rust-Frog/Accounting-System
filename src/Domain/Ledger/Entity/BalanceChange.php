<?php

declare(strict_types=1);

namespace Domain\Ledger\Entity;

use DateTimeImmutable;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\ChartOfAccounts\ValueObject\NormalBalance;
use Domain\Ledger\ValueObject\BalanceChangeId;
use Domain\Transaction\ValueObject\LineType;
use Domain\Transaction\ValueObject\TransactionId;

/**
 * Value object / entity representing a single balance change from a transaction.
 * Immutable record of how a transaction affected an account balance.
 */
final readonly class BalanceChange
{
    private function __construct(
        private BalanceChangeId $id,
        private AccountId $accountId,
        private TransactionId $transactionId,
        private LineType $lineType,
        private int $amountCents,
        private int $previousBalanceCents,
        private int $newBalanceCents,
        private int $changeCents,
        private bool $isReversal,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * Create a balance change for a transaction line.
     * BR-LP-001: Calculate change based on normal balance.
     */
    public static function create(
        AccountId $accountId,
        TransactionId $transactionId,
        LineType $lineType,
        int $amountCents,
        int $previousBalanceCents,
        NormalBalance $normalBalance,
    ): self {
        $changeCents = self::calculateChange($normalBalance, $lineType, $amountCents);
        $newBalanceCents = $previousBalanceCents + $changeCents;

        return new self(
            id: BalanceChangeId::generate(),
            accountId: $accountId,
            transactionId: $transactionId,
            lineType: $lineType,
            amountCents: $amountCents,
            previousBalanceCents: $previousBalanceCents,
            newBalanceCents: $newBalanceCents,
            changeCents: $changeCents,
            isReversal: false,
            occurredAt: new DateTimeImmutable(),
        );
    }

    public static function reconstruct(
        BalanceChangeId $id,
        AccountId $accountId,
        TransactionId $transactionId,
        LineType $lineType,
        int $amountCents,
        int $previousBalanceCents,
        int $newBalanceCents,
        int $changeCents,
        bool $isReversal,
        DateTimeImmutable $occurredAt
    ): self {
        return new self(
            id: $id,
            accountId: $accountId,
            transactionId: $transactionId,
            lineType: $lineType,
            amountCents: $amountCents,
            previousBalanceCents: $previousBalanceCents,
            newBalanceCents: $newBalanceCents,
            changeCents: $changeCents,
            isReversal: $isReversal,
            occurredAt: $occurredAt
        );
    }

    /**
     * Create a reversal change (opposite of original).
     */
    public static function createReversal(
        AccountId $accountId,
        TransactionId $transactionId,
        LineType $lineType,
        int $amountCents,
        int $previousBalanceCents,
        NormalBalance $normalBalance,
    ): self {
        $changeCents = self::calculateChange($normalBalance, $lineType, $amountCents);
        $newBalanceCents = $previousBalanceCents + $changeCents;

        return new self(
            id: BalanceChangeId::generate(),
            accountId: $accountId,
            transactionId: $transactionId,
            lineType: $lineType,
            amountCents: $amountCents,
            previousBalanceCents: $previousBalanceCents,
            newBalanceCents: $newBalanceCents,
            changeCents: $changeCents,
            isReversal: true,
            occurredAt: new DateTimeImmutable(),
        );
    }

    /**
     * BR-LP-001: Balance change calculation.
     * Same side as normal balance = increase (+)
     * Opposite side = decrease (-)
     */
    private static function calculateChange(
        NormalBalance $normalBalance,
        LineType $lineType,
        int $amountCents
    ): int {
        $normalIsDebit = $normalBalance === NormalBalance::DEBIT;
        $lineIsDebit = $lineType === LineType::DEBIT;

        // Same side = increase, opposite = decrease
        if ($normalIsDebit === $lineIsDebit) {
            return $amountCents; // Increase
        }

        return -$amountCents; // Decrease
    }

    public function id(): BalanceChangeId
    {
        return $this->id;
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function transactionId(): TransactionId
    {
        return $this->transactionId;
    }

    public function lineType(): LineType
    {
        return $this->lineType;
    }

    public function amountCents(): int
    {
        return $this->amountCents;
    }

    public function previousBalanceCents(): int
    {
        return $this->previousBalanceCents;
    }

    public function newBalanceCents(): int
    {
        return $this->newBalanceCents;
    }

    public function changeCents(): int
    {
        return $this->changeCents;
    }

    public function isReversal(): bool
    {
        return $this->isReversal;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'account_id' => $this->accountId->toString(),
            'transaction_id' => $this->transactionId->toString(),
            'line_type' => $this->lineType->value,
            'amount_cents' => $this->amountCents,
            'previous_balance_cents' => $this->previousBalanceCents,
            'new_balance_cents' => $this->newBalanceCents,
            'change_cents' => $this->changeCents,
            'is_reversal' => $this->isReversal,
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
