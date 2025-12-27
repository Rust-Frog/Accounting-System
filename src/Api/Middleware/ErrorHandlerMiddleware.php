<?php

declare(strict_types=1);

namespace Api\Middleware;

use Api\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Error handler middleware for catching exceptions and returning JSON error responses.
 */
final class ErrorHandlerMiddleware
{
    private bool $debug;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Process the request and catch any exceptions.
     */
    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Handle exception and return appropriate JSON response.
     */
    private function handleException(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $statusCode = $this->mapExceptionToStatusCode($e);
        $message = $this->getErrorMessage($e);

        $payload = [
            'status' => 'error',
            'message' => $message,
            'code' => $statusCode,
        ];

        // Log exception details to server log
        error_log(sprintf(
            "Exception: %s in %s:%d\nStack trace: %s",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        // Add request ID if present
        $requestId = $request->getAttribute('request_id');
        if ($requestId !== null) {
            $payload['request_id'] = $requestId;
        }

        return (new JsonResponse($payload, $statusCode))
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache');
    }

    /**
     * Map exception type to HTTP status code.
     */
    private function mapExceptionToStatusCode(Throwable $e): int
    {
        $class = get_class($e);

        // Domain exceptions
        return match (true) {
            str_contains($class, 'NotFound') => 404,
            str_contains($class, 'InvalidArgument') => 400,
            str_contains($class, 'Validation') => 422,
            str_contains($class, 'Unauthorized') => 401,
            str_contains($class, 'Forbidden') => 403,
            str_contains($class, 'Conflict') => 409,
            str_contains($class, 'DomainException') => 400,
            default => 500,
        };
    }

    /**
     * Get user-facing error message.
     */
    private function getErrorMessage(Throwable $e): string
    {
        $statusCode = $this->mapExceptionToStatusCode($e);

        // Production: Mask execution/internal errors (5xx)
        if ($statusCode >= 500) {
            return 'An internal error occurred. Please try again later.';
        }

        // Use generic messages for mapped 4xx errors
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            default => 'An error occurred',
        };
    }
}
