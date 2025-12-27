<?php

declare(strict_types=1);

namespace Api\Validation;

use Domain\Shared\Validation\RequestValidator;
use Domain\Shared\Validation\ValidationResult;

/**
 * Validation rules for company-related API requests.
 */
final class CompanyValidation
{
    private RequestValidator $validator;

    public function __construct(?RequestValidator $validator = null)
    {
        $this->validator = $validator ?? new RequestValidator();
    }

    /**
     * Validate company creation request.
     */
    public function validateCreate(array $data): ValidationResult
    {
        return $this->validator->validate($data, [
            'company_name' => ['required', 'string', 'min:2', 'max:255'],
            'legal_name' => ['required', 'string', 'min:2', 'max:255'],
            'tax_id' => ['required', 'string', 'min:5', 'max:50'],
            'currency' => ['required', 'currency'],
            'address_street' => ['required', 'string', 'max:255'],
            'address_city' => ['required', 'string', 'max:100'],
            'address_country' => ['required', 'string', 'max:100'],
            'address_state' => ['string', 'max:100'],
            'address_postal_code' => ['string', 'max:20'],
        ]);
    }

    /**
     * Validate company update request.
     */
    public function validateUpdate(array $data): ValidationResult
    {
        return $this->validator->validate($data, [
            'company_name' => ['string', 'min:2', 'max:255'],
            'legal_name' => ['string', 'min:2', 'max:255'],
            'currency' => ['currency'],
            'address_street' => ['string', 'max:255'],
            'address_city' => ['string', 'max:100'],
            'address_country' => ['string', 'max:100'],
        ]);
    }
}
