<?php

declare(strict_types=1);

namespace Domain\Company\ValueObject;

use InvalidArgumentException;

final class Address
{
    private function __construct(
        private readonly string $street,
        private readonly string $city,
        private readonly ?string $state,
        private readonly ?string $postalCode,
        private readonly string $country
    ) {
    }

    public static function create(
        string $street,
        string $city,
        ?string $state,
        ?string $postalCode,
        string $country
    ): self {
        $street = trim($street);
        $city = trim($city);
        $state = $state !== null ? trim($state) : null;
        $postalCode = $postalCode !== null ? trim($postalCode) : null;
        $country = trim($country);

        if ($street === '') {
            throw new InvalidArgumentException('Street cannot be empty');
        }

        if ($city === '') {
            throw new InvalidArgumentException('City cannot be empty');
        }

        if ($country === '') {
            throw new InvalidArgumentException('Country cannot be empty');
        }

        return new self($street, $city, $state, $postalCode, $country);
    }

    public function street(): string
    {
        return $this->street;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function state(): ?string
    {
        return $this->state;
    }

    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    public function country(): string
    {
        return $this->country;
    }

    public function equals(self $other): bool
    {
        return $this->street === $other->street
            && $this->city === $other->city
            && $this->state === $other->state
            && $this->postalCode === $other->postalCode
            && $this->country === $other->country;
    }

    public function format(): string
    {
        $parts = [$this->street, $this->city];

        if ($this->state !== null) {
            $parts[] = $this->state;
        }

        if ($this->postalCode !== null) {
            $parts[] = $this->postalCode;
        }

        $parts[] = $this->country;

        return implode(', ', $parts);
    }
}
