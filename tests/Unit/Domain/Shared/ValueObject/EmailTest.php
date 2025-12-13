<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObject;

use Domain\Shared\ValueObject\Email;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function test_creates_email_from_valid_string(): void
    {
        $email = Email::fromString('john@example.com');
        $this->assertEquals('john@example.com', $email->toString());
    }

    public function test_rejects_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Email::fromString('invalid-email');
    }

    public function test_rejects_empty_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Email::fromString('');
    }

    public function test_normalizes_email_to_lowercase(): void
    {
        $email = Email::fromString('John.Doe@Example.COM');
        $this->assertEquals('john.doe@example.com', $email->toString());
    }

    public function test_equals_returns_true_for_same_email(): void
    {
        $email1 = Email::fromString('john@example.com');
        $email2 = Email::fromString('john@example.com');
        $this->assertTrue($email1->equals($email2));
    }

    public function test_equals_returns_false_for_different_email(): void
    {
        $email1 = Email::fromString('john@example.com');
        $email2 = Email::fromString('jane@example.com');
        $this->assertFalse($email1->equals($email2));
    }

    public function test_equals_handles_case_insensitive_comparison(): void
    {
        $email1 = Email::fromString('JOHN@example.com');
        $email2 = Email::fromString('john@example.com');
        $this->assertTrue($email1->equals($email2));
    }
}
