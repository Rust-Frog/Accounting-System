<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject;

use InvalidArgumentException;

final class Uuid
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private function __construct(
        private readonly string $value
    ) {
    }

    public static function generate(): self
    {
        // Generate UUID v4 using random bytes
        $data = random_bytes(16);

        // Set version to 0100 (UUID v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10 (RFC 4122 variant)
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return new self($uuid);
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            throw new InvalidArgumentException('UUID cannot be empty');
        }

        if (!preg_match(self::UUID_PATTERN, $normalized)) {
            throw new InvalidArgumentException(
                sprintf('Invalid UUID format: %s', $value)
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
