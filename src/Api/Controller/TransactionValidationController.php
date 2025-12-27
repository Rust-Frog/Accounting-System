<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use Domain\Company\ValueObject\CompanyId;
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
        private readonly TransactionValidationService $validationService
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
     *   "date": "2025-01-15" // optional, for date-based validation
     * }
     * 
     * Response:
     * {
     *   "valid": true/false,
     *   "errors": ["Error message 1", "Error message 2"]
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

            if (!is_array($lines)) {
                return JsonResponse::error('Lines must be an array', 400);
            }

            $result = $this->validationService->validate(
                $lines,
                CompanyId::fromString($companyId)
            );

            return JsonResponse::success([
                'valid' => $result->isValid(),
                'errors' => $result->errors(),
            ]);

        } catch (\Throwable $e) {
            return JsonResponse::error(
                'Validation failed: ' . $e->getMessage(),
                500
            );
        }
    }
}
