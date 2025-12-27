<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject\DateTime;

use DateTimeImmutable;
use Domain\Shared\Exception\InvalidArgumentException;
use JsonSerializable;
use Stringable;

final class DateValue implements JsonSerializable, Stringable
{
    private const FORMAT = 'Y-m-d';

    private DateTimeImmutable $value;

    private function __construct(DateTimeImmutable $value)
    {
        // Ensure time is stripped
        $this->value = $value->setTime(0, 0, 0);
    }

    public static function fromString(string $value): self
    {
        $date = DateTimeImmutable::createFromFormat(self::FORMAT, $value);

        // PHP createFromFormat helps but strict checking is done via formatting back
        if ($date === false || $date->format(self::FORMAT) !== $value) {
            throw new InvalidArgumentException(
                sprintf('Invalid date format. Expected "%s", got "%s"', self::FORMAT, $value)
            );
        }

        return new self($date);
    }

    public static function fromNative(DateTimeImmutable $date): self
    {
        return new self($date);
    }

    public static function today(): self
    {
        return new self(new DateTimeImmutable());
    }

    public function value(): DateTimeImmutable
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value->format(self::FORMAT);
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    public function isBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function isAfter(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
