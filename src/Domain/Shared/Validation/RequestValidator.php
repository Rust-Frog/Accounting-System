<?php

declare(strict_types=1);

namespace Domain\Shared\Validation;

/**
 * Centralized request validation service.
 * 
 * Validates input data against typed rules.
 * Used by controllers to validate API requests before processing.
 * 
 * Rules format:
 * [
 *     'field_name' => ['required', 'string', 'min:3', 'max:255'],
 *     'amount' => ['required', 'numeric', 'positive'],
 *     'email' => ['required', 'email'],
 * ]
 */
final class RequestValidator
{
    /**
     * Validate data against rules.
     * 
     * @param array<string, mixed> $data Input data
     * @param array<string, array<string>> $rules Validation rules per field
     */
    public function validate(array $data, array $rules): ValidationResult
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $rule) {
                $error = $this->validateRule($field, $value, $rule, $data);
                if ($error !== null) {
                    if (!isset($errors[$field])) {
                        $errors[$field] = [];
                    }
                    $errors[$field][] = $error;
                    
                    // Stop on first error for this field (optional)
                    break;
                }
            }
        }

        if (empty($errors)) {
            return ValidationResult::valid();
        }

        return ValidationResult::invalid($errors);
    }

    /**
     * Validate a single rule.
     * 
     * @return string|null Error message or null if valid
     */
    private function validateRule(string $field, mixed $value, string $rule, array $data): ?string
    {
        // Parse rule with parameters (e.g., 'min:3', 'max:255')
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $param = $parts[1] ?? null;

        return match ($ruleName) {
            'required' => $this->validateRequired($field, $value),
            'string' => $this->validateString($field, $value),
            'numeric' => $this->validateNumeric($field, $value),
            'integer' => $this->validateInteger($field, $value),
            'positive' => $this->validatePositive($field, $value),
            'email' => $this->validateEmail($field, $value),
            'uuid' => $this->validateUuid($field, $value),
            'date' => $this->validateDate($field, $value),
            'array' => $this->validateArray($field, $value),
            'min' => $this->validateMin($field, $value, (int) $param),
            'max' => $this->validateMax($field, $value, (int) $param),
            'in' => $this->validateIn($field, $value, $param),
            'currency' => $this->validateCurrency($field, $value),
            'not_empty' => $this->validateNotEmpty($field, $value),
            default => null, // Unknown rule, skip
        };
    }

    private function validateRequired(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return "{$field} is required";
        }
        return null;
    }

    private function validateString(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_string($value)) {
            return "{$field} must be a string";
        }
        return null;
    }

    private function validateNumeric(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_numeric($value)) {
            return "{$field} must be numeric";
        }
        return null;
    }

    private function validateInteger(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_int($value) && !ctype_digit((string) $value)) {
            return "{$field} must be an integer";
        }
        return null;
    }

    private function validatePositive(string $field, mixed $value): ?string
    {
        if ($value !== null && is_numeric($value) && (float) $value <= 0) {
            return "{$field} must be positive";
        }
        return null;
    }

    private function validateEmail(string $field, mixed $value): ?string
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "{$field} must be a valid email address";
        }
        return null;
    }

    private function validateUuid(string $field, mixed $value): ?string
    {
        if ($value !== null) {
            $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
            if (!preg_match($pattern, (string) $value)) {
                return "{$field} must be a valid UUID";
            }
        }
        return null;
    }

    private function validateDate(string $field, mixed $value): ?string
    {
        if ($value !== null) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                return "{$field} must be a valid date (YYYY-MM-DD)";
            }
        }
        return null;
    }

    private function validateArray(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_array($value)) {
            return "{$field} must be an array";
        }
        return null;
    }

    private function validateMin(string $field, mixed $value, int $min): ?string
    {
        if ($value === null) {
            return null;
        }
        
        if (is_string($value) && strlen($value) < $min) {
            return "{$field} must be at least {$min} characters";
        }
        
        if (is_array($value) && count($value) < $min) {
            return "{$field} must have at least {$min} items";
        }
        
        if (is_numeric($value) && $value < $min) {
            return "{$field} must be at least {$min}";
        }
        
        return null;
    }

    private function validateMax(string $field, mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        
        if (is_string($value) && strlen($value) > $max) {
            return "{$field} must not exceed {$max} characters";
        }
        
        if (is_array($value) && count($value) > $max) {
            return "{$field} must not exceed {$max} items";
        }
        
        if (is_numeric($value) && $value > $max) {
            return "{$field} must not exceed {$max}";
        }
        
        return null;
    }

    private function validateIn(string $field, mixed $value, ?string $options): ?string
    {
        if ($value === null || $options === null) {
            return null;
        }
        
        $allowed = explode(',', $options);
        if (!in_array((string) $value, $allowed, true)) {
            return "{$field} must be one of: " . implode(', ', $allowed);
        }
        
        return null;
    }

    private function validateCurrency(string $field, mixed $value): ?string
    {
        if ($value !== null) {
            $validCurrencies = ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'PHP'];
            if (!in_array(strtoupper((string) $value), $validCurrencies, true)) {
                return "{$field} must be a valid currency code";
            }
        }
        return null;
    }

    private function validateNotEmpty(string $field, mixed $value): ?string
    {
        if (is_string($value) && trim($value) === '') {
            return "{$field} cannot be empty";
        }
        return null;
    }
}
