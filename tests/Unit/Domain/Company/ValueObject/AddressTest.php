<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Company\ValueObject;

use Domain\Company\ValueObject\Address;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    public function test_creates_address_with_all_fields(): void
    {
        $address = Address::create(
            street: '123 Main Street',
            city: 'Manila',
            state: 'Metro Manila',
            postalCode: '1000',
            country: 'Philippines'
        );

        $this->assertEquals('123 Main Street', $address->street());
        $this->assertEquals('Manila', $address->city());
        $this->assertEquals('Metro Manila', $address->state());
        $this->assertEquals('1000', $address->postalCode());
        $this->assertEquals('Philippines', $address->country());
    }

    public function test_rejects_empty_street(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Street cannot be empty');

        Address::create(
            street: '',
            city: 'Manila',
            state: 'Metro Manila',
            postalCode: '1000',
            country: 'Philippines'
        );
    }

    public function test_rejects_empty_city(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('City cannot be empty');

        Address::create(
            street: '123 Main Street',
            city: '',
            state: 'Metro Manila',
            postalCode: '1000',
            country: 'Philippines'
        );
    }

    public function test_rejects_empty_country(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Country cannot be empty');

        Address::create(
            street: '123 Main Street',
            city: 'Manila',
            state: 'Metro Manila',
            postalCode: '1000',
            country: ''
        );
    }

    public function test_state_is_optional(): void
    {
        $address = Address::create(
            street: '123 Main Street',
            city: 'Manila',
            state: null,
            postalCode: '1000',
            country: 'Philippines'
        );

        $this->assertNull($address->state());
    }

    public function test_postal_code_is_optional(): void
    {
        $address = Address::create(
            street: '123 Main Street',
            city: 'Manila',
            state: 'Metro Manila',
            postalCode: null,
            country: 'Philippines'
        );

        $this->assertNull($address->postalCode());
    }

    public function test_equals_returns_true_for_same_address(): void
    {
        $address1 = Address::create(
            street: '123 Main Street',
            city: 'Manila',
            state: 'Metro Manila',
            postalCode: '1000',
            country: 'Philippines'
        );

        $address2 = Address::create(
            street: '123 Main Street',
            city: 'Manila',
            state: 'Metro Manila',
            postalCode: '1000',
            country: 'Philippines'
        );

        $this->assertTrue($address1->equals($address2));
    }

    public function test_equals_returns_false_for_different_address(): void
    {
        $address1 = Address::create(
            street: '123 Main Street',
            city: 'Manila',
            state: 'Metro Manila',
            postalCode: '1000',
            country: 'Philippines'
        );

        $address2 = Address::create(
            street: '456 Other Street',
            city: 'Manila',
            state: 'Metro Manila',
            postalCode: '1000',
            country: 'Philippines'
        );

        $this->assertFalse($address1->equals($address2));
    }

    public function test_formats_as_string(): void
    {
        $address = Address::create(
            street: '123 Main Street',
            city: 'Manila',
            state: 'Metro Manila',
            postalCode: '1000',
            country: 'Philippines'
        );

        $formatted = $address->format();

        $this->assertStringContainsString('123 Main Street', $formatted);
        $this->assertStringContainsString('Manila', $formatted);
        $this->assertStringContainsString('Philippines', $formatted);
    }
}
