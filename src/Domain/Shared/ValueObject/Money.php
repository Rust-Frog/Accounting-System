<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject;

use InvalidArgumentException;

final class Money
{
    private function __construct(
        private readonly int $cents,
        private readonly Currency $currency
    ) {
    }

    public static function fromFloat(float $amount, Currency $currency): self
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }

        // Convert to cents to avoid floating-point precision issues
        $cents = (int) round($amount * 100);

        return new self($cents, $currency);
    }

    public static function fromCents(int $cents, Currency $currency): self
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }

        return new self($cents, $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        $resultCents = $this->cents - $other->cents;

        if ($resultCents < 0) {
            throw new InvalidArgumentException('Subtraction would result in negative amount');
        }

        return new self($resultCents, $this->currency);
    }

    public function multiply(float $multiplier): self
    {
        $resultCents = (int) round($this->cents * $multiplier);

        if ($resultCents < 0) {
            throw new InvalidArgumentException('Multiplication would result in negative amount');
        }

        return new self($resultCents, $this->currency);
    }

    public function amount(): float
    {
        return round($this->cents / 100, 2);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents
            && $this->currency === $other->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot operate on different currencies: %s and %s',
                    $this->currency->value,
                    $other->currency->value
                )
            );
        }
    }
}
