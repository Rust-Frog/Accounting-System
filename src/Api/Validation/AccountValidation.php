<?php

declare(strict_types=1);

namespace Api\Validation;

use Domain\Shared\Validation\RequestValidator;
use Domain\Shared\Validation\ValidationResult;

/**
 * Validation rules for Account-related requests.
 */
final class AccountValidation
{
    public function __construct(
        private readonly RequestValidator $validator
    ) {
    }

    /**
     * Validate account creation request.
     */
    public function validateCreate(array $data): ValidationResult
    {
        return $this->validator->validate($data, [
            'code' => ['required', 'numeric', 'min:1000', 'max:99999'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['string', 'max:1000'],
            'parent_id' => ['uuid'],
        ]);
    }

    /**
     * Validate account update request.
     */
    public function validateUpdate(array $data): ValidationResult
    {
        return $this->validator->validate($data, [
            'name' => ['string', 'min:2', 'max:255'],
            'description' => ['string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);
    }
}
