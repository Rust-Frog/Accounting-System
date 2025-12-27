<?php

declare(strict_types=1);

namespace Domain\Ledger\Repository;

use Domain\Company\ValueObject\CompanyId;
use Domain\Ledger\Entity\JournalEntry;
use Domain\Shared\ValueObject\HashChain\ContentHash;

interface JournalEntryRepositoryInterface
{
    public function save(JournalEntry $entry): void;

    public function findById(string $id): ?JournalEntry;

    /**
     * @return array<JournalEntry>
     */
    public function findByCompany(CompanyId $companyId, int $limit = 100): array;

    /**
     * @return array<JournalEntry>
     */
    public function findByCompanyPaginated(CompanyId $companyId, int $page, int $perPage): array;

    public function countByCompany(CompanyId $companyId): int;

    public function getLatestHash(CompanyId $companyId): ?ContentHash;
}

