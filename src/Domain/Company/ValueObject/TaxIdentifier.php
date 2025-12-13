<?php

declare(strict_types=1);

namespace Domain\Company\ValueObject;

use InvalidArgumentException;

final class TaxIdentifier
{
    private function __construct(
        private readonly string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Tax identifier cannot be empty');
        }

        return new self($trimmed);
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
