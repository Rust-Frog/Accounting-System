<?php

declare(strict_types=1);

namespace Domain\Transaction\ValueObject;

use Domain\Shared\ValueObject\Uuid;

final readonly class TransactionLineId
{
    private function __construct(
        private Uuid $uuid,
    ) {
    }

    public static function generate(): self
    {
        return new self(Uuid::generate());
    }

    public static function fromString(string $id): self
    {
        return new self(Uuid::fromString($id));
    }

    public function toString(): string
    {
        return $this->uuid->toString();
    }

    public function equals(self $other): bool
    {
        return $this->uuid->equals($other->uuid);
    }
}
