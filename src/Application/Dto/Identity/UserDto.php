<?php

declare(strict_types=1);

namespace Application\Dto\Identity;

use Application\Dto\DtoInterface;

/**
 * DTO representing a user for external consumption.
 */
final readonly class UserDto implements DtoInterface
{
    public function __construct(
        public string $id,
        public string $username,
        public string $email,
        public string $firstName,
        public string $lastName,
        public string $role,
        public string $status,
        public ?string $companyId,
        public string $createdAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'role' => $this->role,
            'status' => $this->status,
            'company_id' => $this->companyId,
            'created_at' => $this->createdAt,
        ];
    }
}
