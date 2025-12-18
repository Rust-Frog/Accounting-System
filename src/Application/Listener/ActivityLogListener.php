<?php

declare(strict_types=1);

namespace Application\Listener;

use Domain\Audit\Service\ActivityLogService;
use Domain\Audit\ValueObject\ActivityType;
use Domain\Audit\ValueObject\Actor;
use Domain\Audit\ValueObject\RequestContext;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\DomainEvent;
use Domain\Transaction\Event\TransactionCreated;
use Domain\Transaction\Event\TransactionPosted;
use Domain\Transaction\Event\TransactionVoided;

/**
 * Listener to automatically log activities based on domain events.
 */
final class ActivityLogListener
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {
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
            $this->activityLogService->logActivity(
                new \Domain\Audit\ValueObject\LogActivityRequest(
                    companyId: $eventData['company_id'],
                    actor: Actor::user(UserId::fromString($eventData['created_by']), 'Unknown'),
                    activityType: ActivityType::CREATE,
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
        
        // Add other event handlers here...
    }
}
