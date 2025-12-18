<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use Application\Command\Transaction\CreateTransactionCommand;
use Application\Command\Transaction\PostTransactionCommand;
use Application\Command\Transaction\TransactionLineData;
use Application\Command\Transaction\VoidTransactionCommand;
use Application\Dto\Transaction\TransactionDto;
use Application\Handler\Transaction\CreateTransactionHandler;
use Application\Handler\Transaction\PostTransactionHandler;
use Application\Handler\Transaction\VoidTransactionHandler;
use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\ValueObject\TransactionId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Transaction controller for journal entry management.
 * Uses Application layer handlers for proper domain type translation.
 */
final class TransactionController
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly CreateTransactionHandler $createHandler,
        private readonly PostTransactionHandler $postHandler,
        private readonly VoidTransactionHandler $voidHandler,
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
            $transactions = $this->transactionRepository->findByCompany(
                CompanyId::fromString($companyId)
            );

            $data = array_map(fn($t) => $this->formatTransactionSummary($t), $transactions);

            return JsonResponse::success($data);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 500);
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

        try {
            $transaction = $this->transactionRepository->findById(
                TransactionId::fromString($id)
            );

            if ($transaction === null) {
                return JsonResponse::error('Transaction not found', 404);
            }

            return JsonResponse::success($this->formatTransactionSummary($transaction));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 500);
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

        if ($companyId === null || $userId === null) {
            return JsonResponse::error('Company ID and authentication required', 400);
        }

        $errorResponse = $this->validateCreateRequest($body);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        try {
            $lines = $this->parseTransactionLines($body['lines']);

            $command = new CreateTransactionCommand(
                companyId: $companyId,
                createdBy: $userId,
                description: $body['description'],
                currency: $body['currency'] ?? 'USD',
                lines: $lines,
                transactionDate: $body['date'] ?? null,
                referenceNumber: $body['reference_number'] ?? null,
            );

            $dto = $this->createHandler->handle($command);

            return JsonResponse::created($dto->toArray());
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    private function validateCreateRequest(array $body): ?ResponseInterface
    {
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
        return $this->handleTransactionAction(
            $request,
            fn($id, $userId) => $this->postHandler->handle(
                new PostTransactionCommand($id, $userId)
            )
        );
    }

    public function void(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handleTransactionAction(
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
    }

    private function handleTransactionAction(ServerRequestInterface $request, callable $action): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $userId = $request->getAttribute('user_id');

        if ($id === null || $userId === null) {
            return JsonResponse::error('Transaction ID and authentication required', 400);
        }

        try {
            $dto = $action($id, $userId, $request);
            return JsonResponse::success($dto->toArray());
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Format transaction entity for list/get responses.
     * Uses domain entity accessors directly for read operations.
     */
    private function formatTransactionSummary(mixed $transaction): array
    {
        return [
            'id' => $transaction->id()->toString(),
            'company_id' => $transaction->companyId()->toString(),
            'description' => $transaction->description(),
            'status' => $transaction->status()->value,
            'date' => $transaction->transactionDate()->format('Y-m-d'),
            'total_debits_cents' => $transaction->totalDebits()->cents(),
            'total_credits_cents' => $transaction->totalCredits()->cents(),
            'reference_number' => $transaction->referenceNumber(),
            'created_at' => $transaction->createdAt()->format('Y-m-d\TH:i:s\Z'),
            'posted_at' => $transaction->postedAt()?->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
