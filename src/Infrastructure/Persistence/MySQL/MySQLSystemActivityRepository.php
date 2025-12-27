<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\MySQL;

use DateTimeImmutable;
use Domain\Audit\Entity\SystemActivity;
use Domain\Audit\Repository\SystemActivityRepositoryInterface;
use Domain\Audit\ValueObject\ActivityId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\HashChain\ContentHash;
use PDO;

/**
 * MySQL implementation of SystemActivityRepository.
 */
final class MySQLSystemActivityRepository implements SystemActivityRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function save(SystemActivity $activity): void
    {
        $sql = <<<SQL
            INSERT INTO system_activities (
                id, previous_id, actor_user_id, actor_username, actor_ip_address,
                activity_type, severity, entity_type, entity_id, description,
                metadata_json, content_hash, previous_hash, chain_hash, created_at
            ) VALUES (
                :id, :previous_id, :actor_user_id, :actor_username, :actor_ip_address,
                :activity_type, :severity, :entity_type, :entity_id, :description,
                :metadata_json, :content_hash, :previous_hash, :chain_hash, :created_at
            )
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $activity->id()->toString(),
            'previous_id' => $activity->previousId()?->toString(),
            'actor_user_id' => $activity->actorUserId()?->toString(),
            'actor_username' => $activity->actorUsername(),
            'actor_ip_address' => $activity->actorIpAddress(),
            'activity_type' => $activity->activityType(),
            'severity' => $activity->severity(),
            'entity_type' => $activity->entityType(),
            'entity_id' => $activity->entityId(),
            'description' => $activity->description(),
            'metadata_json' => $activity->metadata() ? json_encode($activity->metadata()) : null,
            'content_hash' => $activity->contentHash()->toString(),
            'previous_hash' => $activity->previousHash()?->toString(),
            'chain_hash' => $activity->chainHash()->toString(),
            'created_at' => $activity->createdAt()->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function findRecent(int $limit = 10, int $offset = 0): array
    {
        $sql = <<<SQL
            SELECT * FROM system_activities 
            ORDER BY created_at DESC, sequence_number DESC
            LIMIT :limit OFFSET :offset
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $activities = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activities[] = $this->hydrate($row);
        }

        return $activities;
    }

    public function findLatest(): ?SystemActivity
    {
        $sql = <<<SQL
            SELECT * FROM system_activities 
            ORDER BY sequence_number DESC
            LIMIT 1
        SQL;

        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findById(ActivityId $id): ?SystemActivity
    {
        $sql = 'SELECT * FROM system_activities WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id->toString()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByType(string $activityType, int $limit = 10, int $offset = 0): array
    {
        $sql = <<<SQL
            SELECT * FROM system_activities 
            WHERE activity_type = :activity_type
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('activity_type', $activityType);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $activities = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activities[] = $this->hydrate($row);
        }

        return $activities;
    }

    public function count(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM system_activities');
        return (int) $stmt->fetchColumn();
    }

    private function hydrate(array $row): SystemActivity
    {
        return SystemActivity::reconstitute(
            id: ActivityId::fromString($row['id']),
            sequenceNumber: (int) $row['sequence_number'],
            previousId: $row['previous_id'] ? ActivityId::fromString($row['previous_id']) : null,
            actorUserId: $row['actor_user_id'] ? UserId::fromString($row['actor_user_id']) : null,
            actorUsername: $row['actor_username'],
            actorIpAddress: $row['actor_ip_address'],
            activityType: $row['activity_type'],
            severity: $row['severity'],
            entityType: $row['entity_type'],
            entityId: $row['entity_id'],
            description: $row['description'],
            metadata: $row['metadata_json'] ? json_decode($row['metadata_json'], true) : null,
            contentHash: ContentHash::fromString($row['content_hash']),
            previousHash: $row['previous_hash'] ? ContentHash::fromString($row['previous_hash']) : null,
            chainHash: ContentHash::fromString($row['chain_hash']),
            createdAt: new DateTimeImmutable($row['created_at'])
        );
    }
}
