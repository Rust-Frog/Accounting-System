<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject;

use InvalidArgumentException;

final class Email
{
    private function __construct(
        private readonly string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            throw new InvalidArgumentException('Email cannot be empty');
        }

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                sprintf('Invalid email format: %s', $value)
            );
        }

        return new self($normalized);
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
