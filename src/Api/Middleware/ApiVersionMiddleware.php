<?php

declare(strict_types=1);

namespace Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Middleware to add API version header to every response.
 */
final class ApiVersionMiddleware
{
    private const VERSION = '1.0.0';

    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $response = $next($request);
        return $response->withHeader('X-API-Version', self::VERSION);
    }
}
