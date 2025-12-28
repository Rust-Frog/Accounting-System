<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Controller\Traits\SafeExceptionHandlerTrait;

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
    use SafeExceptionHandlerTrait;

    public function __construct(
        private readonly GenerateTrialBalanceHandler $handler,
        private readonly \Domain\Reporting\Service\ReportExportService $exportService
    ) {
    }

    /**
     * GET /api/v1/companies/{companyId}/trial-balance
     * 
     * Query params:
     * - as_of_date: (optional) Date for the trial balance (default: today)
     * - format: pdf|csv (optional)
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
        $format = $queryParams['format'] ?? null;

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

            if ($format === 'csv') {
                return $this->exportCsv($result);
            }
            if ($format === 'pdf') {
                return $this->exportPdf($result, $asOfDate);
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
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($data['accounts'] ?? [] as $account) {
            $debit = isset($account['debit']) ? $account['debit'] / 100 : 0;
            $credit = isset($account['credit']) ? $account['credit'] / 100 : 0;
            
            $rows[] = [
                $account['code'],
                $account['name'],
                $debit > 0 ? number_format($debit, 2) : '',
                $credit > 0 ? number_format($credit, 2) : ''
            ];
            
            $totalDebit += $debit;
            $totalCredit += $credit;
        }
        
        $rows[] = ['', 'Totals', number_format($totalDebit, 2), number_format($totalCredit, 2)];

        $csvContent = $this->exportService->exportToCsv($rows, ['Code', 'Account', 'Debit', 'Credit']);

        return new \Api\Response\CsvResponse($csvContent, 'trial_balance.csv');
    }

    private function exportPdf(array $data, string $date): ResponseInterface
    {
        $html = "
            <html>
            <head>
                <style>
                    body { font-family: Helvetica, sans-serif; }
                    .header { text-align: center; margin-bottom: 20px; }
                    .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    .table th, .table td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                    .amount { text-align: right; }
                    .total { font-weight: bold; background-color: #f5f5f5; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h2>Trial Balance</h2>
                    <p>As of: $date</p>
                </div>
                <table class='table'>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Account</th>
                            <th class='amount'>Debit</th>
                            <th class='amount'>Credit</th>
                        </tr>
                    </thead>
                    <tbody>";

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($data['accounts'] ?? [] as $account) {
            $debit = isset($account['debit']) ? $account['debit'] / 100 : 0;
            $credit = isset($account['credit']) ? $account['credit'] / 100 : 0;
            $totalDebit += $debit;
            $totalCredit += $credit;

            $debitStr = $debit > 0 ? number_format($debit, 2) : '';
            $creditStr = $credit > 0 ? number_format($credit, 2) : '';

            $html .= "
                <tr>
                    <td>{$account['code']}</td>
                    <td>{$account['name']}</td>
                    <td class='amount'>{$debitStr}</td>
                    <td class='amount'>{$creditStr}</td>
                </tr>";
        }

        $totalDebitStr = number_format($totalDebit, 2);
        $totalCreditStr = number_format($totalCredit, 2);

        $html .= "
                        <tr class='total'>
                            <td colspan='2'>Totals</td>
                            <td class='amount'>{$totalDebitStr}</td>
                            <td class='amount'>{$totalCreditStr}</td>
                        </tr>
                    </tbody>
                </table>
            </body>
            </html>
        ";

        $pdfContent = $this->exportService->exportToPdf($html);
        return new \Api\Response\PdfResponse($pdfContent, 'trial_balance.pdf');
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
