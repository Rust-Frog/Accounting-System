<?php

declare(strict_types=1);

namespace Domain\Transaction\Entity;

use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Shared\ValueObject\Money;
use Domain\Transaction\ValueObject\LineType;
use Domain\Transaction\ValueObject\TransactionLineId;

final readonly class TransactionLine
{
    private function __construct(
        private TransactionLineId $id,
        private AccountId $accountId,
        private LineType $lineType,
        private Money $amount,
        private ?string $description,
    ) {
    }

    public static function create(
        AccountId $accountId,
        LineType $lineType,
        Money $amount,
        ?string $description = null,
    ): self {
        return new self(
            id: TransactionLineId::generate(),
            accountId: $accountId,
            lineType: $lineType,
            amount: $amount,
            description: $description,
        );
    }

    public static function reconstitute(
        TransactionLineId $id,
        AccountId $accountId,
        LineType $lineType,
        Money $amount,
        ?string $description,
    ): self {
        return new self(
            id: $id,
            accountId: $accountId,
            lineType: $lineType,
            amount: $amount,
            description: $description,
        );
    }

    public function id(): TransactionLineId
    {
        return $this->id;
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function lineType(): LineType
    {
        return $this->lineType;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function isDebit(): bool
    {
        return $this->lineType->isDebit();
    }

    public function isCredit(): bool
    {
        return $this->lineType->isCredit();
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
