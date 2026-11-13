<?php
/**
 * Tests for the static-tier cache key versioning in SlyMetrics\Metrics\Cache (issue #10).
 *
 * The static tier key embeds the WP and PHP versions, so a core update
 * invalidates the cache without waiting for the 60-minute TTL.
 *
 * @package SlyMetrics\Tests\Unit\Metrics
 */

declare(strict_types=1);

namespace SlyMetrics\Tests\Unit\Metrics;

use SlyMetrics\Metrics\Cache;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\Metrics\Cache
 */
class CacheStaticKeyTest extends TestCase_Base {

    /**
     * Invoke the private static key() method via reflection.
     *
     * @return string
     */
    private static function static_key(): string {
        $method = new \ReflectionMethod( Cache::class, 'static_key' );
        return $method->invoke( null );
    }

    public function test_static_key_contains_wp_version(): void {
        $this->assertStringContainsString( 'wp6.5', self::static_key() );
    }

    public function test_static_key_contains_php_version(): void {
        $this->assertStringContainsString(
            'php' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION,
            self::static_key()
        );
    }

    public function test_static_key_is_stable_within_one_process(): void {
        $this->assertSame( self::static_key(), self::static_key() );
    }

    public function test_build_uses_versioned_static_key(): void {
        // Prime everything except the static tier so build() takes the
        // cache-miss path for the static tier only.
        $GLOBALS['slymetrics_test_transients']['slymetrics_cache'] = false;
        $GLOBALS['slymetrics_test_transients']['slymetrics_cache_fast'] = 'FAST';
        $GLOBALS['slymetrics_test_transients']['slymetrics_heavy_cache'] = 'HEAVY';
        $GLOBALS['slymetrics_test_transients']['slymetrics_checksum_cache'] = 'CHECKSUM';
        $GLOBALS['slymetrics_test_transients']['slymetrics_static_cache'] = 'STALE_STATIC';

        Cache::get();

        // The stale unversioned entry must not be returned; the versioned
        // key must hold the freshly built static metrics.
        $this->assertStringNotContainsString( 'STALE_STATIC', $GLOBALS['slymetrics_test_transients']['slymetrics_cache'] );
        $this->assertArrayHasKey( self::static_key(), $GLOBALS['slymetrics_test_transients'] );
        $this->assertStringContainsString( 'wordpress_version', $GLOBALS['slymetrics_test_transients'][self::static_key()] );
    }

    public function test_flush_deletes_versioned_and_legacy_keys(): void {
        $GLOBALS['slymetrics_test_transients']['slymetrics_cache'] = 'A';
        $GLOBALS['slymetrics_test_transients']['slymetrics_cache_fast'] = 'B';
        $GLOBALS['slymetrics_test_transients']['slymetrics_heavy_cache'] = 'C';
        $GLOBALS['slymetrics_test_transients']['slymetrics_static_cache'] = 'D';
        $GLOBALS['slymetrics_test_transients']['slymetrics_checksum_cache'] = 'F';
        $GLOBALS['slymetrics_test_transients'][self::static_key()] = 'E';

        Cache::flush();

        $this->assertArrayNotHasKey( 'slymetrics_cache', $GLOBALS['slymetrics_test_transients'] );
        $this->assertArrayNotHasKey( 'slymetrics_cache_fast', $GLOBALS['slymetrics_test_transients'] );
        $this->assertArrayNotHasKey( 'slymetrics_heavy_cache', $GLOBALS['slymetrics_test_transients'] );
        $this->assertArrayNotHasKey( 'slymetrics_static_cache', $GLOBALS['slymetrics_test_transients'] );
        $this->assertArrayNotHasKey( self::static_key(), $GLOBALS['slymetrics_test_transients'] );
    }
}
