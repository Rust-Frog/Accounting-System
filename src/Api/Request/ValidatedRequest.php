<?php

declare(strict_types=1);

namespace Api\Request;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Base class for validated request objects.
 * Provides validation infrastructure for API request data.
 */
abstract class ValidatedRequest
{
    /** @var array<string, string[]> */
    private array $errors = [];

    /** @var array<string, mixed> */
    protected array $data = [];

    /**
     * Create and validate request from PSR-7 request.
     *
     * @throws ValidationException if validation fails
     */
    public static function fromRequest(ServerRequestInterface $request): static
    {
        /** @var static $instance */
        $instance = new static();
        $instance->data = $request->getParsedBody() ?? [];
        $instance->validate();

        if ($instance->hasErrors()) {
            throw new ValidationException($instance->errors);
        }

        return $instance;
    }

    /**
     * Implement validation rules in child classes.
     */
    abstract protected function validate(): void;

    /**
     * Get a value from the request data.
     */
    protected function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if a field exists and is not empty.
     */
    protected function has(string $key): bool
    {
        return isset($this->data[$key]) && $this->data[$key] !== '';
    }

    /**
     * Add a validation error.
     */
    protected function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    /**
     * Check if there are any validation errors.
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    // Common validation helpers

    protected function requireField(string $field, string $label = null): void
    {
        if (!$this->has($field)) {
            $this->addError($field, ($label ?? $field) . ' is required');
        }
    }

    protected function requireEmail(string $field): void
    {
        if ($this->has($field) && !filter_var($this->get($field), FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'Invalid email format');
        }
    }

    protected function requireMinLength(string $field, int $length, string $label = null): void
    {
        $value = $this->get($field, '');
        if (is_string($value) && strlen($value) < $length) {
            $this->addError($field, ($label ?? $field) . " must be at least {$length} characters");
        }
    }

    protected function requireMaxLength(string $field, int $length, string $label = null): void
    {
        $value = $this->get($field, '');
        if (is_string($value) && strlen($value) > $length) {
            $this->addError($field, ($label ?? $field) . " must be at most {$length} characters");
        }
    }

    protected function requireInteger(string $field, string $label = null): void
    {
        $value = $this->get($field);
        if ($value !== null && !is_int($value) && !ctype_digit((string) $value)) {
            $this->addError($field, ($label ?? $field) . ' must be an integer');
        }
    }

    protected function requirePositive(string $field, string $label = null): void
    {
        $value = $this->get($field);
        if ($value !== null && (int) $value <= 0) {
            $this->addError($field, ($label ?? $field) . ' must be positive');
        }
    }

    protected function requireArray(string $field, string $label = null): void
    {
        $value = $this->get($field);
        if ($value !== null && !is_array($value)) {
            $this->addError($field, ($label ?? $field) . ' must be an array');
        }
    }

    protected function requireMinArrayLength(string $field, int $length, string $label = null): void
    {
        $value = $this->get($field, []);
        if (is_array($value) && count($value) < $length) {
            $this->addError($field, ($label ?? $field) . " must have at least {$length} items");
        }
    }

    protected function requireInList(string $field, array $allowed, string $label = null): void
    {
        $value = $this->get($field);
        if ($value !== null && !in_array($value, $allowed, true)) {
            $allowedStr = implode(', ', $allowed);
            $this->addError($field, ($label ?? $field) . " must be one of: {$allowedStr}");
        }
    }
}
