<?php

declare(strict_types=1);

namespace Infrastructure\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Simple PSR-11 compatible dependency injection container.
 */
final class Container implements ContainerInterface
{
    /** @var array<string, callable> */
    private array $factories = [];
    
    /** @var array<string, object> */
    private array $instances = [];

    /**
     * Register a factory for creating instances.
     */
    public function set(string $id, callable $factory): self
    {
        $this->factories[$id] = $factory;
        return $this;
    }

    /**
     * Register a singleton instance.
     */
    public function singleton(string $id, callable $factory): self
    {
        $this->factories[$id] = function (ContainerInterface $c) use ($id, $factory) {
            if (!isset($this->instances[$id])) {
                $this->instances[$id] = $factory($c);
            }
            return $this->instances[$id];
        };
        return $this;
    }

    /**
     * Register an existing instance.
     */
    public function instance(string $id, object $instance): self
    {
        $this->instances[$id] = $instance;
        $this->factories[$id] = fn() => $instance;
        return $this;
    }

    /**
     * Get an entry from the container.
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class("No entry found for: $id") extends \Exception implements NotFoundExceptionInterface {};
        }

        return $this->factories[$id]($this);
    }

    /**
     * Check if the container has an entry.
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
