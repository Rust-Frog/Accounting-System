<?php

declare(strict_types=1);

namespace Domain\Transaction\Service\EdgeCaseDetector;

use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\ValueObject\NormalBalance;
use Domain\Company\ValueObject\CompanyId;
use Domain\Ledger\Repository\LedgerRepositoryInterface;
use Domain\Ledger\Service\BalanceCalculationService;
use Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use Domain\Transaction\ValueObject\EdgeCaseFlag;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;
use Domain\Transaction\ValueObject\LineType;

/**
 * Detects balance impact anomalies:
 * - Negative balance for asset accounts (Rule #12)
 * - Negative balance for liability accounts (Rule #13)
 * - Negative balance for equity accounts (Rule #14)
 */
final class BalanceImpactDetector
{
    public function __construct(
        private readonly LedgerRepositoryInterface $ledgerRepository,
        private readonly BalanceCalculationService $balanceCalculator,
    ) {
    }

    /**
     * @param array<array{account: Account, debit_cents: int, credit_cents: int}> $lines
     */
    public function detect(array $lines, CompanyId $companyId, EdgeCaseThresholds $thresholds): EdgeCaseDetectionResult
    {
        if (!$thresholds->requireApprovalNegativeBalance()) {
            return EdgeCaseDetectionResult::clean();
        }

        $flags = [];

        foreach ($lines as $line) {
            /** @var Account $account */
            $account = $line['account'];
            $debitCents = $line['debit_cents'] ?? 0;
            $creditCents = $line['credit_cents'] ?? 0;

            // Get current balance
            $currentBalanceCents = $this->ledgerRepository->getBalanceCents(
                $companyId,
                $account->id(),
            );

            // Calculate balance change
            $normalBalance = $account->normalBalance();
            $changeCents = 0;

            if ($debitCents > 0) {
                $changeCents += $this->balanceCalculator->calculateChange(
                    $normalBalance,
                    LineType::DEBIT,
                    $debitCents,
                );
            }

            if ($creditCents > 0) {
                $changeCents += $this->balanceCalculator->calculateChange(
                    $normalBalance,
                    LineType::CREDIT,
                    $creditCents,
                );
            }

            // Project new balance
            $projectedBalanceCents = $this->balanceCalculator->projectBalance(
                $currentBalanceCents,
                $changeCents,
            );

            // Flag if balance goes negative
            if ($projectedBalanceCents < 0) {
                $flags[] = EdgeCaseFlag::negativeBalance(
                    $account->name(),
                    $currentBalanceCents,
                    $projectedBalanceCents,
                );
            }
        }

        return empty($flags)
            ? EdgeCaseDetectionResult::clean()
            : EdgeCaseDetectionResult::withFlags($flags);
    }
}
