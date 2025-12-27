<?php

declare(strict_types=1);

namespace Domain\Shared\Exception;

final class ValidationException extends DomainException implements SafeToExposeInterface
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Validation failed'
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
}
