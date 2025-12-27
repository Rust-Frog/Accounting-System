<?php

declare(strict_types=1);

namespace Domain\Audit\ValueObject;

use Domain\Audit\ValueObject\ActivityType;
use Domain\Audit\ValueObject\Actor;
use Domain\Audit\ValueObject\RequestContext;

final class LogActivityRequest
{
    /**
     * @param array<string, mixed> $entityInfo
     * @param array<string, mixed> $stateInfo
     */
    public function __construct(
        public readonly string $companyId,
        public readonly Actor $actor,
        public readonly ActivityType $activityType,
        public readonly array $entityInfo,
        public readonly array $stateInfo,
        public readonly RequestContext $context
    ) {
    }
}
