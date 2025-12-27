<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

/**
 * Value object for OTP recovery codes.
 * Used as backup authentication method when TOTP device is unavailable.
 */
final readonly class RecoveryCode
{
    private const CODE_LENGTH = 8;
    private const CODE_COUNT = 10;

    private function __construct(
        private string $code,
        private bool $used = false
    ) {
    }

    /**
     * Generate a single recovery code.
     */
    public static function generate(): self
    {
        $code = strtoupper(bin2hex(random_bytes(self::CODE_LENGTH / 2)));
        return new self($code);
    }

    /**
     * Generate a set of recovery codes.
     *
     * @return array<self>
     */
    public static function generateSet(): array
    {
        $codes = [];
        for ($i = 0; $i < self::CODE_COUNT; $i++) {
            $codes[] = self::generate();
        }
        return $codes;
    }

    /**
     * Reconstitute from stored data.
     */
    public static function fromString(string $code, bool $used = false): self
    {
        return new self(strtoupper(trim($code)), $used);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function markUsed(): self
    {
        return new self($this->code, true);
    }

    /**
     * Check if input matches this recovery code.
     * Uses timing-safe comparison.
     */
    public function matches(string $input): bool
    {
        return hash_equals($this->code, strtoupper(trim($input)));
    }

    public function __toString(): string
    {
        return $this->code;
    }
}

