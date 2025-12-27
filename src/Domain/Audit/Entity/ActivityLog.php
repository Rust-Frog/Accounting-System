<?php

declare(strict_types=1);

namespace Domain\Audit\Entity;

use DateTimeImmutable;
use Domain\Audit\ValueObject\ActivityId;
use Domain\Audit\ValueObject\ActivityType;
use Domain\Audit\ValueObject\Actor;
use Domain\Audit\ValueObject\AuditSeverity;
use Domain\Audit\ValueObject\ChangeRecord;
use Domain\Audit\ValueObject\RequestContext;
use Domain\Company\ValueObject\CompanyId;

/**
 * Immutable audit log entry (append-only).
 * BR-AT-001: No update or delete operations allowed.
 */
final readonly class ActivityLog
{
    /**
     * @param array<string, mixed> $previousState
     * @param array<string, mixed> $newState
     * @param array<ChangeRecord> $changes
     */
    public function __construct(
        private ActivityId $id,
        private CompanyId $companyId,
        private Actor $actor,
        private ActivityType $activityType,
        private string $entityType,
        private string $entityId,
        private string $action,
        private array $previousState,
        private array $newState,
        private array $changes,
        private RequestContext $context,
        private DateTimeImmutable $occurredAt,
        private ?\Domain\Shared\ValueObject\HashChain\ContentHash $contentHash = null,
        private ?\Domain\Shared\ValueObject\HashChain\ContentHash $previousHash = null,
        private ?\Domain\Shared\ValueObject\HashChain\ChainLink $chainLink = null
    ) {
    }

    public function id(): ActivityId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function actor(): Actor
    {
        return $this->actor;
    }

    public function activityType(): ActivityType
    {
        return $this->activityType;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function entityId(): string
    {
        return $this->entityId;
    }

    public function action(): string
    {
        return $this->action;
    }

    /**
     * @return array<string, mixed>
     */
    public function previousState(): array
    {
        return $this->previousState;
    }

    /**
     * @return array<string, mixed>
     */
    public function newState(): array
    {
        return $this->newState;
    }

    /**
     * @return array<ChangeRecord>
     */
    public function changes(): array
    {
        return $this->changes;
    }

    public function context(): RequestContext
    {
        return $this->context;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function category(): string
    {
        return \Domain\Audit\Service\ActivityClassification::getCategory($this->activityType);
    }

    public function severity(): AuditSeverity
    {
        return \Domain\Audit\Service\ActivityClassification::getSeverity($this->activityType);
    }

    public function contentHash(): ?\Domain\Shared\ValueObject\HashChain\ContentHash
    {
        return $this->contentHash;
    }

    public function previousHash(): ?\Domain\Shared\ValueObject\HashChain\ContentHash
    {
        return $this->previousHash;
    }

    public function chainLink(): ?\Domain\Shared\ValueObject\HashChain\ChainLink
    {
        return $this->chainLink;
    }

    /**
     * @return array<string, mixed>
     */
    public function toContentArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'company_id' => $this->companyId->toString(),
            'actor' => $this->actor->toArray(),
            'activity_type' => $this->activityType->value,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'action' => $this->action,
            'previous_state' => $this->previousState,
            'new_state' => $this->newState,
            'changes' => array_map(fn(ChangeRecord $c) => $c->toArray(), $this->changes),
            'context' => $this->context->toArray(),
            'severity' => $this->severity()->value,
            'category' => $this->category(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->toContentArray();
        $data['content_hash'] = $this->contentHash?->toString();
        $data['previous_hash'] = $this->previousHash?->toString();
        $data['chain_hash'] = $this->chainLink?->computeHash()->toString();
        
        return $data;
    }
}
