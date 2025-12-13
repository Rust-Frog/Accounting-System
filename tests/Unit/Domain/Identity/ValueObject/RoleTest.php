<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\ValueObject;

use Domain\Identity\ValueObject\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function test_has_admin_role(): void
    {
        $role = Role::ADMIN;
        $this->assertEquals('admin', $role->value);
    }

    public function test_has_tenant_role(): void
    {
        $role = Role::TENANT;
        $this->assertEquals('tenant', $role->value);
    }

    public function test_is_admin_returns_true_for_admin(): void
    {
        $role = Role::ADMIN;
        $this->assertTrue($role->isAdmin());
    }

    public function test_is_admin_returns_false_for_tenant(): void
    {
        $role = Role::TENANT;
        $this->assertFalse($role->isAdmin());
    }

    public function test_can_approve_returns_true_for_admin(): void
    {
        $role = Role::ADMIN;
        $this->assertTrue($role->canApprove());
    }

    public function test_can_approve_returns_false_for_tenant(): void
    {
        $role = Role::TENANT;
        $this->assertFalse($role->canApprove());
    }
}
