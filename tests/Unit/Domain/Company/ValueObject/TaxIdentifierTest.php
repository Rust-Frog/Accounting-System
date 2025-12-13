<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Company\ValueObject;

use Domain\Company\ValueObject\TaxIdentifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TaxIdentifierTest extends TestCase
{
    public function test_creates_tax_identifier_from_valid_string(): void
    {
        $taxId = TaxIdentifier::fromString('123-456-789');
        $this->assertEquals('123-456-789', $taxId->toString());
    }

    public function test_rejects_empty_tax_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TaxIdentifier::fromString('');
    }

    public function test_rejects_tax_identifier_with_only_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TaxIdentifier::fromString('   ');
    }

    public function test_trims_whitespace(): void
    {
        $taxId = TaxIdentifier::fromString('  123-456-789  ');
        $this->assertEquals('123-456-789', $taxId->toString());
    }

    public function test_equals_returns_true_for_same_tax_id(): void
    {
        $taxId1 = TaxIdentifier::fromString('123-456-789');
        $taxId2 = TaxIdentifier::fromString('123-456-789');

        $this->assertTrue($taxId1->equals($taxId2));
    }

    public function test_equals_returns_false_for_different_tax_id(): void
    {
        $taxId1 = TaxIdentifier::fromString('123-456-789');
        $taxId2 = TaxIdentifier::fromString('987-654-321');

        $this->assertFalse($taxId1->equals($taxId2));
    }
}
