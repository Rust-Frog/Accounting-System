<?php

declare(strict_types=1);

namespace Application\Dto\Identity;

use Application\Dto\DtoInterface;

/**
 * DTO representing authentication result.
 */
final readonly class AuthenticationResultDto implements DtoInterface
{
    public function __construct(
        public bool $success,
        public ?string $token = null,
        public ?UserDto $user = null,
        public ?string $errorMessage = null,
    ) {
    }

    public static function success(string $token, UserDto $user): self
    {
        return new self(
            success: true,
            token: $token,
            user: $user,
        );
    }

    public static function failure(string $errorMessage): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'token' => $this->token,
            'user' => $this->user?->toArray(),
            'error_message' => $this->errorMessage,
        ];
    }
}
