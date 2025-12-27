<?php

declare(strict_types=1);

namespace Infrastructure\Authorization;

use Domain\Authorization\OwnershipResult;
use Domain\Authorization\OwnershipVerifierInterface;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\Repository\UserRepositoryInterface;
use Domain\Identity\ValueObject\UserId;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\ValueObject\TransactionId;

/**
 * Implementation of ownership verification using repositories.
 * Verifies that resources belong to the expected company.
 */
final class OwnershipVerifier implements OwnershipVerifierInterface
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function verify(
        string $resourceType,
        string $resourceId,
        CompanyId $expectedCompanyId
    ): OwnershipResult {
        return match ($resourceType) {
            'transaction' => $this->verifyTransaction($resourceId, $expectedCompanyId),
            'account' => $this->verifyAccount($resourceId, $expectedCompanyId),
            'company' => $this->verifyCompany($resourceId, $expectedCompanyId),
            default => OwnershipResult::notOwned("Unknown resource type: {$resourceType}"),
        };
    }

    private function verifyCompany(string $resourceId, CompanyId $expectedCompanyId): OwnershipResult
    {
        if ($resourceId !== $expectedCompanyId->toString()) {
            return OwnershipResult::notOwned(
                'Access denied: Resource belongs to a different company',
                $resourceId
            );
        }

        return OwnershipResult::owned($resourceId);
    }

    public function verifyUserCompany(
        UserId $userId,
        CompanyId $expectedCompanyId
    ): OwnershipResult {
        $user = $this->userRepository->findById($userId);
        
        if ($user === null) {
            return OwnershipResult::notFound('user', $userId->toString());
        }

        $userCompanyId = $user->companyId();
        
        if ($userCompanyId === null) {
            return OwnershipResult::notOwned('User is not associated with any company');
        }

        if (!$userCompanyId->equals($expectedCompanyId)) {
            return OwnershipResult::notOwned(
                'User does not belong to this company',
                $userCompanyId->toString()
            );
        }

        return OwnershipResult::owned($expectedCompanyId->toString());
    }

    private function verifyTransaction(string $resourceId, CompanyId $expectedCompanyId): OwnershipResult
    {
        try {
            $transactionId = TransactionId::fromString($resourceId);
        } catch (\Throwable) {
            return OwnershipResult::notOwned('Invalid transaction ID format');
        }

        $transaction = $this->transactionRepository->findById($transactionId);
        
        if ($transaction === null) {
            return OwnershipResult::notFound('transaction', $resourceId);
        }

        $actualCompanyId = $transaction->companyId();
        
        if (!$actualCompanyId->equals($expectedCompanyId)) {
            return OwnershipResult::notOwned(
                'Transaction belongs to a different company',
                $actualCompanyId->toString()
            );
        }

        return OwnershipResult::owned($actualCompanyId->toString());
    }

    private function verifyAccount(string $resourceId, CompanyId $expectedCompanyId): OwnershipResult
    {
        try {
            $accountId = AccountId::fromString($resourceId);
        } catch (\Throwable) {
            return OwnershipResult::notOwned('Invalid account ID format');
        }

        $account = $this->accountRepository->findById($accountId);
        
        if ($account === null) {
            return OwnershipResult::notFound('account', $resourceId);
        }

        $actualCompanyId = $account->companyId();
        
        if (!$actualCompanyId->equals($expectedCompanyId)) {
            return OwnershipResult::notOwned(
                'Account belongs to a different company',
                $actualCompanyId->toString()
            );
        }

        return OwnershipResult::owned($actualCompanyId->toString());
    }
}
