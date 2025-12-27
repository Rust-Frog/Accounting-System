<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

use Domain\Shared\Exception\InvalidArgumentException;

final class Username
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Username cannot be empty');
        }

        if (strlen($value) < 3) {
            throw new InvalidArgumentException('Username must be at least 3 characters');
        }
        
        if (strlen($value) > 50) {
            throw new InvalidArgumentException('Username must be at most 50 characters');
        }

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

    public function __toString(): string
    {
        return $this->value;
    }
}
