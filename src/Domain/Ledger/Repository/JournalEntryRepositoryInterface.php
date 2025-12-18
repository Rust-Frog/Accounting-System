<?php

declare(strict_types=1);

namespace Domain\Ledger\Repository;

use Domain\Company\ValueObject\CompanyId;
use Domain\Ledger\Entity\JournalEntry;
use Domain\Shared\ValueObject\HashChain\ContentHash;

interface JournalEntryRepositoryInterface
{
    public function save(JournalEntry $entry): void;

    /**
     * @return array<JournalEntry>
     */
    public function findByCompany(CompanyId $companyId, int $limit = 100): array;

    public function getLatestHash(CompanyId $companyId): ?ContentHash;
}
