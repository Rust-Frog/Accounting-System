<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use Application\Command\Reporting\GenerateTrialBalanceCommand;
use Application\Handler\Reporting\GenerateTrialBalanceHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Trial Balance API Controller.
 * 
 * Generates on-demand trial balance reports showing all account balances
 * with verification that debits equal credits.
 */
final class TrialBalanceController
{
    public function __construct(
        private readonly GenerateTrialBalanceHandler $handler
    ) {
    }

    /**
     * GET /api/v1/companies/{companyId}/trial-balance
     * 
     * Query params:
     * - as_of_date: (optional) Date for the trial balance (default: today)
     * 
     * Returns trial balance with all account debit/credit balances.
     */
    public function generate(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        $userId = $request->getAttribute('user_id');
        
        if ($companyId === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        $queryParams = $request->getQueryParams();
        $asOfDate = $queryParams['as_of_date'] ?? date('Y-m-d');

        // Validate date format
        if (!$this->isValidDate($asOfDate)) {
            return JsonResponse::error('Invalid as_of_date format. Use YYYY-MM-DD', 422);
        }

        try {
            $command = new GenerateTrialBalanceCommand(
                companyId: $companyId,
                asOfDate: $asOfDate,
                generatedBy: $userId ?? 'system'
            );

            $result = $this->handler->handle($command);

            return JsonResponse::success($result);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Validate date format YYYY-MM-DD.
     */
    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
