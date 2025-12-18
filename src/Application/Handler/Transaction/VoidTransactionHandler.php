<?php

declare(strict_types=1);

namespace Application\Handler\Transaction;

use Application\Command\CommandInterface;
use Application\Command\Transaction\VoidTransactionCommand;
use Application\Dto\Transaction\TransactionDto;
use Application\Dto\Transaction\TransactionLineDto;
use Application\Handler\HandlerInterface;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\EventDispatcherInterface;
use Domain\Shared\Exception\EntityNotFoundException;
use Domain\Transaction\Entity\Transaction;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\ValueObject\TransactionId;

/**
 * Handler for voiding a transaction.
 *
 * @implements HandlerInterface<VoidTransactionCommand>
 */
final readonly class VoidTransactionHandler implements HandlerInterface
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private \Domain\Ledger\Repository\JournalEntryRepositoryInterface $journalEntryRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(CommandInterface $command): TransactionDto
    {
        assert($command instanceof VoidTransactionCommand);

        $transactionId = TransactionId::fromString($command->transactionId);

        // Find transaction
        $transaction = $this->transactionRepository->findById($transactionId);

        if ($transaction === null) {
            throw new EntityNotFoundException("Transaction not found: {$command->transactionId}");
        }

        // Void transaction
        $transaction->void(
            $command->reason,
            UserId::fromString($command->voidedBy)
        );

        // Persist
        $this->transactionRepository->save($transaction);

        // --- IMMUTABLE LEDGER REVERSAL ---
        // Get latest chain hash
        $previousHash = $this->journalEntryRepository->getLatestHash($transaction->companyId());

        // Create reversing bookings
        $bookings = [];
        foreach ($transaction->lines() as $line) {
            $reversedType = ($line->lineType()->value === 'debit') ? 'credit' : 'debit';
            $bookings[] = [
                'account_id' => $line->accountId()->toString(),
                'type' => $reversedType, // FLIPPED for reversal
                'amount' => $line->amount()->cents()
            ];
        }

        // Create Journal Entry
        $journalEntry = \Domain\Ledger\Entity\JournalEntry::create(
            companyId: $transaction->companyId(),
            transactionId: $transaction->id(),
            entryType: 'REVERSAL', // Explicit reversal
            bookings: $bookings,
            occurredAt: new \DateTimeImmutable(),
            previousHash: $previousHash
        );

        $this->journalEntryRepository->save($journalEntry);
        // --------------------------------

        // Dispatch events
        foreach ($transaction->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $this->toDto($transaction);
    }

    private function toDto(Transaction $transaction): TransactionDto
    {
        $lines = [];
        $i = 0;
        foreach ($transaction->lines() as $line) {
            $lines[] = new TransactionLineDto(
                id: (string)$i,
                accountId: $line->accountId()->toString(),
                accountCode: 'Unknown',
                accountName: 'Unknown',
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
