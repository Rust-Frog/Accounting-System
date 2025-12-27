<?php

declare(strict_types=1);

namespace Domain\Transaction\Service;

use DateTimeImmutable;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Ledger\Repository\LedgerRepositoryInterface;
use Domain\Ledger\Service\BalanceCalculationService;
use Domain\Transaction\Repository\ThresholdRepositoryInterface;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\Service\EdgeCaseDetector\AccountTypeAnomalyDetector;
use Domain\Transaction\Service\EdgeCaseDetector\AmountAnomalyDetector;
use Domain\Transaction\Service\EdgeCaseDetector\BalanceImpactDetector;
use Domain\Transaction\Service\EdgeCaseDetector\DocumentationAnomalyDetector;
use Domain\Transaction\Service\EdgeCaseDetector\DormantAccountDetector;
use Domain\Transaction\Service\EdgeCaseDetector\DuplicateTransactionDetector;
use Domain\Transaction\Service\EdgeCaseDetector\PeriodEndDetector;
use Domain\Transaction\Service\EdgeCaseDetector\TimingAnomalyDetector;
use Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;

/**
 * Orchestrates all edge case detectors.
 * Runs AFTER TransactionValidationService (hard blocks) passes.
 * Returns aggregated flags for approval routing decision.
 */
final readonly class EdgeCaseDetectionService implements EdgeCaseDetectionServiceInterface
{
    private TimingAnomalyDetector $timingDetector;
    private AmountAnomalyDetector $amountDetector;
    private AccountTypeAnomalyDetector $accountTypeDetector;
    private DocumentationAnomalyDetector $documentationDetector;
    private BalanceImpactDetector $balanceImpactDetector;
    private PeriodEndDetector $periodEndDetector;
    private DormantAccountDetector $dormantAccountDetector;
    private DuplicateTransactionDetector $duplicateTransactionDetector;

    public function __construct(
        private ThresholdRepositoryInterface $thresholdRepository,
        private AccountRepositoryInterface $accountRepository,
        private LedgerRepositoryInterface $ledgerRepository,
        private TransactionRepositoryInterface $transactionRepository,
        BalanceCalculationService $balanceCalculator,
    ) {
        $this->timingDetector = new TimingAnomalyDetector();
        $this->amountDetector = new AmountAnomalyDetector();
        $this->accountTypeDetector = new AccountTypeAnomalyDetector();
        $this->documentationDetector = new DocumentationAnomalyDetector();
        $this->balanceImpactDetector = new BalanceImpactDetector(
            $this->ledgerRepository,
            $balanceCalculator,
        );
        $this->periodEndDetector = new PeriodEndDetector();
        $this->dormantAccountDetector = new DormantAccountDetector($this->transactionRepository);
        $this->duplicateTransactionDetector = new DuplicateTransactionDetector($this->transactionRepository);
    }

    public function detect(
        array $lines,
        DateTimeImmutable $transactionDate,
        string $description,
        CompanyId $companyId,
        ?EdgeCaseThresholds $thresholds = null,
    ): EdgeCaseDetectionResult {
        $thresholds ??= $this->thresholdRepository->getForCompany($companyId);
        $today = new DateTimeImmutable('today');

        // Hydrate account entities for lines that need them
        $hydratedLines = $this->hydrateAccountsForLines($lines);

        // Calculate total amount for duplicate detection
        $totalAmountCents = array_sum(array_map(
            fn($line) => $line['debit_cents'] ?? 0,
            $lines
        ));

        // Run all detectors
        $results = [
            $this->timingDetector->detect($transactionDate, $today, $thresholds),
            $this->amountDetector->detect($lines, $thresholds),
            $this->accountTypeDetector->detect($hydratedLines, $thresholds),
            $this->documentationDetector->detect($description),
            $this->balanceImpactDetector->detect($hydratedLines, $companyId, $thresholds),
            $this->periodEndDetector->detect($transactionDate, $thresholds),
            $this->dormantAccountDetector->detect($hydratedLines, $companyId, $thresholds),
            $this->duplicateTransactionDetector->detect($totalAmountCents, $description, $transactionDate, $companyId),
        ];

        // Merge all results
        $merged = EdgeCaseDetectionResult::clean();
        foreach ($results as $result) {
            $merged = $merged->merge($result);
        }

        return $merged;
    }

    /**
     * @param array<array{account_id: string, debit_cents: int, credit_cents: int}> $lines
     * @return array<array{account: \Domain\ChartOfAccounts\Entity\Account, debit_cents: int, credit_cents: int}>
     */
    private function hydrateAccountsForLines(array $lines): array
    {
        $hydrated = [];

        foreach ($lines as $line) {
            $accountId = AccountId::fromString($line['account_id']);
            $account = $this->accountRepository->findById($accountId);

            if ($account !== null) {
                $hydrated[] = [
                    'account' => $account,
                    'debit_cents' => $line['debit_cents'] ?? 0,
                    'credit_cents' => $line['credit_cents'] ?? 0,
                ];
            }
        }

        return $hydrated;
    }
}
