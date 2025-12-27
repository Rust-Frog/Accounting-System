<?php

declare(strict_types=1);

namespace Domain\Shared\Validation;

/**
 * Result of a validation operation.
 * Immutable value object that holds validation errors.
 */
final class ValidationResult
{
    /** @var array<string, string[]> Field => error messages */
    private array $errors;

    private function __construct(array $errors = [])
    {
        $this->errors = $errors;
    }

    public static function valid(): self
    {
        return new self([]);
    }

    public static function invalid(array $errors): self
    {
        return new self($errors);
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function isInvalid(): bool
    {
        return !$this->isValid();
    }

    /**
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field.
     * @return string[]
     */
    public function errorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get all error messages as a flat array.
     * @return string[]
     */
    public function allMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }
        return $messages;
    }

    /**
     * Get first error message (useful for simple responses).
     */
    public function firstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        return null;
    }

    /**
     * Merge with another validation result.
     */
    public function merge(ValidationResult $other): self
    {
        $merged = $this->errors;
        foreach ($other->errors() as $field => $errors) {
            if (!isset($merged[$field])) {
                $merged[$field] = [];
            }
            $merged[$field] = array_merge($merged[$field], $errors);
        }
        return new self($merged);
    }
}
