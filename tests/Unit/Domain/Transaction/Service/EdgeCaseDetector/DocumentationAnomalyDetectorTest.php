<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\Service\EdgeCaseDetector;

use Domain\Transaction\Service\EdgeCaseDetector\DocumentationAnomalyDetector;
use PHPUnit\Framework\TestCase;

final class DocumentationAnomalyDetectorTest extends TestCase
{
    private DocumentationAnomalyDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new DocumentationAnomalyDetector();
    }

    public function test_detects_empty_description(): void
    {
        $result = $this->detector->detect('');

        $this->assertTrue($result->hasFlags());
        $this->assertSame('missing_description', $result->flags()[0]->type());
    }

    public function test_detects_whitespace_only_description(): void
    {
        $result = $this->detector->detect('   ');

        $this->assertTrue($result->hasFlags());
        $this->assertSame('missing_description', $result->flags()[0]->type());
    }

    public function test_detects_minimal_description(): void
    {
        $result = $this->detector->detect('test');

        $this->assertTrue($result->hasFlags());
        $this->assertSame('missing_description', $result->flags()[0]->type());
    }

    public function test_allows_adequate_description(): void
    {
        $result = $this->detector->detect('Payment for office supplies from Staples');

        $this->assertFalse($result->hasFlags());
    }

    public function test_allows_exactly_minimum_length(): void
    {
        $result = $this->detector->detect('12345'); // 5 chars

        $this->assertFalse($result->hasFlags());
    }
}
