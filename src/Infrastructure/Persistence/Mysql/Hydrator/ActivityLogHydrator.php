<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Hydrator;

use DateTimeImmutable;
use Domain\Audit\Entity\ActivityLog;
use Domain\Audit\ValueObject\ActivityId;
use Domain\Audit\ValueObject\ActivityType;
use Domain\Audit\ValueObject\Actor;
use Domain\Audit\ValueObject\ChangeRecord;
use Domain\Audit\ValueObject\RequestContext;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;

/**
 * Hydrates ActivityLog entities from database rows.
 * ActivityLog is append-only, so we only need hydration and insert extraction.
 */
final class ActivityLogHydrator
{
    /**
     * Hydrate an ActivityLog entity from a database row.
     *
     * @param array<string, mixed> $row
     */
    public function hydrate(array $row): ActivityLog
    {
        $changesJson = json_decode($row['changes_json'] ?? '{}', true);

        // Reconstruct Actor (using Reflection due to private constructor)
        $actorReflector = new \ReflectionClass(Actor::class);
        $actor = $actorReflector->newInstanceWithoutConstructor();
        
        $actorType = $row['actor_user_id'] ? 'user' : ($row['actor_username'] === 'SYSTEM' ? 'system' : 'unknown');
        
        $this->setPrivateProperty($actor, 'userId', $row['actor_user_id']);
        $this->setPrivateProperty($actor, 'actorType', $actorType);
        $this->setPrivateProperty($actor, 'actorName', $row['actor_username'] ?? 'Unknown');
        $this->setPrivateProperty($actor, 'impersonatedBy', null); // Not persisted in current schema

        // Reconstruct RequestContext
        $contextReflector = new \ReflectionClass(RequestContext::class);
        $context = $contextReflector->newInstanceWithoutConstructor();
        
        $this->setPrivateProperty($context, 'ipAddress', $row['actor_ip_address'] ?? null);
        $this->setPrivateProperty($context, 'userAgent', $row['actor_user_agent'] ?? null);
        $this->setPrivateProperty($context, 'requestId', $row['request_id'] ?? null);
        $this->setPrivateProperty($context, 'correlationId', $row['correlation_id'] ?? null);
        $this->setPrivateProperty($context, 'sessionId', $changesJson['context']['session_id'] ?? null);
        $this->setPrivateProperty($context, 'endpoint', $changesJson['context']['url'] ?? null); // check mapping
        $this->setPrivateProperty($context, 'httpMethod', $changesJson['context']['method'] ?? null);
        $this->setPrivateProperty($context, 'timestamp', new DateTimeImmutable($row['occurred_at']));

        // Reconstruct changes
        $changes = [];
        if (isset($changesJson['changes']) && is_array($changesJson['changes'])) {
            $changeReflector = new \ReflectionClass(ChangeRecord::class);
            foreach ($changesJson['changes'] as $changeData) {
                $change = $changeReflector->newInstanceWithoutConstructor();
                $this->setPrivateProperty($change, 'field', $changeData['field'] ?? '');
                $this->setPrivateProperty($change, 'previousValue', $changeData['previous_value'] ?? null);
                $this->setPrivateProperty($change, 'newValue', $changeData['new_value'] ?? null);
                $this->setPrivateProperty($change, 'changeType', $changeData['change_type'] ?? 'modified');
                $changes[] = $change;
            }
        }

        return new ActivityLog(
            id: ActivityId::fromString($row['id']),
            companyId: CompanyId::fromString($row['company_id']),
            actor: $actor,
            activityType: ActivityType::from($row['activity_type']),
            entityType: $row['entity_type'],
            entityId: $row['entity_id'],
            action: $changesJson['action'] ?? 'unknown',
            previousState: $changesJson['previous_state'] ?? [],
            newState: $changesJson['new_state'] ?? [],
            changes: $changes,
            context: $context,
            occurredAt: new DateTimeImmutable($row['occurred_at']),
            contentHash: isset($row['content_hash']) ? \Domain\Shared\ValueObject\HashChain\ContentHash::fromString($row['content_hash']) : null,
            previousHash: isset($row['previous_hash']) ? \Domain\Shared\ValueObject\HashChain\ContentHash::fromString($row['previous_hash']) : null,
            chainLink: isset($row['chain_hash']) ? new \Domain\Shared\ValueObject\HashChain\ChainLink(
                \Domain\Shared\ValueObject\HashChain\ContentHash::fromString($row['previous_hash']),
                \Domain\Shared\ValueObject\HashChain\ContentHash::fromString($row['content_hash']),
                new DateTimeImmutable($row['occurred_at'])
            ) : null
        );
    }

    /**
     * Extract data from ActivityLog entity for persistence.
     * Note: ActivityLog is append-only, so this is only used for INSERT.
     *
     * @return array<string, mixed>
     */
    public function extract(ActivityLog $log): array
    {
        $changesJson = [
            'action' => $log->action(),
            'previous_state' => $log->previousState(),
            'new_state' => $log->newState(),
            'changes' => array_map(fn(ChangeRecord $c) => $c->toArray(), $log->changes()),
            'context' => $log->context()->toArray(),
        ];

        return [
            'id' => $log->id()->toString(),
            'company_id' => $log->companyId()->toString(),
            'actor_user_id' => $log->actor()->userId(),
            'actor_username' => $log->actor()->actorName(),
            'actor_ip_address' => $log->context()->ipAddress(),
            'actor_user_agent' => $log->context()->userAgent(),
            'activity_type' => $log->activityType()->value,
            'severity' => $log->severity()->value,
            'entity_type' => $log->entityType(),
            'entity_id' => $log->entityId(),
            'changes_json' => json_encode($changesJson),
            'request_id' => $log->context()->requestId(),
            'correlation_id' => $log->context()->correlationId(),
            'occurred_at' => $log->occurredAt()->format('Y-m-d H:i:s'),
            'content_hash' => $log->contentHash()?->toString() ?? '',
            'previous_hash' => $log->previousHash()?->toString() ?? '',
            'chain_hash' => $log->chainLink()?->computeHash()->toString() ?? '',
        ];
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
