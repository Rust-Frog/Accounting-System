<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use Application\Command\Reporting\GenerateIncomeStatementCommand;
use Application\Handler\Reporting\GenerateIncomeStatementHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Income Statement API Controller.
 * 
 * Generates on-demand income statement (profit & loss) reports.
 */
final class IncomeStatementController
{
    public function __construct(
        private readonly GenerateIncomeStatementHandler $handler
    ) {
    }

    /**
     * GET /api/v1/companies/{companyId}/income-statement
     * 
     * Query params:
     *   - start_date: ISO date (optional)
     *   - end_date: ISO date (optional)
     */
    public function generate(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');

        if ($companyId === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        $queryParams = $request->getQueryParams();
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;

        // Validate date formats
        if ($startDate && !$this->isValidDate($startDate)) {
            return JsonResponse::error('Invalid start_date format. Use YYYY-MM-DD', 422);
        }
        if ($endDate && !$this->isValidDate($endDate)) {
            return JsonResponse::error('Invalid end_date format. Use YYYY-MM-DD', 422);
        }

        try {
            $command = new GenerateIncomeStatementCommand(
                companyId: $companyId,
                startDate: $startDate,
                endDate: $endDate
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
