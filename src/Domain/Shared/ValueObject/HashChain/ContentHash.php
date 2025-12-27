<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObject\HashChain;

use JsonSerializable;
use Stringable;

/**
 * Immutable SHA-256 content hash for blockchain-style audit chains.
 * Used to create tamper-evident links between audit log entries.
 */
final class ContentHash implements JsonSerializable, Stringable
{
    private const ALGORITHM = 'sha256';
    private const GENESIS_PREFIX = 'GENESIS:';

    private function __construct(
        private readonly string $value,
        private readonly ?string $prefix = null
    ) {
    }

    /**
     * Create hash from string content.
     */
    public static function fromContent(string $content): self
    {
        return new self(hash(self::ALGORITHM, $content));
    }

    /**
     * Create hash from array with deterministic serialization (sorted keys).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // Sort keys recursively for deterministic output
        $sortedData = self::sortArrayRecursive($data);
        return self::fromContent(json_encode($sortedData, JSON_THROW_ON_ERROR));
    }

    /**
     * Create genesis hash for a company's audit chain.
     */
    public static function genesis(string $identifier): self
    {
        $content = self::GENESIS_PREFIX . $identifier;
        return new self(
            hash(self::ALGORITHM, $content),
            self::GENESIS_PREFIX
        );
    }

    /**
     * Recreate from stored hash string.
     */
    public static function fromString(string $hash): self
    {
        if (strlen($hash) !== 64 || !ctype_xdigit($hash)) {
            throw new \Domain\Shared\Exception\InvalidArgumentException(
                'Invalid hash format. Expected 64 hexadecimal characters.'
            );
        }

        return new self($hash);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function prefix(): ?string
    {
        return $this->prefix;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Recursively sort array keys for deterministic hashing.
     *
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    private static function sortArrayRecursive(array $array): array
    {
        ksort($array);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::sortArrayRecursive($value);
            }
        }

        return $array;
    }
}
