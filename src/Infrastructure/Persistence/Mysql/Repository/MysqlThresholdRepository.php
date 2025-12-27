<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Repository;

use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\Repository\ThresholdRepositoryInterface;
use Domain\Transaction\ValueObject\EdgeCaseThresholds;
use PDO;

final readonly class MysqlThresholdRepository implements ThresholdRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function getForCompany(CompanyId $companyId): EdgeCaseThresholds
    {
        $sql = <<<SQL
            SELECT
                large_transaction_threshold_cents,
                backdated_days_threshold,
                future_dated_allowed,
                require_approval_contra_entry,
                require_approval_equity_adjustment,
                require_approval_negative_balance,
                flag_round_numbers,
                flag_period_end_entries,
                dormant_account_days_threshold
            FROM company_settings
            WHERE company_id = :company_id
            LIMIT 1
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['company_id' => $companyId->toString()]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return EdgeCaseThresholds::defaults();
        }

        return EdgeCaseThresholds::fromDatabaseRow($row);
    }
}
