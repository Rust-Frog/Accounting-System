<?php

declare(strict_types=1);

namespace Domain\Transaction\Service;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;

/**
 * Detects edge cases in transaction data that require approval routing.
 *
 * Edge cases are NOT hard blocks - they allow the transaction to proceed
 * but route it through the approval workflow for human review.
 *
 * This service runs AFTER TransactionValidationService (hard blocks) passes.
 */
interface EdgeCaseDetectionServiceInterface
{
    /**
     * Detect all applicable edge cases for a transaction.
     *
     * @param array $lines Transaction line data with account_id, debit_cents, credit_cents
     * @param DateTimeImmutable $transactionDate The transaction date
     * @param string $description Transaction description
     * @param CompanyId $companyId Company ID for threshold lookups
     * @param EdgeCaseThresholds|null $thresholds Override thresholds (for testing)
     * @return EdgeCaseDetectionResult Aggregated detection results
     */
    public function detect(
        array $lines,
        DateTimeImmutable $transactionDate,
        string $description,
        CompanyId $companyId,
        ?EdgeCaseThresholds $thresholds = null,
    ): EdgeCaseDetectionResult;
}
