<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

use Domain\Shared\ValueObject\Uuid;

final class UserId
{
    private function __construct(
        private readonly Uuid $value
    ) {
    }

    public static function generate(): self
    {
        return new self(Uuid::generate());
    }

    public static function fromString(string $value): self
    {
        return new self(Uuid::fromString($value));
    }

    public function toString(): string
    {
        return $this->value->toString();
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }
}
