<?php

declare(strict_types=1);

namespace Application\Dto\Account;

use Application\Dto\DtoInterface;

/**
 * DTO representing an account for external consumption.
 */
final readonly class AccountDto implements DtoInterface
{
    public function __construct(
        public string $id,
        public string $companyId,
        public string $code,
        public string $name,
        public string $type,
        public string $normalBalance,
        public bool $isActive,
        public ?string $parentAccountId,
        public ?string $description,
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
            'company_id' => $this->companyId,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'normal_balance' => $this->normalBalance,
            'is_active' => $this->isActive,
            'parent_account_id' => $this->parentAccountId,
            'description' => $this->description,
            'created_at' => $this->createdAt,
        ];
    }
}
