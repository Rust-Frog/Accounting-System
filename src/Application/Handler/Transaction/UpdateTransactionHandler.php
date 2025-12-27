<?php

declare(strict_types=1);

namespace Application\Handler\Transaction;

use Application\Command\CommandInterface;
use Application\Command\Transaction\UpdateTransactionCommand;
use Application\Dto\Transaction\TransactionDto;
use Application\Dto\Transaction\TransactionLineDto;
use Application\Handler\HandlerInterface;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\EventDispatcherInterface;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;
use Domain\Transaction\Entity\Transaction;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\ValueObject\LineType;
use Domain\Transaction\ValueObject\TransactionId;

/**
 * Handler for updating a draft transaction.
 *
 * @implements HandlerInterface<UpdateTransactionCommand>
 */
final readonly class UpdateTransactionHandler implements HandlerInterface
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private AccountRepositoryInterface $accountRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(CommandInterface $command): TransactionDto
    {
        assert($command instanceof UpdateTransactionCommand);

        $transactionId = TransactionId::fromString($command->transactionId);
        $transaction = $this->transactionRepository->findById($transactionId);

        if ($transaction === null) {
            throw new \DomainException("Transaction not found: {$command->transactionId}");
        }

        if (!$transaction->isDraft()) {
            throw new BusinessRuleException('Only draft transactions can be updated');
        }

        $companyId = CompanyId::fromString($command->companyId);
        if (!$transaction->companyId()->equals($companyId)) {
            throw new \DomainException('Transaction does not belong to the specified company');
        }

        $updatedBy = UserId::fromString($command->updatedBy);
        $transactionDate = $command->transactionDate
            ? new \DateTimeImmutable($command->transactionDate)
            : $transaction->transactionDate();

        $currency = Currency::from($command->currency);

        // Update transaction header
        $transaction->update(
            transactionDate: $transactionDate,
            description: $command->description,
            referenceNumber: $command->referenceNumber,
            updatedBy: $updatedBy,
        );

        // Clear existing lines and add new ones
        $transaction->clearLines();
        $this->processTransactionLines($command, $transaction, $companyId, $currency);

        // Persist
        $this->transactionRepository->save($transaction);

        // Dispatch events
        foreach ($transaction->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $this->toDto($transaction);
    }

    private function processTransactionLines(
        UpdateTransactionCommand $command,
        Transaction $transaction,
        CompanyId $companyId,
        Currency $currency
    ): void {
        foreach ($command->lines as $lineData) {
            $accountId = AccountId::fromString($lineData->accountId);
            $account = $this->accountRepository->findById($accountId);

            if ($account === null) {
                throw new \DomainException("Account not found: {$lineData->accountId}");
            }

            if (!$account->companyId()->equals($companyId)) {
                throw new \DomainException("Account {$lineData->accountId} does not belong to company {$command->companyId}");
            }

            if (!$account->isActive()) {
                throw new \DomainException("Account {$lineData->accountId} is not active");
            }

            $transaction->addLine(
                accountId: $accountId,
                lineType: LineType::from($lineData->lineType),
                amount: Money::fromCents($lineData->amountCents, $currency),
                description: $lineData->description,
            );
        }
    }

    private function toDto(Transaction $transaction): TransactionDto
    {
        $lines = [];
        $i = 0;
        foreach ($transaction->lines() as $line) {
            $account = $this->accountRepository->findById($line->accountId());
            $lines[] = new TransactionLineDto(
                id: (string)$i,
                accountId: $line->accountId()->toString(),
                accountCode: $account !== null ? (string)$account->code()->toInt() : 'Unknown',
                accountName: $account?->name() ?? 'Unknown Account',
                lineType: $line->lineType()->value,
                amountCents: $line->amount()->cents(),
                lineOrder: $i++,
                description: $line->description() ?? ''
            );
        }

        return new TransactionDto(
            id: $transaction->id()->toString(),
            transactionNumber: $transaction->id()->toString(),
            companyId: $transaction->companyId()->toString(),
            status: $transaction->status()->value,
            description: $transaction->description(),
            totalDebitsCents: $transaction->totalDebits()->cents(),
            totalCreditsCents: $transaction->totalCredits()->cents(),
            lines: $lines,
            referenceNumber: $transaction->referenceNumber(),
            transactionDate: $transaction->transactionDate()->format('Y-m-d'),
            createdAt: $transaction->createdAt()->format('Y-m-d H:i:s'),
            postedAt: $transaction->postedAt()?->format('Y-m-d H:i:s'),
        );
    }
}
