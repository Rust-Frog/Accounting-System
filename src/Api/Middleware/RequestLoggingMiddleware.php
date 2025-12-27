<?php

declare(strict_types=1);

namespace Api\Middleware;

use Domain\Audit\Service\SystemActivityService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Middleware for logging API requests and responses.
 * Useful for debugging and audit trails.
 */
final class RequestLoggingMiddleware
{
    public function __construct(
        private readonly ?SystemActivityService $activityService = null
    ) {
    }

    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $startTime = microtime(true);
        $requestId = $request->getAttribute('request_id') ?? uniqid('req_', true);

        // Process request
        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Filter out technical/noisy paths
        $path = $request->getUri()->getPath();
        $excludedPaths = ['/favicon.ico', '/health', '/api/v1/health', '/api/v1/auth/me', '/api/v1/dashboard/stats', '/api/v1/activities'];
        foreach ($excludedPaths as $excluded) {
            if (str_contains($path, $excluded)) {
                return $response;
            }
        }

        // Only log significant events:
        // 1. Errors (>= 400)
        // 2. Mutations (non-GET) that aren't typical successes 
        // (Typical successes like 201 Created for a Company are already logged by the Controller)
        $status = $response->getStatusCode();
        if ($status >= 400 || ($request->getMethod() !== 'GET' && !in_array($status, [200, 201, 204]))) {
            $this->logActivity($request, $response, $duration, $requestId);
        }

        return $response;
    }

    private function logActivity(
        ServerRequestInterface $request,
        ResponseInterface $response,
        float $duration,
        string $requestId
    ): void {
        if ($this->activityService === null) {
            return;
        }

        $path = $request->getUri()->getPath();
        $method = $request->getMethod();
        $status = $response->getStatusCode();
        $userId = $request->getAttribute('user_id');

        $this->activityService->log(
            activityType: 'system.api_request',
            entityType: 'api',
            entityId: $requestId,
            description: "API {$method} {$path} returned {$status} in {$duration}ms",
            severity: $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info'),
            metadata: [
                'method' => $method,
                'path' => $path,
                'status' => $status,
                'duration_ms' => $duration,
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                'user_id' => $userId,
            ]
        );
    }
}
