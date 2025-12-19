<?php

declare(strict_types=1);

namespace Domain\Audit\Service;

use Domain\Audit\ValueObject\ActivityType;
use Domain\Audit\ValueObject\Actor;
use Domain\Audit\ValueObject\RequestContext;

/**
 * Request DTO for logging activity.
 */
final readonly class LogActivityRequest
{
    /**
     * @param string $companyId
     * @param Actor $actor
     * @param ActivityType $activityType
     * @param array{type: string, id: string, action: string} $entityInfo
     * @param array{prev?: array, new?: array, changes?: array} $stateInfo
     * @param RequestContext $context
     */
    public function __construct(
        public string $companyId,
        public Actor $actor,
        public ActivityType $activityType,
        public array $entityInfo,
        public array $stateInfo,
        public RequestContext $context
    ) {
    }
}
