<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObject;

use Domain\Shared\ValueObject\Currency;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    public function test_has_php_currency(): void
    {
        $currency = Currency::PHP;
        $this->assertEquals('PHP', $currency->value);
    }

    public function test_has_usd_currency(): void
    {
        $currency = Currency::USD;
        $this->assertEquals('USD', $currency->value);
    }

    public function test_has_eur_currency(): void
    {
        $currency = Currency::EUR;
        $this->assertEquals('EUR', $currency->value);
    }

    public function test_can_compare_currencies(): void
    {
        $php1 = Currency::PHP;
        $php2 = Currency::PHP;
        $usd = Currency::USD;

        $this->assertTrue($php1 === $php2);
        $this->assertFalse($php1 === $usd);
    }
}
