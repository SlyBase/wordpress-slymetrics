<?php

namespace SlyMetrics\Util;

defined( 'ABSPATH' ) || exit;

/**
 * Simple debug logger – only writes when WP_DEBUG and WP_DEBUG_LOG are both on.
 *
 * @package SlyMetrics\Util
 */
class Logger {

    /**
     * Log an error message with optional context.
     *
     * @param string  $message Human-readable description.
     * @param array   $context Additional key/value data for debugging.
     */
    public static function error( string $message, array $context = [] ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            $context_string = ! empty( $context ) ? ' Context: ' . wp_json_encode( $context ) : '';
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'SlyMetrics Error: ' . $message . $context_string );
        }
    }
}
