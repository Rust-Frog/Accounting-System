<?php

declare(strict_types=1);

namespace Api\Middleware;

use Api\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CORS middleware for handling cross-origin requests.
 */
final class CorsMiddleware
{
    /** @var array<string> */
    private array $allowedOrigins;
    /** @var array<string> */
    private array $allowedMethods;
    /** @var array<string> */
    private array $allowedHeaders;
    private bool $allowCredentials;

    /**
     * @param array<string> $allowedOrigins
     * @param array<string> $allowedMethods
     * @param array<string> $allowedHeaders
     */
    public function __construct(
        array $allowedOrigins = ['*'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Request-ID'],
        bool $allowCredentials = false
    ) {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
        $this->allowCredentials = $allowCredentials;
    }

    /**
     * Process the request through CORS middleware.
     */
    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflight($request);
        }

        // Process regular request and add CORS headers
        $response = $next($request);
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle preflight OPTIONS request.
     */
    private function handlePreflight(ServerRequestInterface $request): ResponseInterface
    {
        $response = new JsonResponse(null, 204);
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Add CORS headers to response.
     */
    private function addCorsHeaders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // Check if origin is allowed
        if ($origin !== '' && $this->isOriginAllowed($origin)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        } elseif (in_array('*', $this->allowedOrigins, true)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        }

        $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
        $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Check if origin is in allowed list.
     */
    private function isOriginAllowed(string $origin): bool
    {
        return in_array($origin, $this->allowedOrigins, true) || in_array('*', $this->allowedOrigins, true);
    }
}
