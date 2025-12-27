<?php

declare(strict_types=1);

namespace Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Request ID Middleware.
 * 
 * Generates a unique request ID for each request and:
 * - Attaches it to the request as an attribute
 * - Adds it as X-Request-ID response header
 * - Makes it available for logging and error responses
 */
final class RequestIdMiddleware
{
    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        // Check if client passed their own correlation ID
        $requestId = $request->getHeaderLine('X-Request-ID');
        
        if (empty($requestId)) {
            $requestId = $this->generateRequestId();
        }
        
        // Attach to request for use by other middleware/controllers
        $request = $request->withAttribute('request_id', $requestId);
        
        // Store globally for JsonResponse to access
        $_SERVER['REQUEST_ID'] = $requestId;
        
        // Process request
        $response = $next($request);
        
        // Add request ID to response headers
        return $response->withHeader('X-Request-ID', $requestId);
    }
    
    private function generateRequestId(): string
    {
        return sprintf(
            'req_%s_%s',
            date('YmdHis'),
            bin2hex(random_bytes(8))
        );
    }
}
