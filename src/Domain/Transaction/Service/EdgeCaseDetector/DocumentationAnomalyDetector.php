<?php

declare(strict_types=1);

namespace Domain\Transaction\Service\EdgeCaseDetector;

use Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use Domain\Transaction\ValueObject\EdgeCaseFlag;

/**
 * Detects documentation anomalies:
 * - Missing or minimal description (Rule #18)
 */
final class DocumentationAnomalyDetector
{
    private const MIN_DESCRIPTION_LENGTH = 5;

    public function detect(string $description): EdgeCaseDetectionResult
    {
        $trimmed = trim($description);

        if (mb_strlen($trimmed) < self::MIN_DESCRIPTION_LENGTH) {
            return EdgeCaseDetectionResult::withFlags([
                EdgeCaseFlag::missingDescription(),
            ]);
        }

        return EdgeCaseDetectionResult::clean();
    }
}
