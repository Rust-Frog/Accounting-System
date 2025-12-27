<?php

declare(strict_types=1);

namespace Domain\Transaction\Service;

/**
 * Immutable result of transaction validation.
 */
final class ValidationResult
{
    /**
     * @param array<string> $errors
     */
    private function __construct(
        private readonly bool $isValid,
        private readonly array $errors
    ) {
    }

    /**
     * Create a valid result.
     */
    public static function valid(): self
    {
        return new self(true, []);
    }

    /**
     * Create an invalid result with errors.
     *
     * @param array<string> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(false, $errors);
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * @return array<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Check if a specific error message exists (exact match or substring).
     */
    public function hasError(string $message): bool
    {
        foreach ($this->errors as $error) {
            if ($error === $message || str_contains($error, $message)) {
                return true;
            }
        }

        return false;
    }

    public function errorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Merge with another result. Final result is valid only if both are valid.
     */
    public function merge(self $other): self
    {
        if ($this->isValid && $other->isValid) {
            return self::valid();
        }

        return self::invalid(array_merge($this->errors, $other->errors));
    }
}
