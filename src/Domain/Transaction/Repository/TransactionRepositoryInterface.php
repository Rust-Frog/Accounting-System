<?php

declare(strict_types=1);

namespace Domain\Transaction\Repository;

use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\Entity\Transaction;
use Domain\Transaction\ValueObject\TransactionId;
use Domain\Transaction\ValueObject\TransactionStatus;

interface TransactionRepositoryInterface
{
    public function save(Transaction $transaction): void;

    public function findById(TransactionId $id): ?Transaction;

    public function findByNumber(CompanyId $companyId, string $transactionNumber): ?Transaction;

    /**
     * @return array<Transaction>
     */
    public function findByCompany(
        CompanyId $companyId, 
        ?TransactionStatus $status = null,
        int $limit = 20,
        int $offset = 0
    ): array;

    /**
     * @return array<Transaction>
     */
    public function findByDateRange(
        CompanyId $companyId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array;

    /**
     * @return array<Transaction>
     */
    public function findByAccount(AccountId $accountId, ?TransactionStatus $status = null): array;

    /**
     * @return array<Transaction>
     */
    public function findPendingApproval(CompanyId $companyId): array;

    public function getNextTransactionNumber(CompanyId $companyId): string;

    public function countByStatus(CompanyId $companyId, TransactionStatus $status): int;
}
