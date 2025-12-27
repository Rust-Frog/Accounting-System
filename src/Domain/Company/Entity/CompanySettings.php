<?php

declare(strict_types=1);

namespace Domain\Company\Entity;

use Domain\Company\ValueObject\CompanyId;
use Domain\Shared\ValueObject\Currency;
use InvalidArgumentException;

final class CompanySettings
{
    private const VALID_MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];

    private function __construct(
        private readonly CompanyId $companyId,
        private Currency $defaultCurrency,
        private string $dateFormat,
        private int $decimalPlaces,
        private string $fiscalYearStart,
        private bool $multiCurrencyEnabled
    ) {
    }

    public static function createDefault(CompanyId $companyId, Currency $defaultCurrency): self
    {
        return new self(
            companyId: $companyId,
            defaultCurrency: $defaultCurrency,
            dateFormat: 'Y-m-d',
            decimalPlaces: 2,
            fiscalYearStart: 'January',
            multiCurrencyEnabled: false
        );
    }

    public function updateDateFormat(string $dateFormat): void
    {
        $this->dateFormat = $dateFormat;
    }

    public function updateDecimalPlaces(int $decimalPlaces): void
    {
        if ($decimalPlaces < 0 || $decimalPlaces > 6) {
            throw new InvalidArgumentException('Decimal places must be between 0 and 6');
        }

        $this->decimalPlaces = $decimalPlaces;
    }

    public function updateFiscalYearStart(string $month): void
    {
        if (!in_array($month, self::VALID_MONTHS, true)) {
            throw new InvalidArgumentException('Invalid month: ' . $month);
        }

        $this->fiscalYearStart = $month;
    }

    public function updateDefaultCurrency(Currency $currency): void
    {
        $this->defaultCurrency = $currency;
    }

    public function enableMultiCurrency(): void
    {
        $this->multiCurrencyEnabled = true;
    }

    public function disableMultiCurrency(): void
    {
        $this->multiCurrencyEnabled = false;
    }

    // Getters
    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function defaultCurrency(): Currency
    {
        return $this->defaultCurrency;
    }

    public function dateFormat(): string
    {
        return $this->dateFormat;
    }

    public function decimalPlaces(): int
    {
        return $this->decimalPlaces;
    }

    public function fiscalYearStart(): string
    {
        return $this->fiscalYearStart;
    }

    public function isMultiCurrencyEnabled(): bool
    {
        return $this->multiCurrencyEnabled;
    }
}
