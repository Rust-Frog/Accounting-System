<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Exception;

use Domain\Shared\Exception\DomainException;
use Domain\Shared\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class ValidationExceptionTest extends TestCase
{
    public function test_extends_domain_exception(): void
    {
        $exception = new ValidationException(['field' => 'Error message']);

        $this->assertInstanceOf(DomainException::class, $exception);
    }

    public function test_stores_validation_errors(): void
    {
        $errors = [
            'email' => 'Email is required',
            'password' => 'Password must be at least 8 characters',
        ];

        $exception = new ValidationException($errors);

        $this->assertEquals($errors, $exception->getErrors());
    }

    public function test_has_default_message(): void
    {
        $exception = new ValidationException(['field' => 'Error']);

        $this->assertEquals('Validation failed', $exception->getMessage());
    }

    public function test_accepts_custom_message(): void
    {
        $exception = new ValidationException(['field' => 'Error'], 'Custom validation message');

        $this->assertEquals('Custom validation message', $exception->getMessage());
    }

    public function test_can_check_if_field_has_error(): void
    {
        $errors = [
            'email' => 'Email is required',
        ];

        $exception = new ValidationException($errors);

        $this->assertTrue($exception->hasError('email'));
        $this->assertFalse($exception->hasError('password'));
    }

    public function test_can_get_error_for_field(): void
    {
        $errors = [
            'email' => 'Email is required',
        ];

        $exception = new ValidationException($errors);

        $this->assertEquals('Email is required', $exception->getError('email'));
        $this->assertNull($exception->getError('password'));
    }
}
