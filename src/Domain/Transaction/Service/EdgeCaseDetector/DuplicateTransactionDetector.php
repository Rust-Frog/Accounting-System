<?php

declare(strict_types=1);

namespace Domain\Transaction\Service\EdgeCaseDetector;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use Domain\Transaction\ValueObject\EdgeCaseFlag;

/**
 * Detects potential duplicate transactions (Rule #19).
 * Flags if a transaction with the same amount exists on the same day.
 */
final readonly class DuplicateTransactionDetector
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function detect(
        int $totalAmountCents,
        string $description,
        DateTimeImmutable $transactionDate,
        CompanyId $companyId,
    ): EdgeCaseDetectionResult {
        $existingTransactionNumber = $this->transactionRepository->findSimilarTransaction(
            $companyId,
            $totalAmountCents,
            $description,
            $transactionDate,
        );

        if ($existingTransactionNumber === null) {
            return EdgeCaseDetectionResult::clean();
        }

        return EdgeCaseDetectionResult::withFlags([
            EdgeCaseFlag::duplicateTransaction($existingTransactionNumber),
        ]);
    }
}
