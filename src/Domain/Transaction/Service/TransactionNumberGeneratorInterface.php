<?php

declare(strict_types=1);

namespace Domain\Transaction\Service;

use Domain\Company\ValueObject\CompanyId;

/**
 * Service interface for generating transaction numbers.
 * Implementation should be in Application/Infrastructure layer.
 */
interface TransactionNumberGeneratorInterface
{
    /**
     * Generate unique transaction number for company.
     * Format: TXN-YYYYMM-XXXXX
     */
    public function generateNextNumber(CompanyId $companyId): string;
}
