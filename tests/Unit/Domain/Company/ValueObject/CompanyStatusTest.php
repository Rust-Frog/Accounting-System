<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Company\ValueObject;

use Domain\Company\ValueObject\CompanyStatus;
use PHPUnit\Framework\TestCase;

final class CompanyStatusTest extends TestCase
{
    public function test_has_pending_status(): void
    {
        $status = CompanyStatus::PENDING;
        $this->assertEquals('pending', $status->value);
    }

    public function test_has_active_status(): void
    {
        $status = CompanyStatus::ACTIVE;
        $this->assertEquals('active', $status->value);
    }

    public function test_has_suspended_status(): void
    {
        $status = CompanyStatus::SUSPENDED;
        $this->assertEquals('suspended', $status->value);
    }

    public function test_is_pending_returns_true_for_pending(): void
    {
        $status = CompanyStatus::PENDING;
        $this->assertTrue($status->isPending());
    }

    public function test_is_active_returns_true_for_active(): void
    {
        $status = CompanyStatus::ACTIVE;
        $this->assertTrue($status->isActive());
    }

    public function test_can_operate_returns_true_only_for_active(): void
    {
        $this->assertTrue(CompanyStatus::ACTIVE->canOperate());
        $this->assertFalse(CompanyStatus::PENDING->canOperate());
        $this->assertFalse(CompanyStatus::SUSPENDED->canOperate());
    }
}
