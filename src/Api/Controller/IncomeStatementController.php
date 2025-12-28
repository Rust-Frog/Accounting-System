<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Controller\Traits\SafeExceptionHandlerTrait;

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
    use SafeExceptionHandlerTrait;

    public function __construct(
        private readonly GenerateIncomeStatementHandler $handler,
        private readonly \Domain\Reporting\Service\ReportExportService $exportService
    ) {
    }

    /**
     * GET /api/v1/companies/{companyId}/income-statement
     * 
     * Query params:
     *   - start_date: ISO date (optional)
     *   - end_date: ISO date (optional)
     *   - format: pdf|csv (optional)
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
        $format = $queryParams['format'] ?? null;

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

            if ($format === 'csv') {
                return $this->exportCsv($result);
            }
            if ($format === 'pdf') {
                return $this->exportPdf($result, $startDate, $endDate);
            }

            return JsonResponse::success($result);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    private function exportCsv(array $data): ResponseInterface
    {
        $rows = [];
        
        // Revenue
        $rows[] = ['REVENUE', ''];
        foreach ($data['revenue']['items'] ?? [] as $item) {
            $rows[] = [$item['account_name'], number_format($item['amount'] / 100, 2)];
        }
        $rows[] = ['Total Revenue', number_format(($data['revenue']['total'] ?? 0) / 100, 2)];
        $rows[] = ['', ''];

        // Expenses
        $rows[] = ['EXPENSES', ''];
        foreach ($data['expenses']['items'] ?? [] as $item) {
            $rows[] = [$item['account_name'], number_format($item['amount'] / 100, 2)];
        }
        $rows[] = ['Total Expenses', number_format(($data['expenses']['total'] ?? 0) / 100, 2)];
        $rows[] = ['', ''];

        // Net Income
        $rows[] = ['NET INCOME', number_format(($data['net_income'] ?? 0) / 100, 2)];

        $csvContent = $this->exportService->exportToCsv($rows, ['Account', 'Amount']);

        return new \Api\Response\CsvResponse($csvContent, 'income_statement.csv');
    }

    private function exportPdf(array $data, ?string $start, ?string $end): ResponseInterface
    {
        $period = ($start && $end) ? "$start to $end" : "All Time";
        
        $html = "
            <html>
            <head>
                <style>
                    body { font-family: Helvetica, sans-serif; }
                    .header { text-align: center; margin-bottom: 20px; }
                    .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    .table th, .table td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                    .amount { text-align: right; }
                    .total { font-weight: bold; }
                    .section-header { font-weight: bold; background-color: #f5f5f5; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h2>Income Statement</h2>
                    <p>Period: $period</p>
                </div>
                <table class='table'>
                    <thead>
                        <tr><th>Account</th><th class='amount'>Amount</th></tr>
                    </thead>
                    <tbody>
                        <tr class='section-header'><td colspan='2'>REVENUE</td></tr>";

        foreach ($data['revenue']['items'] ?? [] as $item) {
            $amount = number_format($item['amount'] / 100, 2);
            $html .= "<tr><td>{$item['account_name']}</td><td class='amount'>{$amount}</td></tr>";
        }
        $totalRevenue = number_format(($data['revenue']['total'] ?? 0) / 100, 2);
        $html .= "<tr class='total'><td>Total Revenue</td><td class='amount'>{$totalRevenue}</td></tr>";

        $html .= "<tr class='section-header'><td colspan='2'>EXPENSES</td></tr>";
        foreach ($data['expenses']['items'] ?? [] as $item) {
            $amount = number_format($item['amount'] / 100, 2);
            $html .= "<tr><td>{$item['account_name']}</td><td class='amount'>{$amount}</td></tr>";
        }
        $totalExpenses = number_format(($data['expenses']['total'] ?? 0) / 100, 2);
        $html .= "<tr class='total'><td>Total Expenses</td><td class='amount'>{$totalExpenses}</td></tr>";

        $netIncome = number_format(($data['net_income'] ?? 0) / 100, 2);
        $html .= "
                        <tr class='total' style='font-size: 1.2em; border-top: 2px solid #000;'>
                            <td>NET INCOME</td>
                            <td class='amount'>{$netIncome}</td>
                        </tr>
                    </tbody>
                </table>
            </body>
            </html>
        ";

        $pdfContent = $this->exportService->exportToPdf($html);
        return new \Api\Response\PdfResponse($pdfContent, 'income_statement.pdf');
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
