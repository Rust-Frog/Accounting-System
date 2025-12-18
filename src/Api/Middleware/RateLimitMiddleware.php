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
 */
final class RateLimitMiddleware
{
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
        $clientId = $this->getClientIdentifier($request);
        $key = "ratelimit:{$clientId}";
        
        $current = (int) $this->redis->incr($key);
        
        // Set expiration on first increment
        if ($current === 1) {
            $this->redis->expire($key, $this->windowSeconds);
        }

        if ($current > $this->maxRequests) {
            $ttl = $this->redis->ttl($key);
            return $this->createRateLimitResponse($ttl > 0 ? $ttl : $this->windowSeconds);
        }

        $response = $next($request);
        
        // Add headers
        $ttl = $this->redis->ttl($key);
        $remaining = max(0, $this->maxRequests - $current);
        $resetTime = time() + ($ttl > 0 ? $ttl : 0);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) $resetTime);
    }

    /**
     * Get unique identifier for rate limiting (IP + User ID if available).
     */
    private function getClientIdentifier(ServerRequestInterface $request): string
    {
        // Try mapped IP first (e.g. from load balancer), then REMOTE_ADDR
        $params = $request->getServerParams();
        $ip = $params['HTTP_X_FORWARDED_FOR'] ?? $params['REMOTE_ADDR'] ?? 'unknown';
        
        $userId = $request->getAttribute('user_id');

        return $userId ? "{$ip}:{$userId}" : $ip;
    }

    /**
     * Create 429 Too Many Requests response.
     */
    private function createRateLimitResponse(int $retryAfter): ResponseInterface
    {
        return JsonResponse::error(
            'Rate limit exceeded. Please slow down.',
            429,
            ['retry_after' => $retryAfter]
        )->withHeader('Retry-After', (string) $retryAfter);
    }
}
