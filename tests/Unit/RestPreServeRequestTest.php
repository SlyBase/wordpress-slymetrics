<?php
/**
 * Tests for SlyMetrics_Plugin::rest_pre_serve_request()
 *
 * @package SlyMetrics\Tests\Unit
 */

declare(strict_types=1);

namespace SlyMetrics\Tests\Unit;

/**
 * @covers \SlyMetrics_Plugin
 */
class RestPreServeRequestTest extends TestCase_Base {

    protected function setUp(): void {
        parent::setUp();
        // get_option is pre-stubbed in bootstrap.php (returns '' for blog_charset)
    }

    private function make_request( string $route ): \WP_REST_Request {
        return new \WP_REST_Request( 'GET', $route );
    }

    // ------------------------------------------------------------------
    // Route guard: other routes must not be touched
    // ------------------------------------------------------------------

    public function test_non_metrics_route_is_not_handled(): void {
        $request = $this->make_request( '/wp/v2/posts' );
        $result  = \WP_REST_Response::class; // just a stand-in object

        $served = \SlyMetrics_Plugin::rest_pre_serve_request(
            false,
            new \WP_REST_Response( 'data' ),
            $request,
            new \WP_REST_Server()
        );

        // Must return the original $served value unchanged
        $this->assertFalse( $served );
    }

    // ------------------------------------------------------------------
    // Metrics route: plain-text body is echoed and true is returned
    // ------------------------------------------------------------------

    public function test_metrics_route_with_rest_response_echoes_payload(): void {
        // headers_sent() is a native PHP function; in PHPUnit CLI it returns false,
        // so header() will be called – that's safe in CLI context.

        $request  = $this->make_request( '/slymetrics/v1/metrics' );
        $response = new \WP_REST_Response( "# HELP foo bar\nfoo{site=\"x\"} 1\n", 200 );

        ob_start();
        $served = \SlyMetrics_Plugin::rest_pre_serve_request(
            false,
            $response,
            $request,
            new \WP_REST_Server()
        );
        $output = ob_get_clean();

        $this->assertTrue( $served );
        $this->assertStringContainsString( '# HELP foo bar', $output );
        $this->assertStringContainsString( 'foo{site="x"} 1', $output );
    }

    public function test_metrics_route_with_string_payload_echoes_it(): void {

        $request = $this->make_request( '/slymetrics/v1/metrics' );
        $payload = "metric_name{site=\"s\"} 99\n";

        ob_start();
        $served = \SlyMetrics_Plugin::rest_pre_serve_request(
            false,
            $payload,
            $request,
            new \WP_REST_Server()
        );
        $output = ob_get_clean();

        $this->assertTrue( $served );
        $this->assertSame( $payload, $output );
    }

    public function test_wp_error_payload_falls_through_without_output(): void {
        $request = $this->make_request( '/slymetrics/v1/metrics' );
        $error   = new \WP_Error( 'rest_forbidden', 'Not allowed' );

        ob_start();
        $served = \SlyMetrics_Plugin::rest_pre_serve_request(
            false,
            $error,
            $request,
            new \WP_REST_Server()
        );
        $output = ob_get_clean();

        // WP_Error path must return $served unchanged and produce no output
        $this->assertFalse( $served );
        $this->assertSame( '', $output );
    }

    // ------------------------------------------------------------------
    // Array payload: should be JSON-encoded and echoed
    // ------------------------------------------------------------------

    public function test_array_payload_in_rest_response_is_json_encoded(): void {
        // wp_json_encode is pre-stubbed as json_encode alias in bootstrap.php

        $request  = $this->make_request( '/slymetrics/v1/metrics' );
        $response = new \WP_REST_Response( ['key' => 'val'], 200 );

        ob_start();
        \SlyMetrics_Plugin::rest_pre_serve_request(
            false,
            $response,
            $request,
            new \WP_REST_Server()
        );
        $output = ob_get_clean();

        $this->assertStringContainsString( '"key"', $output );
        $this->assertStringContainsString( '"val"', $output );
    }
}
