<?php

declare(strict_types=1);

namespace Api\Controller\Traits;

use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\Exception\DomainException;
use Domain\Shared\Exception\EntityNotFoundException;
use Domain\Shared\Exception\InvalidArgumentException;
use Domain\Shared\Exception\ValidationException;
use Throwable;

/**
 * Trait for safely handling exceptions in controllers.
 *
 * Only domain exceptions have user-safe messages.
 * Infrastructure exceptions (PDO, Runtime, etc.) are masked
 * to prevent information disclosure.
 */
trait SafeExceptionHandlerTrait
{
    /**
     * Get a safe error message from an exception.
     *
     * Domain exceptions have user-friendly messages.
     * Other exceptions return a generic message to prevent info leakage.
     */
    protected function getSafeErrorMessage(Throwable $e): string
    {
        // Domain exceptions have user-safe messages
        if ($this->isDomainException($e)) {
            return $e->getMessage();
        }

        // Log the actual error for debugging
        error_log(sprintf(
            "[Controller Error] %s: %s in %s:%d",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        // Return generic message to prevent information disclosure
        return 'An error occurred while processing your request.';
    }

    /**
     * Get appropriate HTTP status code for an exception.
     */
    protected function getExceptionStatusCode(Throwable $e): int
    {
        return match (true) {
            $e instanceof EntityNotFoundException => 404,
            $e instanceof ValidationException => 422,
            $e instanceof InvalidArgumentException => 400,
            $e instanceof BusinessRuleException => 400,
            $e instanceof DomainException => 400,
            default => 500,
        };
    }

    /**
     * Check if exception is a domain exception (safe to expose message).
     */
    private function isDomainException(Throwable $e): bool
    {
        return $e instanceof DomainException
            || $e instanceof BusinessRuleException
            || $e instanceof ValidationException
            || $e instanceof EntityNotFoundException
            || $e instanceof InvalidArgumentException;
    }
}
