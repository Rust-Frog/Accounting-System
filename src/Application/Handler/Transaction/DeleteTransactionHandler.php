<?php

declare(strict_types=1);

namespace Application\Handler\Transaction;

use Application\Command\CommandInterface;
use Application\Command\Transaction\DeleteTransactionCommand;
use Application\Handler\HandlerInterface;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\Exception\EntityNotFoundException;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\ValueObject\TransactionId;

/**
 * Handler for deleting a transaction.
 *
 * @implements HandlerInterface<DeleteTransactionCommand>
 */
final readonly class DeleteTransactionHandler implements HandlerInterface
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteTransactionCommand);

        $transactionId = TransactionId::fromString($command->transactionId);

        // Find transaction
        $transaction = $this->transactionRepository->findById($transactionId);

        if ($transaction === null) {
            throw new EntityNotFoundException("Transaction not found: {$command->transactionId}");
        }

        // BR-TXN-005: Only draft transactions can be deleted.
        if (!$transaction->status()->isDraft()) {
            throw new BusinessRuleException("Only draft transactions can be deleted. Current status: {$transaction->status()->value}");
        }

        // Delete from repository
        $this->transactionRepository->delete($transactionId);

        return ['deleted' => true, 'id' => $command->transactionId];
    }
}
