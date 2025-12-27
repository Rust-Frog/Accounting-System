<?php

declare(strict_types=1);

namespace Infrastructure\Service;

use DateTimeImmutable;
use Domain\Identity\ValueObject\UserId;

/**
 * JWT (JSON Web Token) service for stateless authentication.
 * Uses HMAC SHA-256 for signing tokens.
 */
final class JwtService
{
    private const HEADER = ['alg' => 'HS256', 'typ' => 'JWT'];

    public function __construct(
        private readonly string $secretKey,
        private readonly int $expirationSeconds = 3600, // 1 hour default
        private readonly string $issuer = 'accounting-api',
    ) {
    }

    /**
     * Create a JWT token for a user.
     */
    public function createToken(UserId $userId, array $claims = []): string
    {
        $now = new DateTimeImmutable();
        $expiration = $now->modify("+{$this->expirationSeconds} seconds");

        $payload = array_merge([
            'iss' => $this->issuer,
            'sub' => $userId->toString(),
            'iat' => $now->getTimestamp(),
            'exp' => $expiration->getTimestamp(),
        ], $claims);

        return $this->encode($payload);
    }

    /**
     * Validate and decode a JWT token.
     *
     * @return array{sub: string, exp: int, iat: int, iss: string}|null Payload or null if invalid
     */
    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Verify signature
        $expectedSignature = $this->sign("$headerB64.$payloadB64");
        if (!hash_equals($expectedSignature, $this->base64UrlDecode($signatureB64))) {
            return null;
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            return null;
        }

        // Check expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        // Check issuer
        if (!isset($payload['iss']) || $payload['iss'] !== $this->issuer) {
            return null;
        }

        return $payload;
    }

    /**
     * Extract user ID from a valid token.
     */
    public function getUserIdFromToken(string $token): ?UserId
    {
        $payload = $this->validateToken($token);
        if ($payload === null || !isset($payload['sub'])) {
            return null;
        }

        try {
            return UserId::fromString($payload['sub']);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Encode payload into JWT format.
     */
    private function encode(array $payload): string
    {
        $headerB64 = $this->base64UrlEncode(json_encode(self::HEADER));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload));
        $signatureB64 = $this->base64UrlEncode($this->sign("$headerB64.$payloadB64"));

        return "$headerB64.$payloadB64.$signatureB64";
    }

    /**
     * Create HMAC signature.
     */
    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secretKey, true);
    }

    /**
     * Base64 URL-safe encoding.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decoding.
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
