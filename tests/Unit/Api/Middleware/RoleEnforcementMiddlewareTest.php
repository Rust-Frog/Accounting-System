<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Middleware;

use Api\Middleware\RoleEnforcementMiddleware;
use PHPUnit\Framework\TestCase;

class RoleEnforcementMiddlewareTest extends TestCase
{
    /**
     * Test that special regex characters in route patterns are properly escaped.
     *
     * The '.' character is a regex metacharacter meaning "any character".
     * Without proper escaping, a pattern like 'GET /api/v1.0/*' would incorrectly
     * match 'GET /api/v1X0/test' because '.' matches 'X'.
     */
    public function testMatchesPatternEscapesSpecialCharacters(): void
    {
        $middleware = new RoleEnforcementMiddleware();

        // Use reflection to test the private matchesPattern method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('matchesPattern');
        $method->setAccessible(true);

        // Should match when dots are literal (exact match)
        $this->assertTrue(
            $method->invoke($middleware, 'GET /api/v1.0/test', 'GET /api/v1.0/*'),
            'Pattern with literal dot should match route with literal dot'
        );

        // Should NOT match when dot would need to match a different character
        // If regex escaping is broken, '.' would match 'X' and this would incorrectly return true
        $this->assertFalse(
            $method->invoke($middleware, 'GET /api/v1X0/test', 'GET /api/v1.0/*'),
            'Pattern with literal dot should NOT match route where dot position has different character'
        );

        // Additional test: verify other regex special chars are escaped
        // The pattern contains '+' which is a regex quantifier
        $this->assertTrue(
            $method->invoke($middleware, 'GET /api/test+path/resource', 'GET /api/test+path/*'),
            'Pattern with literal plus should match route with literal plus'
        );

        $this->assertFalse(
            $method->invoke($middleware, 'GET /api/testXpath/resource', 'GET /api/test+path/*'),
            'Pattern with literal plus should NOT match route without the plus'
        );
    }

    /**
     * Test that wildcard (*) still works correctly after escaping.
     */
    public function testMatchesPatternWildcardStillWorks(): void
    {
        $middleware = new RoleEnforcementMiddleware();

        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('matchesPattern');
        $method->setAccessible(true);

        // Basic wildcard matching should still work
        $this->assertTrue(
            $method->invoke($middleware, 'GET /api/v1/users/123', 'GET /api/v1/users/*'),
            'Wildcard should match any single path segment'
        );

        $this->assertTrue(
            $method->invoke($middleware, 'DELETE /api/v1/companies/abc-123/accounts/xyz-789', 'DELETE /api/v1/companies/*/accounts/*'),
            'Multiple wildcards should match multiple path segments'
        );

        // Wildcard should not match across path segments
        $this->assertFalse(
            $method->invoke($middleware, 'GET /api/v1/users/123/extra', 'GET /api/v1/users/*'),
            'Wildcard should not match across slashes'
        );
    }

    /**
     * Test exact pattern matching without wildcards.
     */
    public function testMatchesPatternExactMatch(): void
    {
        $middleware = new RoleEnforcementMiddleware();

        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('matchesPattern');
        $method->setAccessible(true);

        $this->assertTrue(
            $method->invoke($middleware, 'GET /api/v1/companies', 'GET /api/v1/companies'),
            'Exact pattern should match exact route'
        );

        $this->assertFalse(
            $method->invoke($middleware, 'POST /api/v1/companies', 'GET /api/v1/companies'),
            'Different HTTP method should not match'
        );

        $this->assertFalse(
            $method->invoke($middleware, 'GET /api/v1/companies/extra', 'GET /api/v1/companies'),
            'Longer path should not match exact pattern'
        );
    }
}
