<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

use Domain\Shared\Exception\InvalidArgumentException;

final class Password
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    private static function validate(string $value): void
    {
        self::ensureMinimumLength($value);
        self::ensureContainsUppercase($value);
        self::ensureContainsLowercase($value);
        self::ensureContainsDigit($value);
    }

    private static function ensureMinimumLength(string $value): void
    {
        if (strlen($value) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters');
        }
    }

    private static function ensureContainsUppercase(string $value): void
    {
        if (!preg_match('/[A-Z]/', $value)) {
            throw new InvalidArgumentException('Password must contain uppercase letter');
        }
    }

    private static function ensureContainsLowercase(string $value): void
    {
        if (!preg_match('/[a-z]/', $value)) {
            throw new InvalidArgumentException('Password must contain lowercase letter');
        }
    }

    private static function ensureContainsDigit(string $value): void
    {
        if (!preg_match('/[0-9]/', $value)) {
            throw new InvalidArgumentException('Password must contain digit');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
