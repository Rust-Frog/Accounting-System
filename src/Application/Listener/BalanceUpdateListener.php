<?php

declare(strict_types=1);

namespace Application\Listener;

use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\Ledger\Dto\AccountInitializationParams;
use Domain\Ledger\Entity\AccountBalance;
use Domain\Ledger\Entity\BalanceChange;
use Domain\Ledger\Repository\LedgerRepositoryInterface;
use Domain\Shared\Event\DomainEvent;
use Domain\Shared\ValueObject\Currency;
use Domain\Transaction\Event\TransactionPosted;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\ValueObject\TransactionId;

/**
 * Listener to update account balances when a transaction is posted.
 * Maintains the account_balances table for period snapshots.
 */
final class BalanceUpdateListener
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly LedgerRepositoryInterface $ledgerRepository,
        private readonly AccountRepositoryInterface $accountRepository,
    ) {
    }

    public function __invoke(DomainEvent $event): void
    {
        if (!$event instanceof TransactionPosted && !$event instanceof \Domain\Transaction\Event\TransactionVoided) {
            return;
        }

        $eventData = $event->toArray();
        $transactionId = TransactionId::fromString($eventData['transaction_id']);

        $transaction = $this->transactionRepository->findById($transactionId);
        if ($transaction === null) {
            return; 
        }

        foreach ($transaction->lines() as $line) {
            $accountId = $line->accountId();
            
            // Get Account details
            $account = $this->accountRepository->findById($accountId);
            if ($account === null) {
                continue;
            }

            // Get or Initialize AccountBalance
            $accountBalance = $this->ledgerRepository->getAccountBalance($transaction->companyId(), $accountId);
            
            if ($accountBalance === null) {
                $accountBalance = AccountBalance::initialize(new AccountInitializationParams(
                    accountId: $accountId,
                    companyId: $transaction->companyId(),
                    accountType: $account->accountType(),
                    currency: Currency::USD, 
                    openingBalanceCents: 0
                ));
            }

            // Reconstruct the change represented by this line
            $change = BalanceChange::create(
                accountId: $accountId,
                transactionId: $transaction->id(),
                lineType: $line->lineType(),
                amountCents: $line->amount()->cents(),
                previousBalanceCents: $accountBalance->currentBalanceCents(),
                normalBalance: $account->normalBalance()
            );

            if ($event instanceof \Domain\Transaction\Event\TransactionVoided) {
                // Reverse the change
                $accountBalance->reverseChange($change);
            } else {
                // Apply the change (Posting)
                $accountBalance->applyChange($change);
            }

            // Save Balance
            $this->ledgerRepository->saveBalance($accountBalance);
        }
    }
}
