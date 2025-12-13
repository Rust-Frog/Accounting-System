<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service;

use Domain\Transaction\Service\ValidationResult;
use PHPUnit\Framework\TestCase;

final class ValidationResultTest extends TestCase
{
    public function test_creates_valid_result(): void
    {
        $result = ValidationResult::valid();

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors());
    }

    public function test_creates_invalid_result_with_errors(): void
    {
        $errors = ['Error 1', 'Error 2'];
        $result = ValidationResult::invalid($errors);

        $this->assertFalse($result->isValid());
        $this->assertEquals($errors, $result->errors());
    }

    public function test_has_error_returns_true_when_error_exists(): void
    {
        $result = ValidationResult::invalid(['Transaction is not balanced']);

        $this->assertTrue($result->hasError('Transaction is not balanced'));
    }

    public function test_has_error_returns_false_when_error_not_exists(): void
    {
        $result = ValidationResult::invalid(['Error 1']);

        $this->assertFalse($result->hasError('Error 2'));
    }

    public function test_error_count_returns_correct_count(): void
    {
        $result = ValidationResult::invalid(['Error 1', 'Error 2', 'Error 3']);

        $this->assertEquals(3, $result->errorCount());
    }

    public function test_valid_result_has_zero_error_count(): void
    {
        $result = ValidationResult::valid();

        $this->assertEquals(0, $result->errorCount());
    }
}
