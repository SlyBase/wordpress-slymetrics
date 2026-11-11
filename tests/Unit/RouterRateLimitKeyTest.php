<?php
/**
 * Tests for SlyMetrics\Endpoint\Router::rate_limit_key()
 *
 * The rate-limit transient key must not use a weak hash (Snyk php/InsecureHash
 * flagged the previous md5() usage) and must stay within WordPress' transient
 * name length limit.
 *
 * @package SlyMetrics\Tests\Unit
 */

declare(strict_types=1);

namespace SlyMetrics\Tests\Unit;

use SlyMetrics\Endpoint\Router;

/**
 * @covers \SlyMetrics\Endpoint\Router
 */
class RouterRateLimitKeyTest extends TestCase_Base {

	private const PREFIX = 'slymetrics_rate_limit_';

	public function test_key_starts_with_rate_limit_prefix(): void {
		$this->assertStringStartsWith( self::PREFIX, Router::rate_limit_key( '192.0.2.1' ) );
	}

	public function test_key_suffix_is_sha256_hex_not_md5(): void {
		$key    = Router::rate_limit_key( '192.0.2.1' );
		$suffix = substr( $key, strlen( self::PREFIX ) );
		// SHA-256 produces 64 hex chars; MD5 would produce 32 (weak hash).
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $suffix );
	}

	public function test_key_matches_sha256_known_vector(): void {
		// Known-answer test: pins the algorithm to SHA-256 exactly.
		$this->assertSame(
			self::PREFIX . '37fcff24bf62035b2b08020afc08b4fecd4fcffce57ab23518e3561ff0fe76b9',
			Router::rate_limit_key( '192.0.2.1' )
		);
	}

	public function test_key_is_deterministic_for_same_ip(): void {
		$this->assertSame(
			Router::rate_limit_key( '192.0.2.1' ),
			Router::rate_limit_key( '192.0.2.1' )
		);
	}

	public function test_key_differs_between_ips(): void {
		$this->assertNotSame(
			Router::rate_limit_key( '192.0.2.1' ),
			Router::rate_limit_key( '192.0.2.2' )
		);
	}

	public function test_key_fits_wordpress_transient_name_limit(): void {
		// option_name is varchar(191); the '_transient_timeout_' prefix takes 19 chars.
		// Longest realistic input: an IPv6 address.
		$this->assertLessThanOrEqual( 172, strlen( Router::rate_limit_key( '2001:db8:85a3::8a2e:370:7334' ) ) );
	}
}
