<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Controller\Traits\SafeExceptionHandlerTrait;

use Api\Response\JsonResponse;
use Application\Command\Reporting\GenerateBalanceSheetCommand;
use Application\Handler\Reporting\GenerateBalanceSheetHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Balance Sheet API Controller.
 * 
 * Generates on-demand balance sheet reports showing assets, liabilities, and equity.
 * BR-FR-001: Assets = Liabilities + Equity
 */
final class BalanceSheetController
{
    use SafeExceptionHandlerTrait;

    public function __construct(
        private readonly GenerateBalanceSheetHandler $handler
    ) {
    }

    /**
     * GET /api/v1/companies/{companyId}/balance-sheet
     * 
     * Query params:
     *   - as_of_date: ISO date (optional, defaults to today)
     */
    public function generate(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');

        if ($companyId === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        $queryParams = $request->getQueryParams();
        $asOfDate = $queryParams['as_of_date'] ?? null;

        // Validate date format
        if ($asOfDate && !$this->isValidDate($asOfDate)) {
            return JsonResponse::error('Invalid as_of_date format. Use YYYY-MM-DD', 422);
        }

        try {
            $command = new GenerateBalanceSheetCommand(
                companyId: $companyId,
                asOfDate: $asOfDate
            );

            $result = $this->handler->handle($command);

            return JsonResponse::success($result);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
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
