<?php
/**
 * Tests for the checksum cache tier in SlyMetrics\Metrics\Cache (issue #9).
 *
 * @package SlyMetrics\Tests\Unit\Metrics
 */

declare( strict_types=1 );

namespace SlyMetrics\Tests\Unit\Metrics;

use Brain\Monkey;
use SlyMetrics\Metrics\Cache;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\Metrics\Cache
 */
class CacheTest extends TestCase_Base {

	private const CORE_URL = 'https://api.wordpress.org/core/checksums/1.0/?version=6.5&locale=en_US';

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * Stub every WordPress function the full metrics build touches.
	 */
	private function stub_full_build(): void {
		Monkey\Functions\expect( 'count_users' )->andReturn( array( 'total_users' => 0, 'avail_roles' => array() ) );
		Monkey\Functions\expect( 'wp_count_posts' )->andReturn( (object) array() );
		Monkey\Functions\expect( 'wp_count_comments' )->andReturn( (object) array() );
		Monkey\Functions\expect( 'wp_count_terms' )->andReturn( 0 );
		Monkey\Functions\expect( 'get_posts' )->andReturn( array() );
		Monkey\Functions\expect( 'wp_get_themes' )->andReturn( array() );
		Monkey\Functions\expect( 'wp_upload_dir' )->andReturn( array( 'basedir' => sys_get_temp_dir() ) );
		Monkey\Functions\expect( 'get_theme_root' )->andReturn( sys_get_temp_dir() );
		Monkey\Functions\expect( 'get_plugins' )->andReturn( array() );
		Monkey\Functions\expect( 'get_locale' )->andReturn( 'en_US' );
		// Minimal DB stub: HeavyMetrics/Checker read $wpdb directly.
		$GLOBALS['wpdb'] = new class() {
			public $options    = '';
			public $prefix     = 'wp_';
			public $last_error = '';
			public function prepare( $query, ...$args ) { return $query; }
			public function get_var( $query ) { return '0'; }
			public function get_results( $query ) { return array(); }
		};
		// Serve the core checksum reference from the transient cache: no HTTP.
		$GLOBALS['slymetrics_test_transients'][ 'slymetrics_cs_' . hash( 'sha256', self::CORE_URL ) ] = array( 'checksums' => array() );
		Monkey\Functions\expect( 'wp_remote_get' )->never();
	}

	public function test_get_includes_checksum_metrics(): void {
		$this->stub_full_build();

		$out = Cache::get();

		$this->assertStringContainsString( 'wordpress_checksum_valid{wordpress_site="Test Site",type="core",slug="wordpress",version="6.5",status="verified"} 1', $out );
		$this->assertStringContainsString( 'wordpress_users_total', $out );
	}

	public function test_checksum_tier_is_cached_and_rebuilt_after_expiry(): void {
		$this->stub_full_build();

		$first = Cache::get();
		$this->assertArrayHasKey( 'slymetrics_checksum_cache', $GLOBALS['slymetrics_test_transients'] );

		// A changed reference must NOT show up while the tier cache is warm
		// (expire only the 10 s top-level cache so build() runs again).
		$GLOBALS['slymetrics_test_transients'][ 'slymetrics_cs_' . hash( 'sha256', self::CORE_URL ) ] = array(
			'checksums' => array( 'wp-includes/evil.php' => 'deadbeef' ),
		);
		unset( $GLOBALS['slymetrics_test_transients']['slymetrics_cache'] );
		$this->assertSame( $first, Cache::get() );

		// After the tier expires the change is picked up (missing file => modified).
		unset( $GLOBALS['slymetrics_test_transients']['slymetrics_checksum_cache'] );
		unset( $GLOBALS['slymetrics_test_transients']['slymetrics_cache'] );
		$this->assertStringContainsString( 'status="modified"} 0', Cache::get() );
	}

	public function test_flush_removes_checksum_tier(): void {
		$GLOBALS['slymetrics_test_transients']['slymetrics_checksum_cache'] = 'cached';

		Cache::flush();

		$this->assertArrayNotHasKey( 'slymetrics_checksum_cache', $GLOBALS['slymetrics_test_transients'] );
	}

	public function test_flush_checksum_removes_only_checksum_tier(): void {
		$GLOBALS['slymetrics_test_transients']['slymetrics_checksum_cache'] = 'cached';
		$GLOBALS['slymetrics_test_transients']['slymetrics_cache_fast']     = 'fast';

		Cache::flush_checksum();

		$this->assertArrayNotHasKey( 'slymetrics_checksum_cache', $GLOBALS['slymetrics_test_transients'] );
		$this->assertArrayHasKey( 'slymetrics_cache_fast', $GLOBALS['slymetrics_test_transients'] );
	}
}