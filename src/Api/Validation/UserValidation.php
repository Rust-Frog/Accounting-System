<?php

declare(strict_types=1);

namespace Api\Validation;

use Domain\Shared\Validation\RequestValidator;
use Domain\Shared\Validation\ValidationResult;

/**
 * Validation rules for user-related API requests.
 */
final class UserValidation
{
    private RequestValidator $validator;

    public function __construct(?RequestValidator $validator = null)
    {
        $this->validator = $validator ?? new RequestValidator();
    }

    /**
     * Validate user registration request.
     */
    public function validateRegister(array $data): ValidationResult
    {
        return $this->validator->validate($data, [
            'username' => ['required', 'string', 'min:3', 'max:50'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role' => ['in:admin,user,accountant,auditor'],
        ]);
    }

    /**
     * Validate user login request.
     */
    public function validateLogin(array $data): ValidationResult
    {
        return $this->validator->validate($data, [
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'otp_code' => ['string', 'min:6', 'max:6'],
        ]);
    }

    /**
     * Validate user update request.
     */
    public function validateUpdate(array $data): ValidationResult
    {
        return $this->validator->validate($data, [
            'email' => ['email'],
            'role' => ['in:admin,user,accountant,auditor'],
        ]);
    }

    /**
     * Validate password change request.
     */
    public function validatePasswordChange(array $data): ValidationResult
    {
        return $this->validator->validate($data, [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'max:255'],
        ]);
    }
}
