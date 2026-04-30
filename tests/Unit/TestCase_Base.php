<?php
/**
 * Base test case for all SlyMetrics unit tests.
 *
 * Sets up Brain\Monkey before every test and tears it down afterwards.
 *
 * @package SlyMetrics\Tests
 */

declare(strict_types=1);

namespace SlyMetrics\Tests\Unit;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

abstract class TestCase_Base extends TestCase {

    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Invoke a private or protected static method via reflection.
     *
     * @param string $method_name
     * @param array<mixed> $args
     * @return mixed
     */
    protected static function call_private_method( string $method_name, array $args = [] ): mixed {
        $method = new ReflectionMethod( \SlyMetrics_Plugin::class, $method_name );
        // setAccessible() has no effect since PHP 8.1; removed to suppress deprecation
        return $method->invoke( null, ...$args );
    }
}
