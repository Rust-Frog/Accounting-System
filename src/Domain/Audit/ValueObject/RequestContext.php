<?php

declare(strict_types=1);

namespace Domain\Audit\ValueObject;

use DateTimeImmutable;

/**
 * Value object capturing HTTP request context for audit.
 */
final readonly class RequestContext
{
    public function __construct(
        private ?string $ipAddress,
        private ?string $userAgent,
        private ?string $sessionId,
        private ?string $requestId,
        private ?string $correlationId,
        private ?string $endpoint,
        private ?string $httpMethod,
        private DateTimeImmutable $timestamp
    ) {
    }

    public static function empty(): self
    {
        return new self(null, null, null, null, null, null, null, new DateTimeImmutable());
    }

    public static function fromRequest(
        string $ipAddress,
        string $userAgent,
        ?string $sessionId = null,
        ?string $requestId = null,
        ?string $correlationId = null,
        ?string $endpoint = null,
        ?string $httpMethod = null
    ): self {
        return new self(
            $ipAddress,
            $userAgent,
            $sessionId,
            $requestId,
            $correlationId,
            $endpoint,
            $httpMethod,
            new DateTimeImmutable()
        );
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }

    public function endpoint(): ?string
    {
        return $this->endpoint;
    }

    public function httpMethod(): ?string
    {
        return $this->httpMethod;
    }

    public function timestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'session_id' => $this->sessionId,
            'request_id' => $this->requestId,
            'correlation_id' => $this->correlationId,
            'endpoint' => $this->endpoint,
            'http_method' => $this->httpMethod,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
        ];
    }
}
