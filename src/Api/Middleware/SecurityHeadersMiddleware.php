<?php

declare(strict_types=1);

namespace Api\Middleware;

use Api\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Security headers middleware for defense in depth.
 *
 * Implements:
 * - Content Security Policy (CSP)
 * - Request size limits
 * - Additional security headers (X-Content-Type-Options, X-Frame-Options, etc.)
 */
final class SecurityHeadersMiddleware
{
    /**
     * Maximum request body size in bytes (1MB default).
     */
    private const MAX_BODY_SIZE = 1024 * 1024;

    /**
     * Endpoints that allow larger payloads (e.g., file uploads).
     */
    private const LARGE_PAYLOAD_ENDPOINTS = [
        '/api/v1/reports/generate',
    ];

    /**
     * Maximum size for large payload endpoints (5MB).
     */
    private const MAX_LARGE_BODY_SIZE = 5 * 1024 * 1024;

    public function __construct(
        private readonly int $maxBodySize = self::MAX_BODY_SIZE,
    ) {
    }

    /**
     * Process the request and add security headers to response.
     */
    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Check request size limit
        $sizeError = $this->checkRequestSize($request);
        if ($sizeError !== null) {
            return $sizeError;
        }

        // Process request
        $response = $next($request);

        // Add security headers to response
        return $this->addSecurityHeaders($response);
    }

    /**
     * Check if request body exceeds size limit.
     */
    private function checkRequestSize(ServerRequestInterface $request): ?ResponseInterface
    {
        $contentLength = $request->getHeaderLine('Content-Length');

        if ($contentLength === '') {
            return null; // No body or chunked encoding
        }

        $size = (int) $contentLength;
        $maxSize = $this->getMaxSizeForEndpoint($request->getUri()->getPath());

        if ($size > $maxSize) {
            $maxSizeMb = round($maxSize / 1024 / 1024, 1);
            return JsonResponse::error(
                "Request body too large. Maximum size is {$maxSizeMb}MB.",
                413 // Payload Too Large
            );
        }

        return null;
    }

    /**
     * Get maximum body size for endpoint.
     */
    private function getMaxSizeForEndpoint(string $path): int
    {
        foreach (self::LARGE_PAYLOAD_ENDPOINTS as $endpoint) {
            if (str_contains($path, $endpoint)) {
                return self::MAX_LARGE_BODY_SIZE;
            }
        }

        return $this->maxBodySize;
    }

    /**
     * Add security headers to response.
     */
    private function addSecurityHeaders(ResponseInterface $response): ResponseInterface
    {
        return $response
            // Content Security Policy - restrict resource loading
            ->withHeader('Content-Security-Policy', $this->buildCspHeader())

            // Prevent MIME type sniffing
            ->withHeader('X-Content-Type-Options', 'nosniff')

            // Prevent clickjacking
            ->withHeader('X-Frame-Options', 'DENY')

            // XSS Protection (legacy browsers)
            ->withHeader('X-XSS-Protection', '1; mode=block')

            // Referrer Policy - limit referrer information
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')

            // Permissions Policy - disable unnecessary features
            ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()')

            // Cache control for API responses
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    /**
     * Build Content Security Policy header value.
     */
    private function buildCspHeader(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'", // Allow inline styles for UI frameworks
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
        ];

        return implode('; ', $directives);
    }
}
