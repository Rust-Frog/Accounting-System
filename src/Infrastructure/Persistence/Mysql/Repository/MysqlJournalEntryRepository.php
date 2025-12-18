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
            'SELECT chain_hash FROM journal_entries WHERE company_id = :company_id ORDER BY occurred_at DESC LIMIT 1',
            ['company_id' => $companyId->toString()]
        );

        if ($row === null || empty($row['chain_hash'])) {
            return null;
        }

        return ContentHash::fromContent($row['chain_hash']); // Assuming direct content for now, or use appropriate factory
    }

    private function hydrate(array $row): JournalEntry
    {
        $previousHash = $row['previous_hash'] 
            ? ContentHash::fromContent($row['previous_hash']) // Assuming plain string storage
            : null;

        $contentHash = ContentHash::fromContent($row['content_hash']);
        
        // Reconstruct chain link explicitly if needed, but JournalEntry constructor handles it via internal properties usually.
        // However, our JournalEntry constructor is private and create() computes hashes. 
        // We need a way to hydrate existing entries.
        // Ideally we should use reflection or exposed hydration method.
        // For now, let's assume we can reconstruct it via reflection or a dedicated hydrator.
        // Since create() re-computes, we can't use it for hydration without verifying.
        // Let's rely on a strictly controlled hydration mechanism.
        // Refactoring to add 'hydrate' static method or similar to JournalEntry is needed. 
        
        // TEMPORARY: Using reflection to bypass constructor for hydration
        $reflection = new \ReflectionClass(JournalEntry::class);
        $constructor = $reflection->getConstructor();
        $constructor->setAccessible(true);
        
        $object = $reflection->newInstanceWithoutConstructor();
        
        // Set properties
        $this->setProperty($reflection, $object, 'id', $row['id']);
        $this->setProperty($reflection, $object, 'companyId', CompanyId::fromString($row['company_id']));
        $this->setProperty($reflection, $object, 'transactionId', TransactionId::fromString($row['transaction_id']));
        $this->setProperty($reflection, $object, 'entryType', $row['entry_type']);
        $this->setProperty($reflection, $object, 'bookings', json_decode($row['bookings_json'], true));
        $this->setProperty($reflection, $object, 'occurredAt', new DateTimeImmutable($row['occurred_at']));
        $this->setProperty($reflection, $object, 'contentHash', $contentHash);
        $this->setProperty($reflection, $object, 'previousHash', $previousHash);
        
        // ChainLink reconstruction
        if ($previousHash && !empty($row['chain_hash'])) {
             $chainLink = new ChainLink(
                 $previousHash,
                 $contentHash,
                 new DateTimeImmutable($row['occurred_at'])
             );
             $this->setProperty($reflection, $object, 'chainLink', $chainLink);
        } else {
             $this->setProperty($reflection, $object, 'chainLink', null); 
        }

        return $object;
    }

    private function setProperty(\ReflectionClass $reflection, object $object, string $name, mixed $value): void
    {
        $property = $reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
