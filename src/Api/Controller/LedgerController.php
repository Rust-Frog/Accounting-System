<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Controller\Traits\SafeExceptionHandlerTrait;

use Api\Response\JsonResponse;
use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Ledger\Repository\JournalEntryRepositoryInterface;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Ledger controller for General Ledger views.
 * Shows per-account transaction activity with running balances.
 */
final class LedgerController
{
    use SafeExceptionHandlerTrait;

    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly JournalEntryRepositoryInterface $journalEntryRepository
    ) {
    }

    /**
     * GET /api/v1/companies/{companyId}/ledger
     * 
     * Query params:
     * - account_id: (required) Account ID to show ledger for
     * - start_date: (optional) Filter start date YYYY-MM-DD
     * - end_date: (optional) Filter end date YYYY-MM-DD
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        if ($companyId === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        $queryParams = $request->getQueryParams();
        $accountId = $queryParams['account_id'] ?? null;

        if ($accountId === null) {
            return JsonResponse::error('account_id query parameter is required', 400);
        }

        try {
            // Get the account
            $account = $this->accountRepository->findById(
                AccountId::fromString($accountId)
            );

            if ($account === null) {
                return JsonResponse::error('Account not found', 404);
            }

            // Verify account belongs to company
            if ($account->companyId()->toString() !== $companyId) {
                return JsonResponse::error('Account not found in this company', 404);
            }

            // Get journal entries for this company
            $journalEntries = $this->journalEntryRepository->findByCompany(
                CompanyId::fromString($companyId)
            );

            // Sort by occurred_at (using public property)
            usort($journalEntries, function ($a, $b) {
                return $a->occurredAt <=> $b->occurredAt;
            });

            // Optional date filtering
            $startDate = isset($queryParams['start_date']) 
                ? new \DateTimeImmutable($queryParams['start_date'] . ' 00:00:00') 
                : null;
            $endDate = isset($queryParams['end_date']) 
                ? new \DateTimeImmutable($queryParams['end_date'] . ' 23:59:59') 
                : null;

            // Build ledger entries with running balance
            $entries = [];
            $runningBalance = 0;
            $totalDebits = 0;
            $totalCredits = 0;
            $normalBalance = $account->normalBalance()->value;

            foreach ($journalEntries as $journalEntry) {
                // Check date filter (using public property)
                $occurredAt = $journalEntry->occurredAt;
                if ($startDate && $occurredAt < $startDate) continue;
                if ($endDate && $occurredAt > $endDate) continue;

                // Get transaction for description
                $transaction = $this->transactionRepository->findById($journalEntry->transactionId);
                $description = $transaction ? $transaction->description() : 'Unknown';
                $reference = $transaction ? ($transaction->referenceNumber() ?? $journalEntry->transactionId->toString()) : $journalEntry->transactionId->toString();
                $txnDate = $transaction ? $transaction->transactionDate()->format('Y-m-d') : $occurredAt->format('Y-m-d');

                // Check bookings for this account (using public property)
                $bookings = $journalEntry->bookings;
                foreach ($bookings as $booking) {
                    $bookingAccountId = $booking['account_id'] ?? '';
                    if ($bookingAccountId !== $accountId) continue;

                    $amountCents = (int)($booking['amount'] ?? 0);
                    $lineType = $booking['type'] ?? '';

                    // For REVERSAL entries, the type is already flipped in the booking
                    $isReversal = $journalEntry->entryType === 'REVERSAL';

                    $debit = 0;
                    $credit = 0;

                    if ($lineType === 'debit') {
                        $debit = $amountCents;
                        $totalDebits += $amountCents;
                        if ($normalBalance === 'debit') {
                            $runningBalance += $amountCents;
                        } else {
                            $runningBalance -= $amountCents;
                        }
                    } else {
                        $credit = $amountCents;
                        $totalCredits += $amountCents;
                        if ($normalBalance === 'credit') {
                            $runningBalance += $amountCents;
                        } else {
                            $runningBalance -= $amountCents;
                        }
                    }

                    // Add entry type indicator for reversals
                    $entryDescription = $description;
                    if ($isReversal) {
                        $entryDescription = '[REVERSAL] ' . $description;
                    }

                    $entries[] = [
                        'date' => $txnDate,
                        'reference' => $reference,
                        'description' => $entryDescription,
                        'debit_cents' => $debit,
                        'credit_cents' => $credit,
                        'balance_cents' => $runningBalance,
                        'transaction_id' => $journalEntry->transactionId->toString(),
                        'entry_type' => $journalEntry->entryType,
                    ];
                }
            }

            // Format response
            $response = [
                'account' => [
                    'id' => $account->id()->toString(),
                    'code' => $account->code()->toInt(),
                    'name' => $account->name(),
                    'type' => $account->accountType()->value,
                    'normal_balance' => $normalBalance,
                    'current_balance_cents' => $account->balance()->cents(),
                ],
                'entries' => $entries,
                'totals' => [
                    'debit_cents' => $totalDebits,
                    'credit_cents' => $totalCredits,
                    'entry_count' => count($entries),
                ],
            ];

            return JsonResponse::success($response);
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * GET /api/v1/companies/{companyId}/ledger/summary
     * 
     * Returns summary of all accounts with balances for ledger overview.
     */
    public function summary(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        if ($companyId === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        try {
            $accounts = $this->accountRepository->findByCompany(
                CompanyId::fromString($companyId)
            );

            // Group by type
            $grouped = [
                'asset' => [],
                'liability' => [],
                'equity' => [],
                'revenue' => [],
                'expense' => [],
            ];

            foreach ($accounts as $account) {
                $type = $account->accountType()->value;
                if (!isset($grouped[$type])) {
                    $grouped[$type] = [];
                }

                $grouped[$type][] = [
                    'id' => $account->id()->toString(),
                    'code' => $account->code()->toInt(),
                    'name' => $account->name(),
                    'balance_cents' => $account->balance()->cents(),
                    'normal_balance' => $account->normalBalance()->value,
                    'is_active' => $account->isActive(),
                ];
            }

            // Sort each group by code
            foreach ($grouped as $type => &$accounts) {
                usort($accounts, fn($a, $b) => $a['code'] <=> $b['code']);
            }

            // Calculate totals
            $totals = [
                'assets' => array_reduce($grouped['asset'], fn($sum, $a) => $sum + $a['balance_cents'], 0),
                'liabilities' => array_reduce($grouped['liability'], fn($sum, $a) => $sum + $a['balance_cents'], 0),
                'equity' => array_reduce($grouped['equity'], fn($sum, $a) => $sum + $a['balance_cents'], 0),
                'revenue' => array_reduce($grouped['revenue'], fn($sum, $a) => $sum + $a['balance_cents'], 0),
                'expenses' => array_reduce($grouped['expense'], fn($sum, $a) => $sum + $a['balance_cents'], 0),
            ];

            return JsonResponse::success([
                'accounts' => $grouped,
                'totals' => $totals,
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }
}
