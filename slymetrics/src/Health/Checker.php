<?php

namespace SlyMetrics\Health;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Util\SizeConverter;

/**
 * Runs lightweight WordPress site-health checks and exposes the results
 * as structured arrays for the metrics builder.
 *
 * @package SlyMetrics\Health
 */
class Checker {

    /**
     * Return aggregated counts by status and category.
     *
     * @return array<string, int>
     */
    public static function get_summary(): array {
        $details = self::get_details();

        $results = array(
            'good'         => 0,
            'recommended'  => 0,
            'critical'     => 0,
            'security'     => 0,
            'performance'  => 0,
            'total_failed' => 0,
        );

        foreach ( $details as $detail ) {
            switch ( $detail['status'] ) {
                case 'good':
                    $results['good']++;
                    break;
                case 'recommended':
                    $results['recommended']++;
                    break;
                case 'critical':
                    $results['critical']++;
                    break;
            }

            if ( $detail['category'] === 'security'
                && in_array( $detail['status'], array( 'recommended', 'critical' ), true ) ) {
                $results['security']++;
            } elseif ( $detail['category'] === 'performance'
                && in_array( $detail['status'], array( 'recommended', 'critical' ), true ) ) {
                $results['performance']++;
            }
        }

        $results['total_failed'] = $results['critical'] + $results['recommended'];

        return $results;
    }

    /**
     * Return individual check results.
     *
     * @return array<int, array{test: string, status: string, category: string, description: string}>
     */
    public static function get_details(): array {
        try {
            return self::run_checks();
        } catch ( \Exception $e ) {
            return array();
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Execute all checks and return their results.
     *
     * @return array<int, array{test: string, status: string, category: string, description: string}>
     */
    private static function run_checks(): array {
        $details = array();

        // --- File editing ---
        if ( ! defined( 'DISALLOW_FILE_EDIT' ) || ! DISALLOW_FILE_EDIT ) {
            $details[] = self::result( 'file_editing', 'recommended', 'security', 'File editing (DISALLOW_FILE_EDIT) should be disabled in production environments' );
        } else {
            $details[] = self::result( 'file_editing', 'good', 'security', 'File editing is properly disabled' );
        }

        // --- Debug mode ---
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $details[] = self::result( 'debug_mode', 'recommended', 'security', 'Debug mode (WP_DEBUG) should be disabled in production' );
        } else {
            $details[] = self::result( 'debug_mode', 'good', 'security', 'Debug mode is properly disabled' );
        }

        // --- Plugin updates ---
        $updates           = get_site_transient( 'update_plugins' );
        $updates_available = isset( $updates->response ) && is_array( $updates->response ) ? count( $updates->response ) : 0;

        if ( $updates_available > 0 ) {
            $details[] = self::result( 'plugin_updates', 'recommended', 'security', $updates_available . ' plugin updates available' );
        } else {
            $details[] = self::result( 'plugin_updates', 'good', 'security', 'All plugins are up to date' );
        }

        // --- PHP version ---
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            $details[] = self::result( 'php_version', 'critical', 'performance', 'PHP version ' . PHP_VERSION . ' is outdated and unsupported' );
        } elseif ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
            $details[] = self::result( 'php_version', 'recommended', 'performance', 'PHP version ' . PHP_VERSION . ' should be updated to 8.0+' );
        } else {
            $details[] = self::result( 'php_version', 'good', 'performance', 'PHP version ' . PHP_VERSION . ' is current' );
        }

        // --- Memory limit ---
        $memory_limit = ini_get( 'memory_limit' );
        $memory_bytes = SizeConverter::to_bytes( $memory_limit );

        if ( $memory_bytes < 128 * 1024 * 1024 ) {
            $details[] = self::result( 'php_memory_limit', 'critical', 'performance', 'Memory limit ' . $memory_limit . ' is too low' );
        } elseif ( $memory_bytes < 256 * 1024 * 1024 ) {
            $details[] = self::result( 'php_memory_limit', 'recommended', 'performance', 'Memory limit ' . $memory_limit . ' could be increased' );
        } else {
            $details[] = self::result( 'php_memory_limit', 'good', 'performance', 'Memory limit ' . $memory_limit . ' is adequate' );
        }

        // --- Database connection ---
        global $wpdb;
        if ( ! empty( $wpdb->last_error ) ) {
            $details[] = self::result( 'database_connection', 'critical', 'general', 'Database connection has errors' );
        } else {
            $details[] = self::result( 'database_connection', 'good', 'general', 'Database connection is working properly' );
        }

        // --- HTTPS ---
        if ( is_ssl() ) {
            $details[] = self::result( 'https_status', 'good', 'security', 'Site is using HTTPS' );
        } else {
            $details[] = self::result( 'https_status', 'recommended', 'security', 'Site should use HTTPS for better security' );
        }

        return $details;
    }

    /**
     * Build a single check-result array.
     *
     * @param string $test        Identifier.
     * @param string $status      'good', 'recommended', or 'critical'.
     * @param string $category    'security', 'performance', or 'general'.
     * @param string $description Human-readable description.
     * @return array{test: string, status: string, category: string, description: string}
     */
    private static function result( string $test, string $status, string $category, string $description ): array {
        return compact( 'test', 'status', 'category', 'description' );
    }
}
