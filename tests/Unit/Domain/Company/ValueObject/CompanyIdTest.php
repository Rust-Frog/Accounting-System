<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Company\ValueObject;

use Domain\Company\ValueObject\CompanyId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CompanyIdTest extends TestCase
{
    public function test_generates_valid_company_id(): void
    {
        $companyId = CompanyId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $companyId->toString()
        );
    }

    public function test_creates_company_id_from_valid_string(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $companyId = CompanyId::fromString($uuidString);

        $this->assertEquals($uuidString, $companyId->toString());
    }

    public function test_rejects_invalid_company_id_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CompanyId::fromString('invalid-uuid');
    }

    public function test_equals_returns_true_for_same_company_id(): void
    {
        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $companyId1 = CompanyId::fromString($uuidString);
        $companyId2 = CompanyId::fromString($uuidString);

        $this->assertTrue($companyId1->equals($companyId2));
    }
}
