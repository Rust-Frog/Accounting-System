<?php

declare(strict_types=1);

namespace Domain\Transaction\Service\EdgeCaseDetector;

use DateTimeImmutable;
use Domain\ChartOfAccounts\Entity\Account;
use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use Domain\Transaction\ValueObject\EdgeCaseFlag;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;

/**
 * Detects transactions involving dormant accounts (Rule #17).
 * An account is dormant if it has had no activity for longer than the threshold.
 */
final readonly class DormantAccountDetector
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
    ) {
    }

    /**
     * @param array<array{account: Account, debit_cents: int, credit_cents: int}> $lines
     */
    public function detect(
        array $lines,
        CompanyId $companyId,
        EdgeCaseThresholds $thresholds,
    ): EdgeCaseDetectionResult {
        $flags = [];
        $today = new DateTimeImmutable('today');
        $thresholdDays = $thresholds->dormantAccountDaysThreshold();

        // Track accounts we've already checked to avoid duplicate flags
        $checkedAccountIds = [];

        foreach ($lines as $line) {
            /** @var Account $account */
            $account = $line['account'];
            $accountId = $account->id();
            $accountIdString = $accountId->toString();

            // Skip if we've already checked this account
            if (isset($checkedAccountIds[$accountIdString])) {
                continue;
            }
            $checkedAccountIds[$accountIdString] = true;

            $lastActivityDate = $this->transactionRepository->getLastActivityDate($accountId);

            // New accounts with no history are not considered dormant
            if ($lastActivityDate === null) {
                continue;
            }

            $daysSinceActivity = $today->diff($lastActivityDate)->days;

            if ($daysSinceActivity >= $thresholdDays) {
                $flags[] = EdgeCaseFlag::dormantAccount(
                    $account->name(),
                    $daysSinceActivity,
                );
            }
        }

        return count($flags) > 0
            ? EdgeCaseDetectionResult::withFlags($flags)
            : EdgeCaseDetectionResult::clean();
    }
}
