<?php

declare(strict_types=1);

namespace Infrastructure\Service;

use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\Service\TransactionNumberGeneratorInterface;

/**
 * Generates unique transaction numbers for companies.
 * Format: TXN-YYYYMM-XXXXX (e.g., TXN-202412-00001)
 * 
 * Uses database to track last sequence per company/month.
 */
final class TransactionNumberGenerator implements TransactionNumberGeneratorInterface
{
    public function __construct(
        private readonly \PDO $pdo
    ) {
    }

    /**
     * Generate unique transaction number for company.
     * Thread-safe using database locking.
     */
    public function generateNextNumber(CompanyId $companyId): string
    {
        $yearMonth = date('Ym');
        $prefix = 'TXN';
        
        // Support nested transactions/existing transactions
        $isAlreadyInTransaction = $this->pdo->inTransaction();
        if (!$isAlreadyInTransaction) {
            $this->pdo->beginTransaction();
        }
        
        try {
            // Get or create sequence record with lock
            $stmt = $this->pdo->prepare(
                'SELECT sequence FROM transaction_sequences 
                 WHERE company_id = :company_id AND period = :period 
                 FOR UPDATE'
            );
            $stmt->execute([
                'company_id' => $companyId->toString(),
                'period' => $yearMonth,
            ]);
            
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($row) {
                $nextSequence = (int) $row['sequence'] + 1;
                
                $updateStmt = $this->pdo->prepare(
                    'UPDATE transaction_sequences 
                     SET sequence = :sequence, updated_at = NOW() 
                     WHERE company_id = :company_id AND period = :period'
                );
                $updateStmt->execute([
                    'sequence' => $nextSequence,
                    'company_id' => $companyId->toString(),
                    'period' => $yearMonth,
                ]);
            } else {
                $nextSequence = 1;
                
                $insertStmt = $this->pdo->prepare(
                    'INSERT INTO transaction_sequences (company_id, period, sequence, created_at, updated_at) 
                     VALUES (:company_id, :period, :sequence, NOW(), NOW())'
                );
                $insertStmt->execute([
                    'company_id' => $companyId->toString(),
                    'period' => $yearMonth,
                    'sequence' => $nextSequence,
                ]);
            }
            
            if (!$isAlreadyInTransaction) {
                $this->pdo->commit();
            }
            
            // Format: TXN-YYYYMM-XXXXX
            return sprintf('%s-%s-%05d', $prefix, $yearMonth, $nextSequence);
            
        } catch (\Throwable $e) {
            if (!$isAlreadyInTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
