<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Ledger\Entity\JournalEntry;
use Domain\Ledger\Repository\JournalEntryRepositoryInterface;
use Domain\Shared\ValueObject\HashChain\ChainLink;
use Domain\Shared\ValueObject\HashChain\ContentHash;
use Domain\Transaction\ValueObject\TransactionId;
use PDO;

final class MysqlJournalEntryRepository extends AbstractMysqlRepository implements JournalEntryRepositoryInterface
{
    public function save(JournalEntry $entry): void
    {
        $sql = <<<SQL
            INSERT INTO journal_entries (
                id, company_id, transaction_id, entry_type, bookings_json, 
                occurred_at, content_hash, previous_hash, chain_hash
            ) VALUES (
                :id, :company_id, :transaction_id, :entry_type, :bookings_json,
                :occurred_at, :content_hash, :previous_hash, :chain_hash
            )
        SQL;

        $params = [
            'id' => $entry->id,
            'company_id' => $entry->companyId->toString(),
            'transaction_id' => $entry->transactionId->toString(),
            'entry_type' => $entry->entryType,
            'bookings_json' => json_encode($entry->bookings),
            'occurred_at' => $entry->occurredAt->format('Y-m-d H:i:s.u'),
            'content_hash' => $entry->contentHash->toString(),
            'previous_hash' => $entry->previousHash?->toString(),
            'chain_hash' => $entry->getChainHash()?->toString(),
        ];

        $this->execute($sql, $params);
    }

    public function findByCompany(CompanyId $companyId, int $limit = 100): array
    {
        $rows = $this->fetchAll(
            'SELECT * FROM journal_entries WHERE company_id = :company_id ORDER BY occurred_at DESC LIMIT :limit',
            [
                'company_id' => $companyId->toString(),
                'limit' => $limit
            ]
        );

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function getLatestHash(CompanyId $companyId): ?ContentHash
    {
        $row = $this->fetchOne(
            'SELECT chain_hash, content_hash FROM journal_entries WHERE company_id = :company_id ORDER BY occurred_at DESC LIMIT 1',
            ['company_id' => $companyId->toString()]
        );

        if ($row === null) {
            return null;
        }

        if (!empty($row['chain_hash'])) {
            return ContentHash::fromContent($row['chain_hash']);
        }

        // Fallback to content_hash for Genesis entry to maintain chain continuity
        return ContentHash::fromContent($row['content_hash']);
    }

    public function findById(string $id): ?JournalEntry
    {
        $row = $this->fetchOne(
            'SELECT * FROM journal_entries WHERE id = :id',
            ['id' => $id]
        );

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByCompanyPaginated(CompanyId $companyId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        
        $rows = $this->fetchAll(
            'SELECT * FROM journal_entries WHERE company_id = :company_id ORDER BY occurred_at DESC LIMIT :limit OFFSET :offset',
            [
                'company_id' => $companyId->toString(),
                'limit' => $perPage,
                'offset' => $offset,
            ]
        );

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function countByCompany(CompanyId $companyId): int
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) as total FROM journal_entries WHERE company_id = :company_id',
            ['company_id' => $companyId->toString()]
        );

        return (int) ($row['total'] ?? 0);
    }

    private function hydrate(array $row): JournalEntry
    {
        $previousHash = $row['previous_hash'] 
            ? ContentHash::fromContent($row['previous_hash'])
            : null;

        $contentHash = ContentHash::fromContent($row['content_hash']);
        $occurredAt = new DateTimeImmutable($row['occurred_at']);

        // Reconstruct chain link if previous hash exists
        $chainLink = null;
        if ($previousHash && !empty($row['chain_hash'])) {
            $chainLink = new ChainLink(
                $previousHash,
                $contentHash,
                $occurredAt
            );
        }

        return JournalEntry::reconstitute(
            id: $row['id'],
            companyId: CompanyId::fromString($row['company_id']),
            transactionId: TransactionId::fromString($row['transaction_id']),
            entryType: $row['entry_type'],
            bookings: json_decode($row['bookings_json'], true),
            occurredAt: $occurredAt,
            contentHash: $contentHash,
            previousHash: $previousHash,
            chainLink: $chainLink
        );
    }
}
