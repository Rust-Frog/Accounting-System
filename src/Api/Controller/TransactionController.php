<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Controller\Traits\SafeExceptionHandlerTrait;

use Api\Response\JsonResponse;
use Application\Command\Transaction\CreateTransactionCommand;
use Application\Command\Transaction\DeleteTransactionCommand;
use Application\Command\Transaction\PostTransactionCommand;
use Application\Command\Transaction\TransactionLineData;
use Application\Command\Transaction\UpdateTransactionCommand;
use Application\Command\Transaction\VoidTransactionCommand;
use Application\Dto\Transaction\TransactionDto;
use Application\Handler\Transaction\CreateTransactionHandler;
use Application\Handler\Transaction\DeleteTransactionHandler;
use Application\Handler\Transaction\PostTransactionHandler;
use Application\Handler\Transaction\UpdateTransactionHandler;
use Application\Handler\Transaction\VoidTransactionHandler;
use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\ValueObject\TransactionId;
use Api\Validation\TransactionValidation;
use Api\Middleware\AuthorizationGuard;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * Transaction controller for journal entry management.
 * Uses Application layer handlers for proper domain type translation.
 */
final class TransactionController
{
    use SafeExceptionHandlerTrait;

    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly CreateTransactionHandler $createHandler,
        private readonly UpdateTransactionHandler $updateHandler,
        private readonly DeleteTransactionHandler $deleteHandler,
        private readonly PostTransactionHandler $postHandler,
        private readonly VoidTransactionHandler $voidHandler,
        private readonly PDO $pdo,
        private readonly ?\Domain\Audit\Service\SystemActivityService $activityService = null,
        private readonly ?TransactionValidation $validation = null,
        private readonly ?AuthorizationGuard $authGuard = null,
    ) {
    }

    /**
     * GET /api/v1/companies/{companyId}/transactions
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        if ($companyId === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        try {
            $queryParams = $request->getQueryParams();
            $page = max(1, (int) ($queryParams['page'] ?? 1));
            $limit = max(1, min(100, (int) ($queryParams['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $statusFilter = $queryParams['status'] ?? null;

            $transactions = $this->transactionRepository->findByCompany(
                CompanyId::fromString($companyId),
                null,
                $limit,
                $offset
            );

            // Apply status filter if provided
            if ($statusFilter !== null && $statusFilter !== 'all') {
                $transactions = array_filter(
                    $transactions,
                    fn($t) => $t->status()->value === $statusFilter
                );
            }

            // Return flat array - frontend expects data directly
            $data = array_map(fn($t) => $this->formatTransactionSummary($t), $transactions);

            return JsonResponse::success(array_values($data));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * GET /api/v1/companies/{companyId}/transactions/{id}
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        if ($id === null) {
            return JsonResponse::error('Transaction ID required', 400);
        }

        // Verify ownership before allowing access
        if ($this->authGuard !== null && !$this->authGuard->verifyResourceOwnership($request, 'transaction', $id)) {
            return JsonResponse::error('Access denied: Transaction not found or not authorized', 403);
        }

        try {
            $transaction = $this->transactionRepository->findById(
                TransactionId::fromString($id)
            );

            if ($transaction === null) {
                return JsonResponse::error('Transaction not found', 404);
            }

            return JsonResponse::success($this->formatTransactionSummary($transaction));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * POST /api/v1/companies/{companyId}/transactions
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if ($this->isMissingContext($companyId, $userId)) {
            return JsonResponse::error('Company ID and authentication required', 400);
        }

        $errorResponse = $this->validateCreateRequest($body);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $this->pdo->beginTransaction();
        try {
            $command = $this->buildCreateCommand($companyId, $userId, $body);
            $dto = $this->createHandler->handle($command);

            // Log transaction creation
            $this->activityService?->log(
                activityType: 'transaction.created',
                entityType: 'transaction',
                entityId: $dto->id,
                description: "Transaction created: {$dto->description}",
                actorUserId: \Domain\Identity\ValueObject\UserId::fromString($userId),
                actorUsername: $request->getAttribute('username'),
                actorIpAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
                severity: 'info',
                metadata: ['amount_cents' => $dto->totalDebitsCents ?? 0, 'company_id' => $companyId]
            );

            $this->pdo->commit();
            return JsonResponse::created($dto->toArray());
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * PUT /api/v1/companies/{companyId}/transactions/{id}
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        $transactionId = $request->getAttribute('id');
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if ($this->isMissingContext($companyId, $userId) || $transactionId === null) {
            return JsonResponse::error('Company ID, Transaction ID, and authentication required', 400);
        }

        $errorResponse = $this->validateCreateRequest($body);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        try {
            $command = $this->buildUpdateCommand($companyId, $transactionId, $userId, $body);
            $dto = $this->updateHandler->handle($command);

            return JsonResponse::success($dto->toArray());
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * DELETE /api/v1/companies/{companyId}/transactions/{id}
     */
    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        $transactionId = $request->getAttribute('id');
        $userId = $request->getAttribute('user_id');

        if ($this->isMissingContext($companyId, $userId) || $transactionId === null) {
            return JsonResponse::error('Company ID, Transaction ID, and authentication required', 400);
        }

        try {
            $command = new DeleteTransactionCommand(
                transactionId: $transactionId,
                companyId: $companyId,
                deletedBy: $userId
            );

            $this->deleteHandler->handle($command);

            return JsonResponse::success(['message' => 'Transaction deleted successfully']);
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    private function isMissingContext(?string $companyId, ?string $userId): bool
    {
        return $companyId === null || $userId === null;
    }

    private function buildUpdateCommand(string $companyId, string $transactionId, string $userId, array $body): UpdateTransactionCommand
    {
        $lines = $this->parseTransactionLines($body['lines']);

        return new UpdateTransactionCommand(
            transactionId: $transactionId,
            companyId: $companyId,
            updatedBy: $userId,
            description: $body['description'],
            currency: $body['currency'] ?? 'USD',
            lines: $lines,
            transactionDate: $body['date'] ?? null,
            referenceNumber: $body['reference_number'] ?? null,
        );
    }

    private function buildCreateCommand(string $companyId, string $userId, array $body): CreateTransactionCommand
    {
        $lines = $this->parseTransactionLines($body['lines']);

        return new CreateTransactionCommand(
            companyId: $companyId,
            createdBy: $userId,
            description: $body['description'],
            currency: $body['currency'] ?? 'USD',
            lines: $lines,
            transactionDate: $body['date'] ?? null,
            referenceNumber: $body['reference_number'] ?? null,
        );
    }

    private function validateCreateRequest(array $body): ?ResponseInterface
    {
        // Use typed validation if available
        if ($this->validation !== null) {
            $result = $this->validation->validateCreate($body);
            if ($result->isInvalid()) {
                return JsonResponse::error(
                    $result->firstError() ?? 'Validation failed',
                    422,
                    ['validation_errors' => $result->errors()]
                );
            }
            return null;
        }

        // Fallback to basic validation
        if (empty($body['description'])) {
            return JsonResponse::error('Missing required field: description', 422);
        }

        if (!$this->hasValidLines($body)) {
            return JsonResponse::error('At least 2 transaction lines required', 422);
        }

        return null;
    }

    private function hasValidLines(array $body): bool
    {
        return !empty($body['lines'])
            && is_array($body['lines'])
            && count($body['lines']) >= 2;
    }

    /**
     * @return TransactionLineData[]
     * @throws \InvalidArgumentException
     */
    private function parseTransactionLines(array $linesData): array
    {
        $lines = [];
        foreach ($linesData as $line) {
            if (!$this->isValidLine($line)) {
                throw new \InvalidArgumentException('Invalid line: account_id, line_type, amount_cents required');
            }

            $lines[] = new TransactionLineData(
                accountId: $line['account_id'],
                lineType: $line['line_type'], // 'debit' or 'credit'
                amountCents: (int) $line['amount_cents'],
                description: $line['description'] ?? '',
            );
        }
        return $lines;
    }

    private function isValidLine(array $line): bool
    {
        return !empty($line['account_id']) 
            && !empty($line['line_type']) 
            && isset($line['amount_cents']);
    }

    public function post(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->handleTransactionAction(
            $request,
            fn($id, $userId) => $this->postHandler->handle(
                new PostTransactionCommand($id, $userId)
            )
        );

        // Log transaction posting if successful
        if ($result->getStatusCode() === 200) {
            $id = $request->getAttribute('id');
            $userId = $request->getAttribute('user_id');
            $this->activityService?->log(
                activityType: 'transaction.posted',
                entityType: 'transaction',
                entityId: $id,
                description: "Transaction posted to ledger",
                actorUserId: \Domain\Identity\ValueObject\UserId::fromString($userId),
                actorUsername: $request->getAttribute('username'),
                actorIpAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
                severity: 'info'
            );
        }

        return $result;
    }

    public function void(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->handleTransactionAction(
            $request,
            function ($id, $userId, $request) {
                $body = $request->getParsedBody();
                return $this->voidHandler->handle(
                    new VoidTransactionCommand(
                        $id,
                        $userId,
                        $body['reason'] ?? 'Voided via API'
                    )
                );
            }
        );

        // Log transaction voiding if successful
        if ($result->getStatusCode() === 200) {
            $id = $request->getAttribute('id');
            $userId = $request->getAttribute('user_id');
            $body = $request->getParsedBody();
            $this->activityService?->log(
                activityType: 'transaction.voided',
                entityType: 'transaction',
                entityId: $id,
                description: "Transaction voided: " . ($body['reason'] ?? 'No reason'),
                actorUserId: \Domain\Identity\ValueObject\UserId::fromString($userId),
                actorUsername: $request->getAttribute('username'),
                actorIpAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
                severity: 'warning',
                metadata: ['reason' => $body['reason'] ?? 'Voided via API']
            );
        }

        return $result;
    }

    private function handleTransactionAction(ServerRequestInterface $request, callable $action): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $userId = $request->getAttribute('user_id');

        if ($this->isMissingContext($id, $userId)) {
            return JsonResponse::error('Transaction ID and authentication required', 400);
        }

        try {
            $dto = $action($id, $userId, $request);
            return JsonResponse::success($dto->toArray());
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * Format transaction entity for list/get responses.
     * Uses domain entity accessors directly for read operations.
     * Backend handles all conversions - frontend just displays.
     */
    private function formatTransactionSummary(mixed $transaction): array
    {
        $totalDebitsCents = $transaction->totalDebits()->cents();
        $totalCreditsCents = $transaction->totalCredits()->cents();
        
        // Format lines with debit/credit in dollars for frontend display
        $lines = [];
        foreach ($transaction->lines() as $line) {
            $amountCents = $line->amount()->cents();
            $amountDollars = $amountCents / 100;
            
            $lines[] = [
                'account_id' => $line->accountId()->toString(),
                'account_name' => $line->accountId()->toString(), // Frontend resolves name
                'debit' => $line->isDebit() ? $amountDollars : 0,
                'credit' => $line->isCredit() ? $amountDollars : 0,
                'description' => $line->description(),
            ];
        }
        
        return [
            'id' => $transaction->id()->toString(),
            'company_id' => $transaction->companyId()->toString(),
            'description' => $transaction->description(),
            'status' => $transaction->status()->value,
            'date' => $transaction->transactionDate()->format('Y-m-d'),
            // Primary field for display - backend converts cents to dollars
            'total_amount' => $totalDebitsCents / 100,
            // Raw cents values for precision if needed
            'total_debits_cents' => $totalDebitsCents,
            'total_credits_cents' => $totalCreditsCents,
            'reference_number' => $transaction->referenceNumber(),
            'created_at' => $transaction->createdAt()->format('Y-m-d\TH:i:s\Z'),
            'posted_at' => $transaction->postedAt()?->format('Y-m-d\TH:i:s\Z'),
            'lines' => $lines,
        ];
    }
}
