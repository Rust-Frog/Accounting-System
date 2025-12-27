<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject\DateTime;

use DateTimeImmutable;
use Domain\Shared\Exception\InvalidArgumentException;
use InvalidArgumentException as NativeInvalidArgumentException;
use JsonSerializable;
use Stringable;

final class DateTimeValue implements JsonSerializable, Stringable
{
    private const FORMAT = 'Y-m-d H:i:s';

    private DateTimeImmutable $value;

    private function __construct(DateTimeImmutable $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        $date = DateTimeImmutable::createFromFormat(self::FORMAT, $value);

        if ($date === false || $date->format(self::FORMAT) !== $value) {
            throw new InvalidArgumentException(
                sprintf('Invalid date format. Expected "%s", got "%s"', self::FORMAT, $value)
            );
        }

        return new self($date);
    }

    public static function fromNative(DateTimeImmutable $date): self
    {
        // Normalize to our format to drop microseconds if needed, or just keep precision
        // For strict testing matching, we probably want to ensure it formats back and forth cleanly
        return new self($date);
    }

    public static function now(): self
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

    public function diffInDays(self $other): int
    {
        $diff = $this->value->diff($other->value);
        return (int) $diff->days;
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
