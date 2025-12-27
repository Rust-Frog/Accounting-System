<?php

declare(strict_types=1);

namespace Domain\ChartOfAccounts\ValueObject;

use Domain\Shared\Exception\InvalidArgumentException;

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

    /**
     * Get the first digit (type prefix) of the code.
     */
    public function getTypePrefix(): int
    {
        return (int) substr((string) $this->code, 0, 1);
    }

    /**
     * Check if this code represents a sub-account of the given parent code.
     * Sub-accounts share the same type prefix (first digit).
     */
    public function isSubAccountOf(self $parent): bool
    {
        return $this->getTypePrefix() === $parent->getTypePrefix()
            && $this->code !== $parent->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
