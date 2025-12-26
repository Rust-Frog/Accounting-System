<?php

declare(strict_types=1);

namespace Infrastructure\Reporting\Service;

use DateTimeImmutable;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\Entity\BalanceSheet;
use Domain\Reporting\Service\BalanceSheetGeneratorInterface;
use Domain\Reporting\ValueObject\ReportId;
use Domain\Reporting\ValueObject\ReportPeriod;

/**
 * Balance Sheet Generator implementation.
 * 
 * Generates a balance sheet report by aggregating asset, liability,
 * and equity accounts as of a point in time.
 * 
 * BR-FR-001: Assets = Liabilities + Equity
 */
final readonly class BalanceSheetGenerator implements BalanceSheetGeneratorInterface
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository
    ) {
    }

    /**
     * Generate balance sheet as of end of period.
     */
    public function generate(CompanyId $companyId, ReportPeriod $period): BalanceSheet
    {
        $accounts = $this->accountRepository->findByCompany($companyId);

        $currentAssets = [];
        $fixedAssets = [];
        $currentLiabilities = [];
        $longTermLiabilities = [];
        $equityAccounts = [];

        $totalCurrentAssetsCents = 0;
        $totalFixedAssetsCents = 0;
        $totalCurrentLiabilitiesCents = 0;
        $totalLongTermLiabilitiesCents = 0;
        $totalEquityCents = 0;
        $retainedEarningsCents = 0;

        foreach ($accounts as $account) {
            // Skip inactive accounts with zero balance
            if (!$account->isActive() && $account->balance()->cents() === 0) {
                continue;
            }

            $accountType = $account->accountType()->value;
            $balanceCents = $account->balance()->cents();
            $accountCode = (string) $account->code()->toInt();
            $accountName = $account->name();

            $accountData = [
                'account_id' => $account->id()->toString(),
                'account_code' => $accountCode,
                'account_name' => $accountName,
                'amount_cents' => abs($balanceCents),
            ];

            // Categorize by account type
            switch ($accountType) {
                case 'asset':
                    // Classify as current or fixed based on code ranges
                    if ($this->isCurrentAsset($accountCode, $accountName)) {
                        $currentAssets[] = $accountData;
                        $totalCurrentAssetsCents += $balanceCents;
                    } else {
                        $fixedAssets[] = $accountData;
                        $totalFixedAssetsCents += $balanceCents;
                    }
                    break;

                case 'liability':
                    // Classify as current or long-term
                    if ($this->isCurrentLiability($accountCode, $accountName)) {
                        $currentLiabilities[] = $accountData;
                        $totalCurrentLiabilitiesCents += $balanceCents;
                    } else {
                        $longTermLiabilities[] = $accountData;
                        $totalLongTermLiabilitiesCents += $balanceCents;
                    }
                    break;

                case 'equity':
                    // Check for retained earnings
                    if ($this->isRetainedEarnings($accountName)) {
                        $retainedEarningsCents += $balanceCents;
                    } else {
                        $equityAccounts[] = $accountData;
                        $totalEquityCents += $balanceCents;
                    }
                    break;

                case 'revenue':
                case 'expense':
                    // Revenue and expenses roll into retained earnings
                    // Revenue increases equity (credit), expenses decrease (debit)
                    if ($accountType === 'revenue') {
                        $retainedEarningsCents += $balanceCents;
                    } else {
                        $retainedEarningsCents -= $balanceCents;
                    }
                    break;
            }
        }

        // Sort arrays by account code
        usort($currentAssets, fn($a, $b) => $a['account_code'] <=> $b['account_code']);
        usort($fixedAssets, fn($a, $b) => $a['account_code'] <=> $b['account_code']);
        usort($currentLiabilities, fn($a, $b) => $a['account_code'] <=> $b['account_code']);
        usort($longTermLiabilities, fn($a, $b) => $a['account_code'] <=> $b['account_code']);
        usort($equityAccounts, fn($a, $b) => $a['account_code'] <=> $b['account_code']);

        return new BalanceSheet(
            id: ReportId::generate(),
            companyId: $companyId,
            period: $period,
            generatedAt: new DateTimeImmutable(),
            currentAssets: $currentAssets,
            totalCurrentAssetsCents: $totalCurrentAssetsCents,
            fixedAssets: $fixedAssets,
            totalFixedAssetsCents: $totalFixedAssetsCents,
            currentLiabilities: $currentLiabilities,
            totalCurrentLiabilitiesCents: $totalCurrentLiabilitiesCents,
            longTermLiabilities: $longTermLiabilities,
            totalLongTermLiabilitiesCents: $totalLongTermLiabilitiesCents,
            equityAccounts: $equityAccounts,
            totalEquityCents: $totalEquityCents,
            retainedEarningsCents: $retainedEarningsCents
        );
    }

    /**
     * Determine if an asset is current (liquid, < 1 year).
     * Uses account code ranges and name keywords.
     */
    private function isCurrentAsset(string $code, string $name): bool
    {
        // Check name for current asset keywords
        $currentKeywords = ['cash', 'bank', 'receivable', 'inventory', 'prepaid', 'petty'];
        $nameLower = strtolower($name);
        foreach ($currentKeywords as $keyword) {
            if (str_contains($nameLower, $keyword)) {
                return true;
            }
        }

        // Common account code ranges for current assets (1000-1499)
        $codeInt = (int) $code;
        return $codeInt >= 1000 && $codeInt < 1500;
    }

    /**
     * Determine if a liability is current (due < 1 year).
     * Uses account code ranges and name keywords.
     */
    private function isCurrentLiability(string $code, string $name): bool
    {
        // Check name for current liability keywords
        $currentKeywords = ['payable', 'accrued', 'short-term', 'current', 'wages', 'salaries', 'tax'];
        $nameLower = strtolower($name);
        foreach ($currentKeywords as $keyword) {
            if (str_contains($nameLower, $keyword)) {
                return true;
            }
        }

        // Common account code ranges for current liabilities (2000-2499)
        $codeInt = (int) $code;
        return $codeInt >= 2000 && $codeInt < 2500;
    }

    /**
     * Check if account is retained earnings.
     */
    private function isRetainedEarnings(string $name): bool
    {
        $nameLower = strtolower($name);
        return str_contains($nameLower, 'retained') && str_contains($nameLower, 'earnings');
    }
}
