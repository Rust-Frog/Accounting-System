<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\ValueObject;

use Domain\Transaction\ValueObject\EdgeCaseDetectionResult;
use Domain\Transaction\ValueObject\EdgeCaseFlag;
use PHPUnit\Framework\TestCase;

final class EdgeCaseDetectionResultTest extends TestCase
{
    public function test_clean_result_has_no_flags(): void
    {
        $result = EdgeCaseDetectionResult::clean();

        $this->assertTrue($result->isClean());
        $this->assertFalse($result->hasFlags());
        $this->assertFalse($result->requiresApproval());
        $this->assertEmpty($result->flags());
    }

    public function test_result_with_approval_required_flag(): void
    {
        $flag = EdgeCaseFlag::largeAmount(5_000_000, 1_000_000);
        $result = EdgeCaseDetectionResult::withFlags([$flag]);

        $this->assertFalse($result->isClean());
        $this->assertTrue($result->hasFlags());
        $this->assertTrue($result->requiresApproval());
        $this->assertCount(1, $result->flags());
    }

    public function test_result_with_review_only_flag(): void
    {
        $flag = EdgeCaseFlag::roundNumber(10_000_000);
        $result = EdgeCaseDetectionResult::withFlags([$flag]);

        $this->assertTrue($result->hasFlags());
        $this->assertFalse($result->requiresApproval());
        $this->assertTrue($result->hasReviewOnlyFlags());
    }

    public function test_result_with_mixed_flags(): void
    {
        $approvalFlag = EdgeCaseFlag::largeAmount(5_000_000, 1_000_000);
        $reviewFlag = EdgeCaseFlag::roundNumber(5_000_000);
        $result = EdgeCaseDetectionResult::withFlags([$approvalFlag, $reviewFlag]);

        $this->assertTrue($result->requiresApproval());
        $this->assertTrue($result->hasReviewOnlyFlags());
        $this->assertCount(2, $result->flags());
        $this->assertCount(1, $result->approvalRequiredFlags());
        $this->assertCount(1, $result->reviewOnlyFlags());
    }

    public function test_merge_combines_results(): void
    {
        $result1 = EdgeCaseDetectionResult::withFlags([EdgeCaseFlag::largeAmount(5_000_000, 1_000_000)]);
        $result2 = EdgeCaseDetectionResult::withFlags([EdgeCaseFlag::backdated('2024-11-01', 56)]);

        $merged = $result1->merge($result2);

        $this->assertCount(2, $merged->flags());
        $this->assertTrue($merged->requiresApproval());
    }

    public function test_suggested_approval_type_for_high_value(): void
    {
        $result = EdgeCaseDetectionResult::withFlags([EdgeCaseFlag::largeAmount(5_000_000, 1_000_000)]);

        $this->assertSame('high_value', $result->suggestedApprovalType());
    }

    public function test_suggested_approval_type_for_negative_balance(): void
    {
        $result = EdgeCaseDetectionResult::withFlags([EdgeCaseFlag::negativeBalance('Cash', 1000, -500)]);

        $this->assertSame('negative_equity', $result->suggestedApprovalType());
    }

    public function test_serializes_to_array(): void
    {
        $result = EdgeCaseDetectionResult::withFlags([EdgeCaseFlag::largeAmount(5_000_000, 1_000_000)]);
        $array = $result->toArray();

        $this->assertArrayHasKey('has_flags', $array);
        $this->assertArrayHasKey('requires_approval', $array);
        $this->assertArrayHasKey('flags', $array);
        $this->assertArrayHasKey('suggested_approval_type', $array);
    }
}
