<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\Service\EdgeCaseDetectionServiceInterface;
use Domain\Transaction\Service\TransactionValidationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Transaction Validation Controller.
 * 
 * Provides pre-validation endpoint for frontend to check
 * transaction validity before submitting.
 */
final class TransactionValidationController
{
    public function __construct(
        private readonly TransactionValidationService $validationService,
        private readonly EdgeCaseDetectionServiceInterface $edgeCaseDetectionService,
    ) {
    }

    /**
     * POST /api/v1/companies/{companyId}/transactions/validate
     * 
     * Validates transaction lines without creating the transaction.
     * 
     * Request body:
     * {
     *   "lines": [
     *     {"account_id": "...", "debit_cents": 10000, "credit_cents": 0},
     *     {"account_id": "...", "debit_cents": 0, "credit_cents": 10000}
     *   ],
     *   "date": "2025-01-15",
     *   "description": "Transaction description"
     * }
     * 
     * Response:
     * {
     *   "valid": true/false,
     *   "errors": ["Error message 1", "Error message 2"],
     *   "edge_cases": {
     *     "has_flags": true/false,
     *     "requires_approval": true/false,
     *     "flags": [...],
     *     "suggested_approval_type": "high_value" | null
     *   }
     * }
     */
    public function validate(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $companyId = $request->getAttribute('companyId');
            if (!$companyId) {
                return JsonResponse::error('Company ID is required', 400);
            }

            $body = $request->getParsedBody();
            $lines = $body['lines'] ?? [];
            $description = $body['description'] ?? '';
            $dateString = $body['date'] ?? date('Y-m-d');

            if (!is_array($lines)) {
                return JsonResponse::error('Lines must be an array', 400);
            }

            $companyIdVO = CompanyId::fromString($companyId);
            $transactionDate = new \DateTimeImmutable($dateString);

            // Hard-block validation
            $result = $this->validationService->validate($lines, $companyIdVO);

            // Edge case detection (runs regardless of hard-block result for frontend awareness)
            $edgeCaseResult = $this->edgeCaseDetectionService->detect(
                $lines,
                $transactionDate,
                $description,
                $companyIdVO,
            );

            return JsonResponse::success([
                'valid' => $result->isValid(),
                'errors' => $result->errors(),
                'edge_cases' => $edgeCaseResult->toArray(),
            ]);

        } catch (\Throwable $e) {
            return JsonResponse::error(
                'Validation failed: ' . $e->getMessage(),
                500
            );
        }
    }
}
