<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use DateTimeImmutable;
use Domain\Company\ValueObject\CompanyId;
use Domain\Transaction\Repository\TransactionRepositoryInterface;
use Domain\Transaction\Service\EdgeCaseDetector\DuplicateTransactionDetector;
use PHPUnit\Framework\TestCase;

final class DuplicateTransactionDetectorTest extends TestCase
{
    private DuplicateTransactionDetector $detector;
    private TransactionRepositoryInterface $transactionRepository;
    private CompanyId $companyId;

    protected function setUp(): void
    {
        $this->transactionRepository = $this->createMock(TransactionRepositoryInterface::class);
        $this->detector = new DuplicateTransactionDetector($this->transactionRepository);
        $this->companyId = CompanyId::generate();
    }

    public function test_detects_exact_duplicate_transaction(): void
    {
        $description = 'Office supplies purchase';
        $totalAmountCents = 50_000;
        $transactionDate = new DateTimeImmutable('2025-01-15');

        // Repository returns that a similar transaction exists
        $this->transactionRepository->method('findSimilarTransaction')
            ->with(
                $this->companyId,
                $totalAmountCents,
                $description,
                $transactionDate,
            )
            ->willReturn('TXN-2025-000123');

        $result = $this->detector->detect(
            $totalAmountCents,
            $description,
            $transactionDate,
            $this->companyId,
        );

        $this->assertTrue($result->hasFlags());
        $this->assertSame('duplicate_transaction', $result->flags()[0]->type());
        $this->assertStringContainsString('TXN-2025-000123', $result->flags()[0]->description());
    }

    public function test_allows_unique_transaction(): void
    {
        $description = 'New equipment purchase';
        $totalAmountCents = 100_000;
        $transactionDate = new DateTimeImmutable('2025-01-15');

        // No similar transaction found
        $this->transactionRepository->method('findSimilarTransaction')
            ->willReturn(null);

        $result = $this->detector->detect(
            $totalAmountCents,
            $description,
            $transactionDate,
            $this->companyId,
        );

        $this->assertFalse($result->hasFlags());
    }

    public function test_detects_same_amount_same_day_different_description(): void
    {
        $description = 'Office supplies';
        $totalAmountCents = 50_000;
        $transactionDate = new DateTimeImmutable('2025-01-15');

        // Repository returns a similar transaction (same amount, same day)
        $this->transactionRepository->method('findSimilarTransaction')
            ->willReturn('TXN-2025-000456');

        $result = $this->detector->detect(
            $totalAmountCents,
            $description,
            $transactionDate,
            $this->companyId,
        );

        $this->assertTrue($result->hasFlags());
    }

    public function test_duplicate_flag_does_not_require_approval(): void
    {
        $this->transactionRepository->method('findSimilarTransaction')
            ->willReturn('TXN-2025-000789');

        $result = $this->detector->detect(
            50_000,
            'Test transaction',
            new DateTimeImmutable('2025-01-15'),
            $this->companyId,
        );

        $this->assertTrue($result->hasFlags());
        // Duplicate detection is informational, doesn't require approval
        $this->assertFalse($result->flags()[0]->requiresApproval());
    }
}
