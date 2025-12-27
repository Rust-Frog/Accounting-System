<?php

declare(strict_types=1);

namespace Api\Middleware;

use Api\Response\JsonResponse;
use Predis\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Rate limiting middleware to prevent API abuse.
 * Uses Redis for distributed rate limiting.
 * Supports endpoint-specific rate limits for security-sensitive operations.
 */
final class RateLimitMiddleware
{
    /**
     * Endpoint-specific rate limits.
     * Format: [pattern => [maxRequests, windowSeconds]]
     */
    private const ENDPOINT_LIMITS = [
        // Authentication: Strict limits to prevent brute force
        '/api/v1/auth/login' => [5, 60],      // 5 attempts per minute
        '/api/v1/auth/register' => [3, 60],   // 3 registrations per minute

        // Reports: Heavy queries, limit to reduce load
        '/reports' => [10, 60],               // 10 reports per minute
        '/trial-balance' => [10, 60],
        '/income-statement' => [10, 60],
        '/balance-sheet' => [10, 60],

        // Transactions: Moderate limits
        '/transactions' => [30, 60],          // 30 transaction operations per minute
    ];

    public function __construct(
        private readonly ClientInterface $redis,
        private readonly int $maxRequests = 100,
        private readonly int $windowSeconds = 60,
    ) {
    }

    /**
     * Process the request and enforce rate limits.
     */
    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        [$limit, $window] = $this->getLimitsForEndpoint($path);

        $clientId = $this->getClientIdentifier($request);
        $endpointKey = $this->getEndpointKey($path);
        $key = "ratelimit:{$endpointKey}:{$clientId}";

        $current = (int) $this->redis->incr($key);

        // Set expiration on first increment
        if ($current === 1) {
            $this->redis->expire($key, $window);
        }

        if ($current > $limit) {
            $ttl = $this->redis->ttl($key);
            return $this->createRateLimitResponse($ttl > 0 ? $ttl : $window, $limit);
        }

        $response = $next($request);

        // Add rate limit headers
        $ttl = $this->redis->ttl($key);
        $remaining = max(0, $limit - $current);
        $resetTime = time() + ($ttl > 0 ? $ttl : 0);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $limit)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) $resetTime);
    }

    /**
     * Get rate limits for a specific endpoint.
     * @return array{0: int, 1: int} [maxRequests, windowSeconds]
     */
    private function getLimitsForEndpoint(string $path): array
    {
        foreach (self::ENDPOINT_LIMITS as $pattern => $limits) {
            if (str_contains($path, $pattern)) {
                return $limits;
            }
        }

        // Default limits
        return [$this->maxRequests, $this->windowSeconds];
    }

    /**
     * Get a normalized key for endpoint grouping.
     */
    private function getEndpointKey(string $path): string
    {
        // Group similar endpoints for rate limiting
        foreach (self::ENDPOINT_LIMITS as $pattern => $limits) {
            if (str_contains($path, $pattern)) {
                return str_replace('/', '_', trim($pattern, '/'));
            }
        }

        return 'default';
    }

    /**
     * Get unique identifier for rate limiting (IP + User ID if available).
     */
    private function getClientIdentifier(ServerRequestInterface $request): string
    {
        $params = $request->getServerParams();
        $ip = $params['HTTP_X_FORWARDED_FOR'] ?? $params['REMOTE_ADDR'] ?? 'unknown';

        // For forwarded IPs, take the first (original client) IP
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $userId = $request->getAttribute('user_id');

        return $userId ? "{$ip}:{$userId}" : $ip;
    }

    /**
     * Create 429 Too Many Requests response.
     */
    private function createRateLimitResponse(int $retryAfter, int $limit): ResponseInterface
    {
        return JsonResponse::error(
            "Rate limit exceeded ({$limit} requests per minute). Please slow down.",
            429,
            ['retry_after' => $retryAfter]
        )->withHeader('Retry-After', (string) $retryAfter);
    }
}
