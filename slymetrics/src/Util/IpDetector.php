<?php

namespace SlyMetrics\Util;

defined( 'ABSPATH' ) || exit;

/**
 * Determines the real client IP from server headers.
 *
 * Headers are checked from most-specific (Cloudflare) to least-specific
 * (REMOTE_ADDR). Private/reserved IPs are accepted because Prometheus
 * scrapers typically run inside the same network or cluster.
 *
 * @package SlyMetrics\Util
 */
class IpDetector {

    /** @var string[] Ordered list of headers to inspect. */
    private static $headers = array(
        'HTTP_CF_CONNECTING_IP',    // Cloudflare
        'HTTP_X_REAL_IP',           // Nginx proxy
        'HTTP_X_FORWARDED_FOR',     // Standard proxy header
        'HTTP_X_FORWARDED',         // Proxy header
        'HTTP_X_CLUSTER_CLIENT_IP', // Cluster
        'HTTP_CLIENT_IP',           // Proxy header
        'REMOTE_ADDR',              // Standard
    );

    /**
     * Return the validated client IP address or "unknown".
     *
     * @return string
     */
    public static function get_client_ip(): string {
        foreach ( self::$headers as $header ) {
            if ( empty( $_SERVER[ $header ] ) ) {
                continue;
            }

            $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

            // Handle comma-separated IPs (X-Forwarded-For)
            if ( strpos( $ip, ',' ) !== false ) {
                $ip = trim( explode( ',', $ip )[0] );
            }

            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }

        return 'unknown';
    }
}
