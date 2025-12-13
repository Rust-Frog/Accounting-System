<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Company\Entity;

use Domain\Company\Entity\CompanySettings;
use Domain\Company\ValueObject\CompanyId;
use Domain\Shared\ValueObject\Currency;
use PHPUnit\Framework\TestCase;

final class CompanySettingsTest extends TestCase
{
    public function test_creates_settings_with_defaults(): void
    {
        $companyId = CompanyId::generate();
        $settings = CompanySettings::createDefault($companyId, Currency::PHP);

        $this->assertTrue($companyId->equals($settings->companyId()));
        $this->assertEquals(Currency::PHP, $settings->defaultCurrency());
        $this->assertEquals('Y-m-d', $settings->dateFormat());
        $this->assertEquals(2, $settings->decimalPlaces());
        $this->assertEquals('January', $settings->fiscalYearStart());
    }

    public function test_can_update_date_format(): void
    {
        $settings = $this->createSettings();

        $settings->updateDateFormat('d/m/Y');

        $this->assertEquals('d/m/Y', $settings->dateFormat());
    }

    public function test_can_update_decimal_places(): void
    {
        $settings = $this->createSettings();

        $settings->updateDecimalPlaces(4);

        $this->assertEquals(4, $settings->decimalPlaces());
    }

    public function test_decimal_places_must_be_between_0_and_6(): void
    {
        $settings = $this->createSettings();

        $this->expectException(\InvalidArgumentException::class);
        $settings->updateDecimalPlaces(7);
    }

    public function test_decimal_places_cannot_be_negative(): void
    {
        $settings = $this->createSettings();

        $this->expectException(\InvalidArgumentException::class);
        $settings->updateDecimalPlaces(-1);
    }

    public function test_can_update_fiscal_year_start(): void
    {
        $settings = $this->createSettings();

        $settings->updateFiscalYearStart('April');

        $this->assertEquals('April', $settings->fiscalYearStart());
    }

    public function test_fiscal_year_start_must_be_valid_month(): void
    {
        $settings = $this->createSettings();

        $this->expectException(\InvalidArgumentException::class);
        $settings->updateFiscalYearStart('InvalidMonth');
    }

    public function test_can_update_default_currency(): void
    {
        $settings = $this->createSettings();

        $settings->updateDefaultCurrency(Currency::USD);

        $this->assertEquals(Currency::USD, $settings->defaultCurrency());
    }

    public function test_can_enable_multi_currency(): void
    {
        $settings = $this->createSettings();

        $this->assertFalse($settings->isMultiCurrencyEnabled());

        $settings->enableMultiCurrency();

        $this->assertTrue($settings->isMultiCurrencyEnabled());
    }

    public function test_can_disable_multi_currency(): void
    {
        $settings = $this->createSettings();
        $settings->enableMultiCurrency();

        $settings->disableMultiCurrency();

        $this->assertFalse($settings->isMultiCurrencyEnabled());
    }

    private function createSettings(): CompanySettings
    {
        return CompanySettings::createDefault(CompanyId::generate(), Currency::PHP);
    }
}
