<?php

declare(strict_types=1);

namespace Api\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * PDF file download response.
 */
final class PdfResponse implements ResponseInterface
{
    private int $statusCode = 200;
    private string $reasonPhrase = 'OK';
    private array $headers;
    private string $body;
    private string $protocolVersion = '1.1';

    public function __construct(string $content, string $filename)
    {
        $this->body = $content;
        $this->headers = [
            'Content-Type' => ['application/pdf'],
            'Content-Disposition' => ['attachment; filename="' . $filename . '"'],
            'Content-Length' => [(string) strlen($content)],
            'Cache-Control' => ['no-cache, no-store, must-revalidate'],
            'Pragma' => ['no-cache'],
            'Expires' => ['0'],
        ];
    }

    public function getStatusCode(): int { return $this->statusCode; }
    public function getReasonPhrase(): string { return $this->reasonPhrase; }
    public function getProtocolVersion(): string { return $this->protocolVersion; }
    public function getHeaders(): array { return $this->headers; }
    public function hasHeader(string $name): bool { return isset($this->headers[$name]); }
    public function getHeader(string $name): array { return $this->headers[$name] ?? []; }
    public function getHeaderLine(string $name): string { return implode(', ', $this->getHeader($name)); }
    public function getBody(): StreamInterface { return new StringStream($this->body); }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase ?: 'OK';
        return $clone;
    }

    public function withProtocolVersion(string $version): ResponseInterface
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    public function withHeader(string $name, $value): ResponseInterface
    {
        $clone = clone $this;
        $clone->headers[$name] = is_array($value) ? $value : [$value];
        return $clone;
    }

    public function withAddedHeader(string $name, $value): ResponseInterface
    {
        $clone = clone $this;
        $existing = $clone->headers[$name] ?? [];
        $clone->headers[$name] = array_merge($existing, is_array($value) ? $value : [$value]);
        return $clone;
    }

    public function withoutHeader(string $name): ResponseInterface
    {
        $clone = clone $this;
        unset($clone->headers[$name]);
        return $clone;
    }

    public function withBody(StreamInterface $body): ResponseInterface
    {
        $clone = clone $this;
        $clone->body = (string) $body;
        return $clone;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                header("$name: $value", false);
            }
        }
        echo $this->body;
    }
}
