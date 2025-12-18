<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;

trait DatabaseTestHelper
{
    protected function createCompany(PDO $pdo, string $id, string $name = 'Test Company'): void
    {
        $stmt = $pdo->prepare("INSERT INTO companies (
            id, company_name, legal_name, tax_id, address_street, address_city, address_country, created_at, updated_at
        ) VALUES (
            :id, :name, :legal_name, :tax_id, '123 Test St', 'Test City', 'Test Country', NOW(), NOW()
        )");
        $stmt->execute(['id' => $id, 'name' => $name, 'legal_name' => $name, 'tax_id' => 'TAX-' . $id]);
    }

    protected function createAccount(PDO $pdo, string $id, string $companyId, string $name = 'Test Account', string $type = 'asset', string $currency = 'USD'): void
    {
        $stmt = $pdo->prepare("INSERT INTO accounts (
            id, company_id, code, name, type, currency, is_active, created_at, updated_at
        ) VALUES (
            :id, :company_id, '1001', :name, :type, :currency, 1, NOW(), NOW()
        )");
        $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'name' => $name,
            'type' => $type,
            'currency' => $currency
        ]);
    }
    
    protected function createTransaction(PDO $pdo, string $id, string $companyId): void
    {
        $stmt = $pdo->prepare("INSERT INTO transactions (
            id, company_id, description, date, status, created_at, updated_at
        ) VALUES (
            :id, :company_id, 'Test Transaction', NOW(), 'POSTED', NOW(), NOW()
        )");
        $stmt->execute(['id' => $id, 'company_id' => $companyId]);
    }
    
    protected function createTransactionLine(PDO $pdo, string $id, string $transactionId, string $accountId, int $amount, string $type): void
    {
         $stmt = $pdo->prepare("INSERT INTO transaction_lines (
            id, transaction_id, account_id, amount_cents, line_type, description
        ) VALUES (
            :id, :transaction_id, :account_id, :amount, :type, 'Line'
        )");
        $stmt->execute([
            'id' => $id,
            'transaction_id' => $transactionId,
            'account_id' => $accountId,
            'amount' => $amount,
            'type' => $type
        ]);
    }

    protected function createUser(PDO $pdo, string $id, string $username = 'testuser'): void
    {
        $stmt = $pdo->prepare("INSERT INTO users (
            id, username, email, password_hash, role, registration_status, is_active, created_at, updated_at
        ) VALUES (
            :id, :username, :email, '\$2y\$10\$testhash', 'user', 'approved', 1, NOW(), NOW()
        )");
        $stmt->execute([
            'id' => $id,
            'username' => $username,
            'email' => $username . '@test.com'
        ]);
    }
}
