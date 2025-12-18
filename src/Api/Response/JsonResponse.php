<?php

declare(strict_types=1);

namespace Api\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Simple JSON response implementation.
 * PSR-7 compatible for API responses.
 */
final class JsonResponse implements ResponseInterface
{
    private int $statusCode;
    private string $reasonPhrase;
    /** @var array<string, array<string>> */
    private array $headers;
    private string $body;
    private string $protocolVersion = '1.1';

    private const REASON_PHRASES = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        500 => 'Internal Server Error',
    ];

    /**
     * @param mixed $data Data to encode as JSON
     * @param int $status HTTP status code
     * @param array<string, string|array<string>> $headers Additional headers
     */
    public function __construct(mixed $data, int $status = 200, array $headers = [])
    {
        $this->statusCode = $status;
        $this->reasonPhrase = self::REASON_PHRASES[$status] ?? '';
        $this->body = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        
        $this->headers = [
            'Content-Type' => ['application/json'],
            'Content-Length' => [(string) strlen($this->body)],
        ];

        foreach ($headers as $name => $value) {
            $this->headers[$name] = is_array($value) ? $value : [$value];
        }
    }

    /**
     * Create a success response.
     */
    public static function success(mixed $data, int $status = 200): self
    {
        return new self([
            'success' => true,
            'data' => $data,
            'meta' => self::generateMeta(),
        ], $status);
    }

    /**
     * Create an error response.
     */
    public static function error(
        string $message,
        int $status = 400,
        ?array $details = null,
        string $code = 'ERROR'
    ): self {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return new self([
            'success' => false,
            'error' => $error,
            'meta' => self::generateMeta(),
        ], $status);
    }

    /**
     * Create a created response with location header.
     */
    public static function created(mixed $data, ?string $location = null): self
    {
        $headers = $location !== null ? ['Location' => $location] : [];
        return new self([
            'success' => true,
            'data' => $data,
            'meta' => self::generateMeta(),
        ], 201, $headers);
    }

    /**
     * Create a no content response.
     */
    public static function noContent(): self
    {
        return new self(null, 204);
    }

    /**
     * Create a validation error response (422).
     *
     * @param array<string, string[]> $errors Field-level validation errors
     */
    public static function validationError(array $errors): self
    {
        return new self([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'details' => $errors,
            ],
            'meta' => self::generateMeta(),
        ], 422);
    }

    /**
     * Generate standard metadata.
     * @return array{timestamp: string, requestId: string}
     */
    private static function generateMeta(): array
    {
        return [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'requestId' => uniqid('req_', true),
        ];
    }

    // PSR-7 ResponseInterface implementation

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase ?: (self::REASON_PHRASES[$code] ?? '');
        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): ResponseInterface
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

    public function getBody(): StreamInterface
    {
        return new StringStream($this->body);
    }

    public function withBody(StreamInterface $body): ResponseInterface
    {
        $clone = clone $this;
        $clone->body = (string) $body;
        return $clone;
    }

    /**
     * Send the response to the client.
     */
    public function send(): void
    {
        // Send status line
        http_response_code($this->statusCode);

        // Send headers
        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                header("$name: $value", false);
            }
        }

        // Send body
        echo $this->body;
    }
}
