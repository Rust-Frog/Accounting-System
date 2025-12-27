<?php

declare(strict_types=1);

namespace Api\Request;

use Psr\Http\Message\UriInterface;

/**
 * Simple URI implementation for PSR-7.
 */
final class Uri implements UriInterface
{
    private string $scheme = '';
    private string $host = '';
    private ?int $port = null;
    private string $path = '';
    private string $query = '';
    private string $fragment = '';
    private string $userInfo = '';

    /**
     * Create from PHP globals.
     */
    public static function fromGlobals(): self
    {
        $uri = new self();

        $uri->scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $uri->host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        
        // Handle port
        if (isset($_SERVER['SERVER_PORT'])) {
            $port = (int) $_SERVER['SERVER_PORT'];
            if (($uri->scheme === 'http' && $port !== 80) || ($uri->scheme === 'https' && $port !== 443)) {
                $uri->port = $port;
            }
        }

        // Parse REQUEST_URI
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        if (($pos = strpos($requestUri, '?')) !== false) {
            $uri->path = substr($requestUri, 0, $pos);
            $uri->query = substr($requestUri, $pos + 1);
        } else {
            $uri->path = $requestUri;
        }

        return $uri;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }
        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }
        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): UriInterface
    {
        $clone = clone $this;
        $clone->scheme = strtolower($scheme);
        return $clone;
    }

    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        $clone = clone $this;
        $clone->userInfo = $password !== null ? "$user:$password" : $user;
        return $clone;
    }

    public function withHost(string $host): UriInterface
    {
        $clone = clone $this;
        $clone->host = strtolower($host);
        return $clone;
    }

    public function withPort(?int $port): UriInterface
    {
        $clone = clone $this;
        $clone->port = $port;
        return $clone;
    }

    public function withPath(string $path): UriInterface
    {
        $clone = clone $this;
        $clone->path = $path;
        return $clone;
    }

    public function withQuery(string $query): UriInterface
    {
        $clone = clone $this;
        $clone->query = ltrim($query, '?');
        return $clone;
    }

    public function withFragment(string $fragment): UriInterface
    {
        $clone = clone $this;
        $clone->fragment = ltrim($fragment, '#');
        return $clone;
    }

    public function __toString(): string
    {
        $uri = '';
        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }
        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }
        $uri .= $this->path;
        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }
        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }
        return $uri;
    }
}
