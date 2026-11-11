<?php
/**
 * Tests for SlyMetrics\Metrics\Builder\ChecksumMetrics (issue #9).
 *
 * File hashing is tested against REAL files on disk (no md5 mocks).
 * HTTP is stubbed at the wp_remote_get boundary only.
 *
 * @package SlyMetrics\Tests\Unit\Metrics\Builder
 */

declare( strict_types=1 );

namespace SlyMetrics\Tests\Unit\Metrics\Builder;

use SlyMetrics\Metrics\Builder\ChecksumMetrics;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\Metrics\Builder\ChecksumMetrics
 */
class ChecksumMetricsTest extends TestCase_Base {

	/** @var string[] Files created during a test, removed in tearDown. */
	private array $created_files = array();

	/** @var string[] Directories created during a test, removed in tearDown. */
	private array $created_dirs = array();

	protected function tearDown(): void {
		foreach ( $this->created_files as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}
		foreach ( array_reverse( $this->created_dirs ) as $dir ) {
			if ( is_dir( $dir ) ) {
				@rmdir( $dir );
			}
		}
		$this->created_files = array();
		$this->created_dirs  = array();
		parent::tearDown();
	}

	private function make_file( string $path, string $content ): string {
		$dir     = dirname( $path );
		$missing = array();
		$cursor  = $dir;
		while ( ! is_dir( $cursor ) ) {
			$missing[] = $cursor;
			$cursor    = dirname( $cursor );
		}
		foreach ( array_reverse( $missing ) as $new_dir ) {
			mkdir( $new_dir );
			$this->created_dirs[] = $new_dir;
		}
		file_put_contents( $path, $content );
		$this->created_files[] = $path;
		return $path;
	}

	// -----------------------------------------------------------------------
	// Slice 1: verify_files()
	// -----------------------------------------------------------------------

	public function test_verify_files_counts_match_mismatch_and_missing(): void {
		$dir = sys_get_temp_dir() . '/slymetrics_cs_' . uniqid() . '/';
		$this->make_file( $dir . 'good.php', 'good content' );
		$this->make_file( $dir . 'bad.php', 'tampered content' );

		$counts = ChecksumMetrics::verify_files(
			array(
				'good.php' => md5( 'good content' ),
				'bad.php'  => md5( 'original content' ),
				'gone.php' => md5( 'does not matter' ),
			),
			$dir
		);

		$this->assertSame(
			array( 'match' => 1, 'mismatch' => 1, 'missing' => 1 ),
			$counts
		);
	}

	public function test_verify_files_accepts_any_of_multiple_official_hashes(): void {
		// Real-world: wordpress.org lists several acceptable hashes for some
		// plugin files (e.g. readme.txt variants). Seen on code-block-pro,
		// email-no-bot, jwt-authentication-for-wp-rest-api.
		$dir = sys_get_temp_dir() . '/slymetrics_cs_' . uniqid() . '/';
		$this->make_file( $dir . 'readme.txt', 'variant two' );

		$counts = ChecksumMetrics::verify_files(
			array(
				'readme.txt' => array( md5( 'variant one' ), md5( 'variant two' ) ),
			),
			$dir
		);

		$this->assertSame( array( 'match' => 1, 'mismatch' => 0, 'missing' => 0 ), $counts );
	}

	// -----------------------------------------------------------------------
	// Slice 2: core checksums end-to-end through build()
	// -----------------------------------------------------------------------

	private const CORE_URL_EN = 'https://api.wordpress.org/core/checksums/1.0/?version=6.5&locale=en_US';

	private function stub_http( array $url_map ): void {
		\Brain\Monkey\Functions\expect( 'wp_remote_get' )
			->andReturnUsing( function ( $url, $args = array() ) use ( $url_map ) {
				if ( ! isset( $url_map[ $url ] ) ) {
					throw new \RuntimeException( 'Unexpected HTTP request: ' . $url );
				}
				return $url_map[ $url ];
			} );
	}

	private function http_ok( string $body ): array {
		return array( 'response' => array( 'code' => 200 ), 'body' => $body );
	}

	private function stub_no_plugins(): void {
		\Brain\Monkey\Functions\expect( 'get_plugins' )->andReturn( array() );
		\Brain\Monkey\Functions\expect( 'get_locale' )->andReturn( 'en_US' );
	}

	public function test_core_verified_when_all_files_match(): void {
		$this->stub_no_plugins();
		$this->make_file( ABSPATH . 'wp-includes/cs-test.php', 'core content' );
		$this->stub_http( array(
			self::CORE_URL_EN => $this->http_ok( wp_json_encode( array(
				'checksums' => array( 'wp-includes/cs-test.php' => md5( 'core content' ) ),
			) ) ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString(
			'wordpress_checksum_valid{wordpress_site="WP SITE",type="core",slug="wordpress",version="6.5",status="verified"} 1',
			$out
		);
		$this->assertStringContainsString(
			'wordpress_checksum_files_total{wordpress_site="WP SITE",type="core",slug="wordpress",version="6.5",result="match"} 1',
			$out
		);
		$this->assertStringContainsString( '# TYPE wordpress_checksum_valid gauge', $out );
		$this->assertStringContainsString( '# TYPE wordpress_checksum_files_total gauge', $out );
	}

	public function test_core_modified_when_a_file_differs(): void {
		$this->stub_no_plugins();
		$this->make_file( ABSPATH . 'wp-includes/cs-test.php', 'TAMPERED' );
		$this->stub_http( array(
			self::CORE_URL_EN => $this->http_ok( wp_json_encode( array(
				'checksums' => array( 'wp-includes/cs-test.php' => md5( 'core content' ) ),
			) ) ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",status="modified"} 0', $out );
		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",result="mismatch"} 1', $out );
	}

	public function test_core_missing_files_are_reported_but_do_not_flip_valid(): void {
		// Deleted bundled themes/plugins are normal hosting practice, not
		// tampering (wp-cli treats missing as warning, mismatch as error).
		$this->stub_no_plugins();
		$this->stub_http( array(
			self::CORE_URL_EN => $this->http_ok( wp_json_encode( array(
				'checksums' => array( 'wp-includes/cs-deleted.php' => md5( 'gone' ) ),
			) ) ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",status="verified"} 1', $out );
		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",result="missing"} 1', $out );
	}

	public function test_core_unavailable_on_http_404(): void {
		$this->stub_no_plugins();
		$this->stub_http( array(
			self::CORE_URL_EN => array( 'response' => array( 'code' => 404 ), 'body' => 'Not found' ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",status="unavailable"} 0', $out );
		$this->assertStringNotContainsString( 'type="core",slug="wordpress",result=', $out );
	}

	public function test_core_unavailable_on_wp_error(): void {
		$this->stub_no_plugins();
		$this->stub_http( array(
			self::CORE_URL_EN => new \WP_Error( 'http_request_failed', 'Connection refused' ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",status="unavailable"} 0', $out );
	}

	public function test_core_unavailable_on_invalid_json(): void {
		$this->stub_no_plugins();
		$this->stub_http( array(
			self::CORE_URL_EN => $this->http_ok( 'this is not json' ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",status="unavailable"} 0', $out );
	}

	public function test_core_url_uses_site_locale(): void {
		\Brain\Monkey\Functions\expect( 'get_plugins' )->andReturn( array() );
		\Brain\Monkey\Functions\expect( 'get_locale' )->andReturn( 'de_DE' );
		$url = 'https://api.wordpress.org/core/checksums/1.0/?version=6.5&locale=de_DE';
		$this->stub_http( array(
			$url              => $this->http_ok( wp_json_encode( array( 'checksums' => array() ) ) ),
			self::CORE_URL_EN => $this->http_ok( wp_json_encode( array( 'checksums' => array() ) ) ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",status="verified"} 1', $out );
	}

	public function test_core_accepts_en_us_variant_of_a_localized_file(): void {
		// Real-world: the fleet runs en_US-based images with a de_DE UI locale.
		// wp-includes/version.php and wp-config-sample.php differ between the
		// official packages; both variants are official and must be accepted.
		\Brain\Monkey\Functions\expect( 'get_plugins' )->andReturn( array() );
		\Brain\Monkey\Functions\expect( 'get_locale' )->andReturn( 'de_DE' );
		$this->make_file( ABSPATH . 'wp-includes/version.php', 'en_US variant' );
		$this->stub_http( array(
			'https://api.wordpress.org/core/checksums/1.0/?version=6.5&locale=de_DE' => $this->http_ok( wp_json_encode( array(
				'checksums' => array( 'wp-includes/version.php' => md5( 'de_DE variant' ) ),
			) ) ),
			self::CORE_URL_EN => $this->http_ok( wp_json_encode( array(
				'checksums' => array( 'wp-includes/version.php' => md5( 'en_US variant' ) ),
			) ) ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",status="verified"} 1', $out );
		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",result="match"} 1', $out );
	}

	public function test_core_falls_back_to_en_us_when_locale_reference_fails(): void {
		\Brain\Monkey\Functions\expect( 'get_plugins' )->andReturn( array() );
		\Brain\Monkey\Functions\expect( 'get_locale' )->andReturn( 'de_DE' );
		$this->make_file( ABSPATH . 'wp-includes/cs-test.php', 'core content' );
		$this->stub_http( array(
			'https://api.wordpress.org/core/checksums/1.0/?version=6.5&locale=de_DE' => array( 'response' => array( 'code' => 404 ), 'body' => 'Not found' ),
			self::CORE_URL_EN => $this->http_ok( wp_json_encode( array(
				'checksums' => array( 'wp-includes/cs-test.php' => md5( 'core content' ) ),
			) ) ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",status="verified"} 1', $out );
	}

	public function test_core_unavailable_only_when_both_references_fail(): void {
		\Brain\Monkey\Functions\expect( 'get_plugins' )->andReturn( array() );
		\Brain\Monkey\Functions\expect( 'get_locale' )->andReturn( 'de_DE' );
		$this->stub_http( array(
			'https://api.wordpress.org/core/checksums/1.0/?version=6.5&locale=de_DE' => array( 'response' => array( 'code' => 404 ), 'body' => 'Not found' ),
			self::CORE_URL_EN => array( 'response' => array( 'code' => 404 ), 'body' => 'Not found' ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",status="unavailable"} 0', $out );
	}

	// -----------------------------------------------------------------------
	// Slice 3: plugin checksums
	// -----------------------------------------------------------------------

	private const AKISMET_URL = 'https://downloads.wordpress.org/plugin-checksums/akismet/5.3.json';

	private function stub_core_empty(): void {
		$this->stub_http( array(
			self::CORE_URL_EN => $this->http_ok( wp_json_encode( array( 'checksums' => array() ) ) ),
		) );
	}

	private function plugin_response( array $files ): array {
		return $this->http_ok( wp_json_encode( array(
			'plugin'  => 'akismet',
			'version' => '5.3',
			'files'   => $files,
		) ) );
	}

	public function test_plugin_verified_when_all_files_match(): void {
		\Brain\Monkey\Functions\expect( 'get_locale' )->andReturn( 'en_US' );
		\Brain\Monkey\Functions\expect( 'get_plugins' )->andReturn( array(
			'akismet/akismet.php' => array( 'Version' => '5.3' ),
		) );
		$this->make_file( WP_PLUGIN_DIR . '/akismet/akismet.php', 'plugin content' );
		$this->stub_http( array(
			self::CORE_URL_EN   => $this->http_ok( wp_json_encode( array( 'checksums' => array() ) ) ),
			self::AKISMET_URL   => $this->plugin_response( array(
				'akismet.php' => array( 'md5' => md5( 'plugin content' ), 'sha256' => hash( 'sha256', 'plugin content' ) ),
			) ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString(
			'wordpress_checksum_valid{wordpress_site="WP SITE",type="plugin",slug="akismet",version="5.3",status="verified"} 1',
			$out
		);
		$this->assertStringContainsString(
			'wordpress_checksum_files_total{wordpress_site="WP SITE",type="plugin",slug="akismet",version="5.3",result="match"} 1',
			$out
		);
	}

	public function test_plugin_modified_when_a_file_differs(): void {
		\Brain\Monkey\Functions\expect( 'get_locale' )->andReturn( 'en_US' );
		\Brain\Monkey\Functions\expect( 'get_plugins' )->andReturn( array(
			'akismet/akismet.php' => array( 'Version' => '5.3' ),
		) );
		$this->make_file( WP_PLUGIN_DIR . '/akismet/akismet.php', 'TAMPERED' );
		$this->stub_http( array(
			self::CORE_URL_EN => $this->http_ok( wp_json_encode( array( 'checksums' => array() ) ) ),
			self::AKISMET_URL => $this->plugin_response( array(
				'akismet.php' => array( 'md5' => md5( 'plugin content' ), 'sha256' => hash( 'sha256', 'plugin content' ) ),
			) ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="plugin",slug="akismet",version="5.3",status="modified"} 0', $out );
		$this->assertStringContainsString( 'type="plugin",slug="akismet",version="5.3",result="mismatch"} 1', $out );
	}

	public function test_plugin_unavailable_on_404(): void {
		\Brain\Monkey\Functions\expect( 'get_locale' )->andReturn( 'en_US' );
		\Brain\Monkey\Functions\expect( 'get_plugins' )->andReturn( array(
			'custom-plugin/custom.php' => array( 'Version' => '2.0' ),
		) );
		$this->stub_http( array(
			self::CORE_URL_EN => $this->http_ok( wp_json_encode( array( 'checksums' => array() ) ) ),
			'https://downloads.wordpress.org/plugin-checksums/custom-plugin/2.0.json' => array( 'response' => array( 'code' => 404 ), 'body' => 'Not found' ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="plugin",slug="custom-plugin",version="2.0",status="unavailable"} 0', $out );
		$this->assertStringNotContainsString( 'type="plugin",slug="custom-plugin",version="2.0",result=', $out );
	}

	public function test_single_file_plugin_uses_filename_slug_and_plugin_root(): void {
		\Brain\Monkey\Functions\expect( 'get_locale' )->andReturn( 'en_US' );
		\Brain\Monkey\Functions\expect( 'get_plugins' )->andReturn( array(
			'hello.php' => array( 'Version' => '1.7.2' ),
		) );
		$this->make_file( WP_PLUGIN_DIR . '/hello.php', 'hello content' );
		$this->stub_http( array(
			self::CORE_URL_EN => $this->http_ok( wp_json_encode( array( 'checksums' => array() ) ) ),
			'https://downloads.wordpress.org/plugin-checksums/hello/1.7.2.json' => $this->http_ok( wp_json_encode( array(
				'files' => array( 'hello.php' => array( 'md5' => md5( 'hello content' ) ) ),
			) ) ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="plugin",slug="hello",version="1.7.2",status="verified"} 1', $out );
	}

	public function test_plugin_without_version_is_unavailable_without_http_call(): void {
		\Brain\Monkey\Functions\expect( 'get_locale' )->andReturn( 'en_US' );
		\Brain\Monkey\Functions\expect( 'get_plugins' )->andReturn( array(
			'no-version/no-version.php' => array( 'Name' => 'No Version' ),
		) );
		// Only the core request may happen; the plugin must not trigger HTTP.
		$this->stub_http( array(
			self::CORE_URL_EN => $this->http_ok( wp_json_encode( array( 'checksums' => array() ) ) ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="plugin",slug="no-version",version="",status="unavailable"} 0', $out );
	}

	public function test_plugin_accepts_any_of_multiple_official_hashes(): void {
		\Brain\Monkey\Functions\expect( 'get_locale' )->andReturn( 'en_US' );
		\Brain\Monkey\Functions\expect( 'get_plugins' )->andReturn( array(
			'akismet/akismet.php' => array( 'Version' => '5.3' ),
		) );
		$this->make_file( WP_PLUGIN_DIR . '/akismet/readme.txt', 'variant two' );
		$this->stub_http( array(
			self::CORE_URL_EN => $this->http_ok( wp_json_encode( array( 'checksums' => array() ) ) ),
			self::AKISMET_URL => $this->plugin_response( array(
				'readme.txt' => array(
					'md5'    => array( md5( 'variant one' ), md5( 'variant two' ) ),
					'sha256' => array( hash( 'sha256', 'variant one' ), hash( 'sha256', 'variant two' ) ),
				),
			) ),
		) );

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="plugin",slug="akismet",version="5.3",status="verified"} 1', $out );
	}

	// -----------------------------------------------------------------------
	// Slice 4: reference cache + time budget (lesson from #12)
	// -----------------------------------------------------------------------

	private function reference_key( string $url ): string {
		return 'slymetrics_cs_' . hash( 'sha256', $url );
	}

	public function test_core_reference_cache_hit_skips_http(): void {
		$this->stub_no_plugins();
		$this->make_file( ABSPATH . 'wp-includes/cs-test.php', 'core content' );
		$GLOBALS['slymetrics_test_transients'][ $this->reference_key( self::CORE_URL_EN ) ] = array(
			'checksums' => array( 'wp-includes/cs-test.php' => md5( 'core content' ) ),
		);
		\Brain\Monkey\Functions\expect( 'wp_remote_get' )->never();

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",status="verified"} 1', $out );
		$this->assertStringContainsString( 'type="core",slug="wordpress",version="6.5",result="match"} 1', $out );
	}

	public function test_plugin_reference_cache_hit_skips_http(): void {
		\Brain\Monkey\Functions\expect( 'get_locale' )->andReturn( 'en_US' );
		\Brain\Monkey\Functions\expect( 'get_plugins' )->andReturn( array(
			'akismet/akismet.php' => array( 'Version' => '5.3' ),
		) );
		$this->make_file( WP_PLUGIN_DIR . '/akismet/akismet.php', 'plugin content' );
		$GLOBALS['slymetrics_test_transients'][ $this->reference_key( self::CORE_URL_EN ) ]   = array( 'checksums' => array() );
		$GLOBALS['slymetrics_test_transients'][ $this->reference_key( self::AKISMET_URL ) ] = array(
			'files' => array( 'akismet.php' => array( 'md5' => md5( 'plugin content' ) ) ),
		);
		\Brain\Monkey\Functions\expect( 'wp_remote_get' )->never();

		$out = ChecksumMetrics::build( 'WP SITE' );

		$this->assertStringContainsString( 'type="plugin",slug="akismet",version="5.3",status="verified"} 1', $out );
	}

	public function test_fetch_json_caches_successful_response(): void {
		$body = wp_json_encode( array( 'checksums' => array( 'a.php' => 'abc' ) ) );
		$this->stub_http( array( self::CORE_URL_EN => $this->http_ok( $body ) ) );

		$data = ChecksumMetrics::fetch_json( self::CORE_URL_EN, microtime( true ) + 30 );

		$this->assertSame( array( 'checksums' => array( 'a.php' => 'abc' ) ), $data );
		$this->assertSame( $data, $GLOBALS['slymetrics_test_transients'][ $this->reference_key( self::CORE_URL_EN ) ] );
	}

	public function test_fetch_json_returns_null_after_deadline_without_http(): void {
		\Brain\Monkey\Functions\expect( 'wp_remote_get' )->never();

		$this->assertNull( ChecksumMetrics::fetch_json( self::CORE_URL_EN, microtime( true ) - 1 ) );
	}

	public function test_fetch_json_serves_cached_reference_even_after_deadline(): void {
		\Brain\Monkey\Functions\expect( 'wp_remote_get' )->never();
		$GLOBALS['slymetrics_test_transients'][ $this->reference_key( self::CORE_URL_EN ) ] = array( 'checksums' => array() );

		$this->assertSame(
			array( 'checksums' => array() ),
			ChecksumMetrics::fetch_json( self::CORE_URL_EN, microtime( true ) - 1 )
		);
	}
}