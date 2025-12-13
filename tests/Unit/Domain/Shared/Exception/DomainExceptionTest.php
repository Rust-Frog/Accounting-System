<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Exception;

use Domain\Shared\Exception\DomainException;
use Exception;
use PHPUnit\Framework\TestCase;

final class DomainExceptionTest extends TestCase
{
    public function test_domain_exception_extends_exception(): void
    {
        $exception = new class('Test message') extends DomainException {};

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }
}
