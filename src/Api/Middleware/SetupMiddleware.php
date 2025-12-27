<?php

declare(strict_types=1);

namespace Api\Middleware;

use Api\Response\JsonResponse;
use Domain\Identity\Repository\UserRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SetupMiddleware
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Only enforce on setup routes
        if (str_starts_with($path, '/api/v1/setup')) {
            // Check if system is already initialized
            if ($this->userRepository->hasAnyAdmin()) {
                return JsonResponse::error('System is already initialized', 403);
            }
        }

        return $next($request);
    }
}
