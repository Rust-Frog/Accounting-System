<?php

declare(strict_types=1);

namespace Domain\Ledger\ValueObject;

use Domain\Shared\ValueObject\Uuid;

final readonly class LedgerId
{
    private function __construct(
        private string $value
    ) {
    }

    public static function generate(): self
    {
        return new self(Uuid::generate()->toString());
    }

    public static function fromString(string $value): self
    {
        Uuid::fromString($value);

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
