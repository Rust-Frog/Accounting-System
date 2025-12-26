<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Controller\Traits\SafeExceptionHandlerTrait;

use Api\Response\JsonResponse;
use Application\Command\Reporting\GenerateReportCommand;
use Application\Handler\Reporting\GenerateReportHandler;
use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\Entity\Report;
use Domain\Reporting\Repository\ReportRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Report controller for financial reports.
 */
final class ReportController
{
    use SafeExceptionHandlerTrait;

    public function __construct(
        private readonly ReportRepositoryInterface $reportRepository,
        private readonly ?GenerateReportHandler $generateHandler = null
    ) {
    }

    /**
     * GET /api/v1/companies/{companyId}/reports
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        if ($companyId === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        try {
            $reports = $this->reportRepository->findByCompany(
                CompanyId::fromString($companyId)
            );

            $data = array_map(fn(Report $r) => $this->formatReport($r, false), $reports);

            return JsonResponse::success($data);
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * GET /api/v1/companies/{companyId}/reports/{id}
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        if ($id === null) {
            return JsonResponse::error('Report ID required', 400);
        }

        try {
            $report = $this->reportRepository->findById(
                \Domain\Reporting\ValueObject\ReportId::fromString($id)
            );

            if ($report === null) {
                return JsonResponse::error('Report not found', 404);
            }

            return JsonResponse::success($this->formatReport($report, true));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * POST /api/v1/companies/{companyId}/reports/generate
     * 
     * Request body:
     * {
     *   "report_type": "balance_sheet" | "income_statement",
     *   "period_start": "2024-01-01",
     *   "period_end": "2024-12-31"
     * }
     */
    public function generate(ServerRequestInterface $request): ResponseInterface
    {
        $validationError = $this->validateGenerateRequest($request);
        if ($validationError !== null) {
            return $validationError;
        }

        $companyId = $request->getAttribute('companyId');
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        try {
            $command = new GenerateReportCommand(
                companyId: $companyId,
                reportType: $body['report_type'],
                periodStart: $body['period_start'],
                periodEnd: $body['period_end'],
                generatedBy: $userId,
            );

            $result = $this->generateHandler->handle($command);

            return JsonResponse::success($result, 201);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * Validate generate request and return error response if invalid.
     */
    private function validateGenerateRequest(ServerRequestInterface $request): ?ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        $userId = $request->getAttribute('user_id');
        
        if ($companyId === null || $userId === null) {
            return JsonResponse::error('Company ID and authentication required', 400);
        }

        if ($this->generateHandler === null) {
            return JsonResponse::error('Report generation not configured', 500);
        }

        $body = $request->getParsedBody();

        if (empty($body['report_type'])) {
            return JsonResponse::error('report_type is required (balance_sheet or income_statement)', 422);
        }
        
        if (empty($body['period_start']) || empty($body['period_end'])) {
            return JsonResponse::error('period_start and period_end are required (YYYY-MM-DD format)', 422);
        }

        return null;
    }

    /**
     * Format report for API response.
     * 
     * @param bool $includeData Whether to include full report data
     */
    private function formatReport(Report $report, bool $includeData): array
    {
        $formatted = [
            'id' => $report->id()->toString(),
            'company_id' => $report->companyId()->toString(),
            'type' => $report->type(),
            'period' => [
                'start' => $report->period()->startDate()->format('Y-m-d'),
                'end' => $report->period()->endDate()->format('Y-m-d'),
                'type' => $report->period()->type()->value,
            ],
            'generated_at' => $report->generatedAt()->format('Y-m-d\TH:i:s\Z'),
        ];

        if ($includeData) {
            $formatted['data'] = $report->data();
        }

        return $formatted;
    }
}

