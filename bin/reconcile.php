<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Infrastructure\Container\ContainerBuilder;
use PDO;

echo "Starting Balance Reconciliation...\n";

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Build container
$container = ContainerBuilder::build();
$pdo = $container->get(PDO::class);

// 1. Get Companies
$stmt = $pdo->query("SELECT id, company_name FROM companies");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($companies as $c) {
    $companyId = $c['id'];
    echo "Processing Company: {$c['company_name']} ({$companyId})\n";

    // 2. Get Accounts
    $stmtAcc = $pdo->prepare("SELECT id, code, type, currency FROM accounts WHERE company_id = ?");
    $stmtAcc->execute([$companyId]);
    $accounts = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);

    foreach ($accounts as $acc) {
        $accountId = $acc['id'];
        $type = $acc['type'];
        
        // Determine Normal Balance side (Asset/Expense = Debit)
        $isDebitNormal = in_array($type, ['asset', 'expense']);
        
        // 3. Calculate Live Balance from Transaction Lines
        // Logic: SUM(Debit) - SUM(Credit) = Net Debit
        $sql = "SELECT 
                    COALESCE(SUM(tl.amount_cents), 0) as total_cents,
                    COALESCE(SUM(CASE WHEN tl.line_type = 'debit' THEN tl.amount_cents ELSE 0 END), 0) as total_debits,
                    COALESCE(SUM(CASE WHEN tl.line_type = 'credit' THEN tl.amount_cents ELSE 0 END), 0) as total_credits
                FROM transaction_lines tl
                INNER JOIN transactions t ON tl.transaction_id = t.id
                WHERE tl.account_id = ? 
                AND t.status = 'posted'";
        
        $stmtBal = $pdo->prepare($sql);
        $stmtBal->execute([$accountId]);
        $row = $stmtBal->fetch(PDO::FETCH_ASSOC);
        
        $totalDebits = (int)$row['total_debits'];
        $totalCredits = (int)$row['total_credits'];
        
        // Count transactions
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM transaction_lines tl INNER JOIN transactions t ON tl.transaction_id = t.id WHERE tl.account_id = ? AND t.status = 'posted'");
        $stmtCount->execute([$accountId]);
        $txnLineCount = (int)$stmtCount->fetchColumn();

        $netDebit = $totalDebits - $totalCredits;
        $currentBalance = $isDebitNormal ? $netDebit : -$netDebit;
        
        // 4. Update account_balances
        // Check for existing latest record
        $stmtCheck = $pdo->prepare("SELECT id FROM account_balances WHERE account_id = ? ORDER BY period_end DESC LIMIT 1");
        $stmtCheck->execute([$accountId]);
        $existingId = $stmtCheck->fetchColumn();
        
        if ($existingId) {
            // Update existing
            $sqlUpd = "UPDATE account_balances SET 
                        current_balance_cents = :bal,
                        total_debits_cents = :debits,
                        total_credits_cents = :credits,
                        transaction_count = :count,
                        updated_at = NOW()
                       WHERE id = :id";
            $stmtUpd = $pdo->prepare($sqlUpd);
            $stmtUpd->execute([
                'bal' => $currentBalance,
                'debits' => $totalDebits, 
                'credits' => $totalCredits,
                'count' => $txnLineCount,
                'id' => $existingId
            ]);
            // echo "Updated balance for account {$acc['code']}: $currentBalance\n";
        } else {
            // Insert new snapshot
            $id = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            $sqlIns = "INSERT INTO account_balances (
                        id, account_id, company_id, 
                        period_start, period_end, 
                        opening_balance_cents, current_balance_cents, 
                        total_debits_cents, total_credits_cents,
                        transaction_count, currency, updated_at
                       ) VALUES (
                        :id, :acc_id, :comp_id,
                        '1970-01-01', NOW(),
                        0, :bal,
                        :debits, :credits,
                        :count, :curr, NOW()
                       )";
            $stmtIns = $pdo->prepare($sqlIns);
            $stmtIns->execute([
                'id' => $id,
                'acc_id' => $accountId,
                'comp_id' => $companyId,
                'bal' => $currentBalance,
                'debits' => $totalDebits,
                'credits' => $totalCredits,
                'count' => $txnLineCount,
                'curr' => $acc['currency'] ?? 'USD'
            ]);
            echo "Created new balance snapshot for account {$acc['code']}: $currentBalance\n";
        }
    }
}

echo "Reconciliation Complete.\n";
