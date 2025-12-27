<?php

declare(strict_types=1);

namespace Api\Request;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Api\Response\StringStream;

/**
 * Simple ServerRequest implementation for PSR-7.
 */
final class ServerRequest implements ServerRequestInterface
{
    /** @var array<string, array<string>> */
    private array $headers = [];
    /** @var array<string, mixed> */
    private array $attributes = [];
    /** @var array<string, string> */
    private array $queryParams = [];
    /** @var array<string, mixed>|object|null */
    private array|object|null $parsedBody = null;
    /** @var array<string, mixed> */
    private array $serverParams;
    /** @var array<string, mixed> */
    private array $cookieParams = [];
    /** @var array<mixed> */
    private array $uploadedFiles = [];

    private string $method;
    private UriInterface $uri;
    private StreamInterface $body;
    private string $protocolVersion = '1.1';
    private string $requestTarget = '';

    /**
     * Create from PHP globals.
     */
    public static function fromGlobals(): self
    {
        $request = new self();
        $request->serverParams = $_SERVER;
        $request->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $request->queryParams = $_GET;
        $request->parsedBody = $_POST;
        $request->cookieParams = $_COOKIE;
        $request->uploadedFiles = $_FILES;

        // Parse headers from $_SERVER
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $request->headers[ucwords(strtolower($name), '-')] = [(string) $value];
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $request->headers['Content-Type'] = [$_SERVER['CONTENT_TYPE']];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $request->headers['Content-Length'] = [$_SERVER['CONTENT_LENGTH']];
        }

        // Build URI
        $request->uri = Uri::fromGlobals();

        // Read body
        $rawBody = file_get_contents('php://input') ?: '';
        $request->body = new StringStream($rawBody);

        // Parse JSON body if applicable
        $contentType = $request->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json') && $rawBody !== '') {
            $request->parsedBody = json_decode($rawBody, true);
        }

        return $request;
    }

    // PSR-7 ServerRequestInterface implementation

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;
        return $clone;
    }

    public function getParsedBody(): array|object|null
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withoutAttribute(string $name): ServerRequestInterface
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }

    // PSR-7 RequestInterface implementation

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== '') {
            return $this->requestTarget;
        }
        $target = $this->uri->getPath();
        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }
        return $target ?: '/';
    }

    public function withRequestTarget(string $requestTarget): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->uri = $uri;
        return $clone;
    }

    // PSR-7 MessageInterface implementation

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    public function getHeader(string $name): array
    {
        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->headers[$name] = is_array($value) ? $value : [$value];
        return $clone;
    }

    public function withAddedHeader(string $name, $value): ServerRequestInterface
    {
        $clone = clone $this;
        $existing = $clone->headers[$name] ?? [];
        $clone->headers[$name] = array_merge($existing, is_array($value) ? $value : [$value]);
        return $clone;
    }

    public function withoutHeader(string $name): ServerRequestInterface
    {
        $clone = clone $this;
        unset($clone->headers[$name]);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }
}
