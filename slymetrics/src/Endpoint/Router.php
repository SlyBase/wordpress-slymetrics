<?php

namespace SlyMetrics\Endpoint;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Auth\Guard;
use SlyMetrics\Metrics\Cache;
use SlyMetrics\Util\IpDetector;
use SlyMetrics\Util\Logger;

/**
 * Handles all incoming metrics requests regardless of URL pattern:
 *   - ?slymetrics=1 / ?slybase_metrics=1 query parameters
 *   - /slymetrics, /slymetrics/metrics, /metrics path patterns
 *   - WordPress rewrite rule via slymetrics_endpoint query var
 *
 * @package SlyMetrics\Endpoint
 */
class Router {

    /** @var string[] Accepted clean-URL path values. */
    private static $metrics_paths = array( 'slymetrics/metrics', 'slymetrics', 'metrics' );

    // -----------------------------------------------------------------------
    // Hook callbacks
    // -----------------------------------------------------------------------

    /**
     * Early check on plugins_loaded (priority 1) to catch query-parameter
     * and direct-path requests before WordPress routing kicks in.
     */
    public static function early_metrics_check(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public metrics endpoint with custom auth
        if ( isset( $_GET['slymetrics'] ) || isset( $_GET['slybase_metrics'] ) ) {
            self::serve_metrics_response();
            return;
        }

        if ( in_array( self::get_request_path(), self::$metrics_paths, true ) ) {
            self::serve_metrics_response();
        }
    }

    /**
     * Unified handler on parse_request for rewrite-rule-based access.
     *
     * @param \WP $wp WordPress object.
     */
    public static function handle_metrics_request( \WP $wp ): void {
        $path = self::get_request_path();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public metrics endpoint with custom auth
        if ( isset( $_GET['slymetrics'] ) || isset( $_GET['slybase_metrics'] ) ) {
            self::serve_metrics_response();
            return;
        }

        if ( in_array( $path, array( 'slymetrics/metrics', 'slymetrics' ), true ) ) {
            if ( ! preg_match( '/^[a-zA-Z0-9\/_-]+$/', $path ) ) {
                Logger::error( 'Invalid characters in request path', array( 'path' => $path, 'ip' => IpDetector::get_client_ip() ) );
                return;
            }
            self::serve_metrics_response();
            return;
        }

        if ( get_query_var( 'slymetrics_endpoint' ) === 'metrics' ) {
            self::serve_metrics_response();
        }
    }

    /**
     * Register rewrite rules for clean-URL access.
     */
    public static function add_rewrite_rules(): void {
        add_rewrite_rule( '^slymetrics/metrics/?$', 'index.php?slymetrics_endpoint=metrics', 'top' );
        add_rewrite_rule( '^slymetrics/metrics$',   'index.php?slymetrics_endpoint=metrics', 'top' );
        add_rewrite_rule( '^slymetrics/?$',          'index.php?slymetrics_endpoint=metrics', 'top' );
        add_rewrite_rule( '^metrics/?$',             'index.php?slymetrics_endpoint=metrics', 'top' );
        add_rewrite_rule( '^metrics$',               'index.php?slymetrics_endpoint=metrics', 'top' );
    }

    /**
     * Add our custom query variables to WordPress.
     *
     * @param array $vars Existing query vars.
     * @return array
     */
    public static function add_query_vars( array $vars ): array {
        $vars[] = 'slymetrics_endpoint';
        $vars[] = 'slymetrics';
        return $vars;
    }

    /**
     * Flush rewrite rules if our rules are missing (admin-only check).
     */
    public static function maybe_flush_rewrite_rules(): void {
        if ( ! is_admin() ) {
            return;
        }

        $rules        = get_option( 'rewrite_rules' );
        $has_our_rule = false;

        if ( is_array( $rules ) ) {
            foreach ( $rules as $pattern => $rewrite ) {
                if ( ( strpos( $pattern, 'slymetrics/metrics' ) !== false || strpos( $pattern, '^metrics' ) !== false )
                     && strpos( $rewrite, 'slymetrics_endpoint=metrics' ) !== false ) {
                    $has_our_rule = true;
                    break;
                }
            }
        }

        if ( ! $has_our_rule ) {
            self::add_rewrite_rules();
            flush_rewrite_rules();
            update_option( 'slymetrics_rewrite_rules_flushed', time() );
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Extract and trim the path from the current REQUEST_URI.
     *
     * @return string Path without leading/trailing slashes.
     */
    private static function get_request_path(): string {
        $request_uri = isset( $_SERVER['REQUEST_URI'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
            : '';
        $parsed = wp_parse_url( $request_uri );
        return isset( $parsed['path'] ) ? trim( $parsed['path'], '/' ) : '';
    }

    /**
     * Authenticate, rate-limit, and serve the metrics response.
     * Calls exit() on completion.
     */
    private static function serve_metrics_response(): void {
        // Fix Host header for Prometheus ServiceMonitor compatibility
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( $site_host && isset( $_SERVER['HTTP_HOST'] ) && $_SERVER['HTTP_HOST'] !== $site_host ) {
            $_SERVER['HTTP_HOST'] = $site_host;
        }

        // Rate limiting: max 60 requests per minute per IP
        $client_ip        = IpDetector::get_client_ip();
        $rate_limit_key   = 'slymetrics_rate_limit_' . md5( $client_ip );
        $current_requests = (int) get_transient( $rate_limit_key );

        if ( $current_requests >= 60 ) {
            status_header( 429 );
            header( 'Content-Type: application/json; charset=' . ( get_option( 'blog_charset' ) ?: 'UTF-8' ) );
            header( 'Retry-After: 60' );
            header( 'X-RateLimit-Limit: 60' );
            header( 'X-RateLimit-Remaining: 0' );
            header( 'X-RateLimit-Reset: ' . ( time() + 60 ) );
            Logger::error( 'Rate limit exceeded', array( 'ip' => $client_ip, 'requests' => $current_requests ) );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo wp_json_encode( array( 'error' => 'Rate limit exceeded. Please try again later.' ) );
            exit;
        }

        set_transient( $rate_limit_key, $current_requests + 1, 60 );

        // Build a minimal REST request for auth checking
        $fake_request = new \WP_REST_Request( 'GET', '/slymetrics/v1/metrics' );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['api_key'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $fake_request->set_param( 'api_key', sanitize_text_field( wp_unslash( $_GET['api_key'] ) ) );
        }

        $auth_result = Guard::check( $fake_request );
        if ( is_wp_error( $auth_result ) ) {
            status_header( 401 );
            header( 'Content-Type: application/json; charset=' . ( get_option( 'blog_charset' ) ?: 'UTF-8' ) );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo wp_json_encode( array(
                'error'   => 'Authentication Required',
                'message' => $auth_result->get_error_message(),
            ) );
            exit;
        }

        $metrics = Cache::get();

        status_header( 200 );
        header( 'Content-Type: text/plain; charset=' . ( get_option( 'blog_charset' ) ?: 'UTF-8' ) );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: DENY' );
        header( 'X-XSS-Protection: 1; mode=block' );
        header( 'Referrer-Policy: no-referrer' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw Prometheus exposition format
        echo $metrics;
        exit;
    }
}
