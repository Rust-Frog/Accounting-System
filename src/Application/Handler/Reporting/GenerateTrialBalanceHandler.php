<?php

declare(strict_types=1);

namespace Application\Handler\Reporting;

use Application\Command\CommandInterface;
use Application\Command\Reporting\GenerateTrialBalanceCommand;
use Application\Handler\HandlerInterface;
use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Reporting\Entity\TrialBalance;
use Domain\Reporting\Service\TrialBalanceGeneratorInterface;

/**
 * Handler for generating trial balance reports.
 * 
 * Following Hexagonal Architecture:
 * - Uses domain service interface (port)
 * - Infrastructure provides implementation (adapter)
 * 
 * @implements HandlerInterface<GenerateTrialBalanceCommand>
 */
final readonly class GenerateTrialBalanceHandler implements HandlerInterface
{
    public function __construct(
        private TrialBalanceGeneratorInterface $trialBalanceGenerator
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof GenerateTrialBalanceCommand);

        $companyId = CompanyId::fromString($command->companyId);
        $asOfDate = new DateTimeImmutable($command->asOfDate);

        // Generate the trial balance using domain service
        $trialBalance = $this->trialBalanceGenerator->generate($companyId, $asOfDate);

        // Return formatted response
        return $this->formatResponse($trialBalance);
    }

    /**
     * Format trial balance for API response.
     * 
     * @return array<string, mixed>
     */
    private function formatResponse(TrialBalance $trialBalance): array
    {
        $entries = [];
        foreach ($trialBalance->entries() as $entry) {
            $entries[] = [
                'account_id' => $entry->accountId(),
                'account_code' => $entry->accountCode(),
                'account_name' => $entry->accountName(),
                'account_type' => $entry->accountType(),
                'debit_cents' => $entry->debitBalanceCents(),
                'credit_cents' => $entry->creditBalanceCents(),
            ];
        }

        return [
            'id' => $trialBalance->id()->toString(),
            'company_id' => $trialBalance->companyId()->toString(),
            'as_of_date' => $trialBalance->period()->endDate()->format('Y-m-d'),
            'generated_at' => $trialBalance->generatedAt()->format('Y-m-d\TH:i:s\Z'),
            'entries' => $entries,
            'totals' => [
                'debit_cents' => $trialBalance->totalDebitsCents(),
                'credit_cents' => $trialBalance->totalCreditsCents(),
            ],
            'is_balanced' => $trialBalance->isBalanced(),
            'difference_cents' => $trialBalance->differenceCents(),
        ];
    }
}
