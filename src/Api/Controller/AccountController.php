<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use Domain\ChartOfAccounts\Entity\Account;
use Domain\ChartOfAccounts\Repository\AccountRepositoryInterface;
use Domain\ChartOfAccounts\ValueObject\AccountCode;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\ChartOfAccounts\ValueObject\AccountType;
use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Account controller for Chart of Accounts management.
 */
final class AccountController
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly ?\Domain\Audit\Service\SystemActivityService $activityService = null
    ) {
    }

    /**
     * GET /api/v1/companies/{companyId}/accounts
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $companyId = $this->getCompanyId($request);
            $accounts = $this->accountRepository->findByCompany($companyId);

            $data = array_map(fn(Account $a) => $this->formatAccount($a), $accounts);

            return JsonResponse::success($data);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/companies/{companyId}/accounts/{id}
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $account = $this->getAccount($request);

            return JsonResponse::success($this->formatAccount($account));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/companies/{companyId}/accounts
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $companyId = $this->getCompanyId($request);
        } catch (\Throwable $e) {
             return JsonResponse::error($e->getMessage(), 400);
        }

        $body = $request->getParsedBody();

        // Validate required fields
        $required = ['code', 'name'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                return JsonResponse::error("Missing required field: $field", 422);
            }
        }

        try {
            $account = Account::create(
                AccountCode::fromInt((int) $body['code']),
                $body['name'],
                $companyId,
                $body['description'] ?? null,
                isset($body['parent_id']) ? AccountId::fromString($body['parent_id']) : null
            );

            $this->accountRepository->save($account);

            // Log account creation
            $this->activityService?->log(
                activityType: 'account.created',
                entityType: 'account',
                entityId: $account->id()->toString(),
                description: "Account {$account->code()->toInt()} - {$account->name()} created",
                actorUserId: \Domain\Identity\ValueObject\UserId::fromString($request->getAttribute('user_id') ?? ''),
                actorUsername: $request->getAttribute('username'),
                actorIpAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
                severity: 'info',
                metadata: ['code' => $account->code()->toInt(), 'type' => $account->accountType()->value]
            );

            return JsonResponse::created($this->formatAccount($account));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/v1/companies/{companyId}/accounts/{id}
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $account = $this->getAccount($request);
            $body = $request->getParsedBody();

            // Update name if provided
            if (isset($body['name']) && !empty($body['name'])) {
                $account->rename($body['name']);
            }

            // Update description if provided
            if (array_key_exists('description', $body)) {
                $account->updateDescription($body['description'] ?? '');
            }

            $this->accountRepository->save($account);

            return JsonResponse::success($this->formatAccount($account));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/companies/{companyId}/accounts/{id}/toggle
     */
    public function toggle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $account = $this->getAccount($request);

            // Toggle active status
            if ($account->isActive()) {
                $account->deactivate();
            } else {
                $account->activate();
            }

            $this->accountRepository->save($account);

            // Log account toggle
            $action = $account->isActive() ? 'activated' : 'deactivated';
            $this->activityService?->log(
                activityType: "account.{$action}",
                entityType: 'account',
                entityId: $account->id()->toString(),
                description: "Account {$account->code()->toInt()} - {$account->name()} {$action}",
                actorUserId: \Domain\Identity\ValueObject\UserId::fromString($request->getAttribute('user_id') ?? ''),
                actorUsername: $request->getAttribute('username'),
                actorIpAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
                severity: 'info'
            );

            return JsonResponse::success($this->formatAccount($account));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/v1/companies/{companyId}/accounts/{id}/transactions
     * Fetch transactions where this account was used.
     */
    public function transactions(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Verify account exists
            $account = $this->getAccount($request);

            // Fetch transactions involving this account (limit to recent 20)
            $transactions = $this->transactionRepository->findByAccount(
                $account->id()
            );

            // Limit to 20 most recent
            $transactions = array_slice($transactions, 0, 20);

            $data = array_map(fn($t) => $this->formatTransactionSummary($t), $transactions);

            return JsonResponse::success(array_values($data));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Format account for API response.
     * Backend handles all formatting - frontend just displays.
     */
    private function formatAccount(Account $account): array
    {
        return [
            'id' => $account->id()->toString(),
            'code' => $account->code()->toInt(),
            'name' => $account->name(),
            'type' => $account->accountType()->value,
            'type_label' => ucfirst($account->accountType()->value),
            'normal_balance' => $account->normalBalance()->value,
            'company_id' => $account->companyId()->toString(),
            'parent_id' => $account->parentAccountId()?->toString(),
            'description' => $account->description(),
            'is_active' => $account->isActive(),
            'balance' => $account->balance()->cents() / 100,
            'balance_cents' => $account->balance()->cents(),
            'currency' => $account->balance()->currency()->value,
        ];
    }

    /**
     * Format transaction for list display.
     */
    private function formatTransactionSummary(\Domain\Transaction\Entity\Transaction $transaction): array
    {
        return [
            'id' => $transaction->id()->toString(),
            'transaction_date' => $transaction->transactionDate()->format('Y-m-d'),
            'description' => $transaction->description(),
            'status' => $transaction->status()->value,
            'amount' => $transaction->totalDebits()->cents() / 100,
        ];
    }
    private function getCompanyId(ServerRequestInterface $request): CompanyId
    {
        $companyId = $request->getAttribute('companyId');
        if ($companyId === null) {
            throw new \InvalidArgumentException('Company ID required');
        }
        return CompanyId::fromString($companyId);
    }

    private function getAccount(ServerRequestInterface $request): Account
    {
        $id = $request->getAttribute('id');
        if ($id === null) {
            throw new \InvalidArgumentException('Account ID required');
        }

        $account = $this->accountRepository->findById(
            AccountId::fromString($id)
        );

        if ($account === null) {
            throw new \Domain\Shared\Exception\EntityNotFoundException('Account not found');
        }

        return $account;
    }
}

