<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\ValueObject;

use Domain\Identity\ValueObject\RegistrationStatus;
use PHPUnit\Framework\TestCase;

final class RegistrationStatusTest extends TestCase
{
    public function test_has_pending_status(): void
    {
        $status = RegistrationStatus::PENDING;
        $this->assertEquals('pending', $status->value);
    }

    public function test_has_approved_status(): void
    {
        $status = RegistrationStatus::APPROVED;
        $this->assertEquals('approved', $status->value);
    }

    public function test_has_declined_status(): void
    {
        $status = RegistrationStatus::DECLINED;
        $this->assertEquals('declined', $status->value);
    }

    public function test_is_pending_returns_true_for_pending(): void
    {
        $status = RegistrationStatus::PENDING;
        $this->assertTrue($status->isPending());
    }

    public function test_is_pending_returns_false_for_approved(): void
    {
        $status = RegistrationStatus::APPROVED;
        $this->assertFalse($status->isPending());
    }

    public function test_can_authenticate_returns_true_for_approved(): void
    {
        $status = RegistrationStatus::APPROVED;
        $this->assertTrue($status->canAuthenticate());
    }

    public function test_can_authenticate_returns_false_for_pending(): void
    {
        $status = RegistrationStatus::PENDING;
        $this->assertFalse($status->canAuthenticate());
    }

    public function test_can_authenticate_returns_false_for_declined(): void
    {
        $status = RegistrationStatus::DECLINED;
        $this->assertFalse($status->canAuthenticate());
    }
}
