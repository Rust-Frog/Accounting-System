<?php

declare(strict_types=1);

namespace Api;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Simple PSR-7 compatible router using FastRoute.
 * Lightweight routing without external framework dependencies.
 */
final class Router
{
    /** @var array<array{method: string, path: string, handler: callable}> */
    private array $routes = [];

    /** @var array<callable> */
    private array $middleware = [];

    /**
     * Add a GET route.
     */
    public function get(string $path, callable $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Add a POST route.
     */
    public function post(string $path, callable $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Add a PUT route.
     */
    public function put(string $path, callable $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Add a DELETE route.
     */
    public function delete(string $path, callable $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Add a PATCH route.
     */
    public function patch(string $path, callable $handler): self
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * Add a route with method, path, and handler.
     */
    public function addRoute(string $method, string $path, callable $handler): self
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
        ];
        return $this;
    }

    /**
     * Add middleware to the pipeline.
     */
    public function addMiddleware(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Dispatch the request through middleware and routes.
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        // Define the core application logic (Routing) as the final handler
        $coreHandler = function (ServerRequestInterface $request): ResponseInterface {
            $method = $request->getMethod();
            $path = $request->getUri()->getPath();

            // Find matching route
            foreach ($this->routes as $route) {
                $params = $this->matchRoute($route['path'], $path);
                if ($route['method'] === $method && $params !== null) {
                    // Add route params to request
                    foreach ($params as $key => $value) {
                        $request = $request->withAttribute($key, $value);
                    }

                    // Execute the route handler
                    return ($route['handler'])($request);
                }
            }

            // No route found - return 404
            return $this->notFoundResponse();
        };

        // Build middleware pipeline wrapping the core handler
        $pipeline = $this->buildPipeline($coreHandler);

        return $pipeline($request);
    }

    /**
     * Match a route pattern against a path.
     * 
     * @return array<string, string>|null Route params if matched, null otherwise
     */
    private function matchRoute(string $pattern, string $path): ?array
    {
        // Convert route pattern to regex
        // e.g., /users/{id} becomes /users/([^/]+)
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            // Filter to only named captures (route params)
            return array_filter($matches, fn($key) => is_string($key), ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    /**
     * Build middleware pipeline with handler at the end.
     */
    private function buildPipeline(callable $handler): callable
    {
        $pipeline = fn(ServerRequestInterface $request): ResponseInterface => $handler($request);

        // Wrap handler in middleware (reverse order)
        foreach (array_reverse($this->middleware) as $middleware) {
            $next = $pipeline;
            $pipeline = fn(ServerRequestInterface $request): ResponseInterface => 
                $middleware($request, $next);
        }

        return $pipeline;
    }

    /**
     * Create a 404 Not Found response.
     */
    private function notFoundResponse(): ResponseInterface
    {
        return new \Api\Response\JsonResponse(
            ['error' => 'Not Found', 'message' => 'The requested resource was not found'],
            404
        );
    }

    /**
     * Get all registered routes (for debugging/documentation).
     * 
     * @return array<array{method: string, path: string}>
     */
    public function getRoutes(): array
    {
        return array_map(
            fn(array $route) => ['method' => $route['method'], 'path' => $route['path']],
            $this->routes
        );
    }
}
