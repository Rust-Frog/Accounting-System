<?php

declare(strict_types=1);

namespace Application\Listener;

use Domain\Audit\Service\ActivityLogService;
use Domain\Audit\ValueObject\ActivityType;
use Domain\Audit\ValueObject\Actor;
use Domain\Audit\ValueObject\RequestContext;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\DomainEvent;
use Domain\Transaction\Event\TransactionCreated;
use Domain\Transaction\Event\TransactionPosted;
use Domain\Transaction\Event\TransactionUpdated;
use Domain\Transaction\Event\TransactionVoided;

/**
 * Listener to automatically log activities based on domain events.
 */
final class ActivityLogListener
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Get the display name for a user ID.
     */
    private function getUserDisplayName(string $userId): string
    {
        try {
            $user = $this->userRepository->findById(UserId::fromString($userId));
            return $user?->username() ?? 'Unknown';
        } catch (\Throwable) {
            return 'Unknown';
        }
    }

    public function __invoke(DomainEvent $event): void
    {
        // Enrich context from global state or event?
        // Ideally event has all info. ActivityLog needs Actor, RequestContext etc.
        // Transactions events have createdBy/postedBy which allows Actor reconstruction.
        // RequestContext (IP, UserAgent) is hard to get from Event unless Event carries it.
        // Or we inject a RequestContext accessor service? 
        // For now, we'll use defaults or try to extract from event if possible.
        // But the events I saw don't have IP/UA.
        // This is a common trade-off. We might need a "ContextAwareEvent" or current request scope.
        // Since we are in a Request/Response cycle, we could inject a RequestContextFactory that pulls from globals or request stack?
        // But `ActivityLogListener` is in Application layer.
        
        // Let's use a placeholder context for now, or minimal info.
        // Let's use a placeholder context for now, or minimal info.
        $context = RequestContext::fromRequest(
            ipAddress: '127.0.0.1', // Placeholder
            userAgent: 'System/Event',
            requestId: uniqid('req_', true),
            correlationId: uniqid('corr_', true)
        );

        if ($event instanceof TransactionCreated) {
            $eventData = $event->toArray();
            $displayName = $this->getUserDisplayName($eventData['created_by']);
            $this->activityLogService->logActivity(
                new \Domain\Audit\Service\LogActivityRequest(
                    companyId: $eventData['company_id'],
                    actor: Actor::user(UserId::fromString($eventData['created_by']), $displayName),
                    activityType: ActivityType::TRANSACTION_CREATED,
                    entityInfo: [
                        'type' => 'transaction',
                        'id' => $eventData['transaction_id'],
                        'action' => 'created'
                    ],
                    stateInfo: [
                        'new' => $eventData
                    ],
                    context: $context
                )
            );
        }

        if ($event instanceof TransactionUpdated) {
            $eventData = $event->toArray();
            $displayName = $this->getUserDisplayName($eventData['updated_by']);
            $this->activityLogService->logActivity(
                new \Domain\Audit\Service\LogActivityRequest(
                    companyId: $eventData['company_id'],
                    actor: Actor::user(UserId::fromString($eventData['updated_by']), $displayName),
                    activityType: ActivityType::TRANSACTION_EDITED,
                    entityInfo: [
                        'type' => 'transaction',
                        'id' => $eventData['transaction_id'],
                        'action' => 'updated'
                    ],
                    stateInfo: [
                        'new' => $eventData
                    ],
                    context: $context
                )
            );
        }

        if ($event instanceof TransactionPosted) {
            $eventData = $event->toArray();
            $displayName = $this->getUserDisplayName($eventData['posted_by']);
            $this->activityLogService->logActivity(
                new \Domain\Audit\Service\LogActivityRequest(
                    companyId: $eventData['company_id'],
                    actor: Actor::user(UserId::fromString($eventData['posted_by']), $displayName),
                    activityType: ActivityType::TRANSACTION_POSTED,
                    entityInfo: [
                        'type' => 'transaction',
                        'id' => $eventData['transaction_id'],
                        'action' => 'posted'
                    ],
                    stateInfo: [
                        'new' => $eventData
                    ],
                    context: $context
                )
            );
        }

        if ($event instanceof TransactionVoided) {
            $eventData = $event->toArray();
            $displayName = $this->getUserDisplayName($eventData['voided_by']);
            $this->activityLogService->logActivity(
                new \Domain\Audit\Service\LogActivityRequest(
                    companyId: $eventData['company_id'],
                    actor: Actor::user(UserId::fromString($eventData['voided_by']), $displayName),
                    activityType: ActivityType::TRANSACTION_VOIDED,
                    entityInfo: [
                        'type' => 'transaction',
                        'id' => $eventData['transaction_id'],
                        'action' => 'voided'
                    ],
                    stateInfo: [
                        'new' => $eventData
                    ],
                    context: $context
                )
            );
        }
    }
}
