<?php

declare(strict_types=1);

namespace Domain\ChartOfAccounts\ValueObject;

use InvalidArgumentException;

final readonly class AccountCode
{
    private const MIN_CODE = 1000;
    private const MAX_CODE = 5999;

    private function __construct(
        private int $code,
    ) {
    }

    public static function fromString(string $code): self
    {
        if ($code === '') {
            throw new InvalidArgumentException('Account code cannot be empty');
        }

        if (!ctype_digit($code)) {
            throw new InvalidArgumentException('Account code must be numeric');
        }

        return self::fromInt((int) $code);
    }

    public static function fromInt(int $code): self
    {
        if ($code < self::MIN_CODE || $code > self::MAX_CODE) {
            throw new InvalidArgumentException(
                sprintf('Account code must be between %d and %d', self::MIN_CODE, self::MAX_CODE)
            );
        }

        return new self($code);
    }

    public function toString(): string
    {
        return (string) $this->code;
    }

    public function toInt(): int
    {
        return $this->code;
    }

    public function accountType(): AccountType
    {
        return AccountType::fromCodeRange($this->code);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
