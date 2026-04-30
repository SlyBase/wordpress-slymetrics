<?php
/**
 * Tests for SlyMetrics_Plugin::check_auth()
 *
 * Uses environment variables for token injection, since WordPress option
 * functions are pre-stubbed in bootstrap.php and cannot be intercepted
 * by Patchwork at test time.
 *
 * @package SlyMetrics\Tests\Unit
 */

declare(strict_types=1);

namespace SlyMetrics\Tests\Unit;

/**
 * @covers \SlyMetrics_Plugin
 */
class CheckAuthTest extends TestCase_Base {

    private const TOKEN   = 'test-bearer-token-abc123';
    private const API_KEY = 'test-api-key-xyz789';

    /** Raw 32-byte encryption key used in this test suite. */
    private const ENC_KEY_RAW = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk'; // 32 bytes

    protected function setUp(): void {
        parent::setUp();
        // Set environment key so the plugin uses env-variable based auth
        putenv( 'SLYMETRICS_ENCRYPTION_KEY=' . base64_encode( self::ENC_KEY_RAW ) );
    }

    protected function tearDown(): void {
        putenv( 'SLYMETRICS_ENCRYPTION_KEY' );
        putenv( 'SLYMETRICS_BEARER_TOKEN' );
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Bearer Token via environment variable (Option 1 + env mode)
    // ------------------------------------------------------------------

    public function test_valid_env_bearer_token_grants_access(): void {
        putenv( 'SLYMETRICS_BEARER_TOKEN=' . self::TOKEN );

        $request = new \WP_REST_Request( 'GET', '/slymetrics/v1/metrics' );
        $request->set_header( 'authorization', 'Bearer ' . self::TOKEN );

        $result = \SlyMetrics_Plugin::check_auth( $request );
        $this->assertTrue( $result );
    }

    public function test_wrong_bearer_token_is_rejected(): void {
        putenv( 'SLYMETRICS_BEARER_TOKEN=' . self::TOKEN );

        $request = new \WP_REST_Request( 'GET', '/slymetrics/v1/metrics' );
        $request->set_header( 'authorization', 'Bearer wrong-token' );

        $result = \SlyMetrics_Plugin::check_auth( $request );
        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_bearer_token_comparison_is_case_sensitive(): void {
        putenv( 'SLYMETRICS_BEARER_TOKEN=' . self::TOKEN );

        $request = new \WP_REST_Request( 'GET', '/slymetrics/v1/metrics' );
        $request->set_header( 'authorization', 'Bearer ' . strtoupper( self::TOKEN ) );

        $result = \SlyMetrics_Plugin::check_auth( $request );
        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    // ------------------------------------------------------------------
    // API Key via database (with env key mode, DB API key stub returns '')
    // ------------------------------------------------------------------

    public function test_missing_api_key_in_db_is_rejected(): void {
        putenv( 'SLYMETRICS_BEARER_TOKEN' ); // unset

        $request = new \WP_REST_Request( 'GET', '/slymetrics/v1/metrics' );
        $request->set_param( 'api_key', self::API_KEY );

        $result = \SlyMetrics_Plugin::check_auth( $request );
        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    // ------------------------------------------------------------------
    // Unauthenticated request
    // ------------------------------------------------------------------

    public function test_unauthenticated_request_returns_wp_error(): void {
        putenv( 'SLYMETRICS_BEARER_TOKEN' ); // unset

        $request = new \WP_REST_Request( 'GET', '/slymetrics/v1/metrics' );
        $result  = \SlyMetrics_Plugin::check_auth( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rest_forbidden', $result->get_error_code() );
    }

    public function test_error_message_is_non_empty(): void {
        putenv( 'SLYMETRICS_BEARER_TOKEN' );

        $request = new \WP_REST_Request( 'GET', '/slymetrics/v1/metrics' );
        $result  = \SlyMetrics_Plugin::check_auth( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertNotEmpty( $result->get_error_message() );
    }

    // ------------------------------------------------------------------
    // Bearer Token from server superglobal fallback
    // ------------------------------------------------------------------

    public function test_auth_header_from_server_vars_is_read(): void {
        putenv( 'SLYMETRICS_BEARER_TOKEN=' . self::TOKEN );
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . self::TOKEN;

        $request = new \WP_REST_Request( 'GET', '/slymetrics/v1/metrics' );
        // No header on the request object → falls back to $_SERVER

        $result = \SlyMetrics_Plugin::check_auth( $request );
        $this->assertTrue( $result );

        unset( $_SERVER['HTTP_AUTHORIZATION'] );
    }
}
