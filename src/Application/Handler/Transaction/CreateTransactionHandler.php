<?php

declare(strict_types=1);

namespace Application\Handler\Transaction;

use Application\Command\CommandInterface;
use Application\Command\Transaction\CreateTransactionCommand;
use Application\Dto\Transaction\TransactionDto;
use Application\Dto\Transaction\TransactionLineDto;
use Application\Handler\HandlerInterface;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\Repository\CompanyRepositoryInterface;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Reporting\Repository\ClosedPeriodRepositoryInterface;
use Domain\Shared\Event\EventDispatcherInterface;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;
use Domain\Transaction\Entity\Transaction;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\Service\TransactionNumberGeneratorInterface;
use Domain\Transaction\ValueObject\LineType;

/**
 * Handler for creating a transaction.
 *
 * @implements HandlerInterface<CreateTransactionCommand>
 */
final readonly class CreateTransactionHandler implements HandlerInterface
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private AccountRepositoryInterface $accountRepository,
        private EventDispatcherInterface $eventDispatcher,
        private ?TransactionNumberGeneratorInterface $transactionNumberGenerator = null,
        private ?CompanyRepositoryInterface $companyRepository = null,
        private ?ClosedPeriodRepositoryInterface $closedPeriodRepository = null,
    ) {
    }

    public function handle(CommandInterface $command): TransactionDto
    {
        assert($command instanceof CreateTransactionCommand);

        $companyId = CompanyId::fromString($command->companyId);
        $createdBy = UserId::fromString($command->createdBy);

        // Validate company is active before allowing transaction creation
        if ($this->companyRepository !== null) {
            $company = $this->companyRepository->findById($companyId);
            if ($company === null) {
                throw new BusinessRuleException('Company not found');
            }
            if (!$company->status()->canOperate()) {
                throw new BusinessRuleException(
                    'Cannot create transactions for a company with status: ' . $company->status()->value
                );
            }
        }

        // Handler was using ->date, Command has ->transactionDate
        $transactionDate = $command->transactionDate 
            ? new \DateTimeImmutable($command->transactionDate) 
            : new \DateTimeImmutable();

        // Validate transaction date is not in a closed period
        if ($this->closedPeriodRepository !== null) {
            if ($this->closedPeriodRepository->isDateInClosedPeriod($companyId, $transactionDate)) {
                throw new BusinessRuleException(
                    'Cannot create transactions in a closed period. Transaction date: ' . $transactionDate->format('Y-m-d')
                );
            }
        }
            
        $currency = Currency::from($command->currency);

        // Create transaction header
        $transaction = Transaction::create(
            companyId: $companyId,
            transactionDate: $transactionDate,
            description: $command->description,
            createdBy: $createdBy,
            referenceNumber: $command->referenceNumber,
        );

        // Add lines
        $this->processTransactionLines($command, $transaction, $companyId, $currency);

        // Generate transaction number (included in hash chain)
        if ($this->transactionNumberGenerator !== null) {
            $transactionNumber = $this->transactionNumberGenerator->generateNextNumber($companyId);
            $transaction->setTransactionNumber($transactionNumber);
        }

        // Persist
        $this->transactionRepository->save($transaction);

        // Dispatch events
        foreach ($transaction->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $this->toDto($transaction);
    }

    private function processTransactionLines(
        CreateTransactionCommand $command,
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
                lineType: LineType::from($lineData->lineType), // was type
                amount: Money::fromCents($lineData->amountCents, $currency), // was amount
                description: $lineData->description,
            );
        }
    }

    private function toDto(Transaction $transaction): TransactionDto
    {
        $lines = [];
        $i = 0;
        foreach ($transaction->lines() as $line) {
            $accountId = $line->accountId();
            $account = $this->accountRepository->findById($accountId);
            
            $lines[] = new TransactionLineDto(
                id: (string)$i,
                accountId: $accountId->toString(),
                accountCode: $account !== null ? $account->code()->toString() : 'Unknown',
                accountName: $account?->name() ?? 'Unknown',
                lineType: $line->lineType()->value,
                amountCents: $line->amount()->cents(),
                lineOrder: $i++,
                description: $line->description() ?? ''
            );
        }

        return new TransactionDto(
            id: $transaction->id()->toString(),
            transactionNumber: $transaction->transactionNumber() ?? $transaction->id()->toString(),
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
