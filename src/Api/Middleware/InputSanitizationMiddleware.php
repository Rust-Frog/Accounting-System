<?php

declare(strict_types=1);

namespace Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Global input sanitization middleware.
 * Sanitizes all incoming request data to prevent XSS, injection attacks.
 */
final class InputSanitizationMiddleware
{
    /** Maximum allowed string length for any single field */
    private const MAX_STRING_LENGTH = 65535;

    /** Maximum allowed array depth */
    private const MAX_ARRAY_DEPTH = 10;

    /**
     * Fields that should NOT be sanitized (e.g., passwords).
     * @var array<string>
     */
    private const BYPASS_FIELDS = ['password', 'password_confirmation', 'current_password'];

    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (is_array($body)) {
            $body = $this->sanitizeRecursive($body, 0);
            $request = $request->withParsedBody($body);
        }

        // Also sanitize query params
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams)) {
            $queryParams = $this->sanitizeRecursive($queryParams, 0);
            $request = $request->withQueryParams($queryParams);
        }

        return $next($request);
    }

    /**
     * Recursively sanitize array data.
     *
     * @param array<string, mixed> $data
     * @param int $depth Current recursion depth
     * @return array<string, mixed>
     */
    private function sanitizeRecursive(array $data, int $depth): array
    {
        if ($depth > self::MAX_ARRAY_DEPTH) {
            return []; // Prevent stack overflow attacks
        }

        $sanitized = [];

        foreach ($data as $key => $value) {
            $cleanKey = $this->sanitizeKey($key);

            if ($this->isBypassField($cleanKey)) {
                $sanitized[$cleanKey] = $value;
                continue;
            }

            if ($this->shouldDropValue($value)) {
                continue;
            }

            $sanitized[$cleanKey] = $this->processValue($value, $depth);
        }

        return $sanitized;
    }

    private function isBypassField(string|int $key): bool
    {
        return in_array($key, self::BYPASS_FIELDS, true);
    }

    private function shouldDropValue(mixed $value): bool
    {
        return !is_string($value) 
            && !is_array($value) 
            && !is_numeric($value) 
            && !is_bool($value) 
            && !is_null($value);
    }

    private function processValue(mixed $value, int $depth): mixed
    {
        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        if (is_array($value)) {
            return $this->sanitizeRecursive($value, $depth + 1);
        }

        return $value; // It is a safe scalar (numeric, bool, null)
    }

    /**
     * Sanitize a string value.
     */
    private function sanitizeString(string $value): string
    {
        // Enforce maximum length
        if (strlen($value) > self::MAX_STRING_LENGTH) {
            $value = substr($value, 0, self::MAX_STRING_LENGTH);
        }

        // Trim whitespace
        $value = trim($value);

        // Remove null bytes (common injection vector)
        $value = str_replace("\0", '', $value);

        // Strip HTML tags
        $value = strip_tags($value);

        // Normalize unicode to NFC form (prevents homograph attacks)
        if (function_exists('normalizer_normalize')) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $value = $normalized;
            }
        }

        // Escape special HTML characters
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);

        return $value;
    }

    /**
     * Sanitize array keys.
     */
    private function sanitizeKey(string|int $key): string|int
    {
        if (is_int($key)) {
            return $key;
        }

        // Only allow alphanumeric, underscore, dash
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $key) ?? '';
    }
}
