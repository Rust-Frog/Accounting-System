<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Exception;

use Domain\Shared\Exception\DomainException;
use Domain\Shared\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InvalidArgumentExceptionTest extends TestCase
{
    public function test_extends_domain_exception(): void
    {
        $exception = new InvalidArgumentException('Invalid argument');

        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertEquals('Invalid argument', $exception->getMessage());
    }

    public function test_can_be_thrown_and_caught(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Test invalid argument');

        throw new InvalidArgumentException('Test invalid argument');
    }
}
