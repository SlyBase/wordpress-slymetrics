<?php

namespace SlyMetrics\Util;

defined( 'ABSPATH' ) || exit;

/**
 * Prometheus metric formatting helpers.
 *
 * @package SlyMetrics\Util
 */
class Formatter {

    /**
     * Escape a label value for Prometheus exposition format.
     * Prevents injection and ensures valid labels.
     *
     * @param string $value Raw value.
     * @return string Safely escaped value.
     */
    public static function escape_label_value( string $value ): string {
        if ( strlen( $value ) > 1000 ) {
            $value = substr( $value, 0, 1000 ) . '...';
            Logger::error( 'Label value truncated due to excessive length' );
        }

        // Decode HTML entities (e.g. &#039; → ')
        $value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        // Escape special characters required by Prometheus format
        $value = str_replace(
            array( '\\', '"', "\n", "\r", "\t" ),
            array( '\\\\', '\\"', '\\n', '\\r', '\\t' ),
            $value
        );

        // Remove control characters; keep multi-byte UTF-8 characters
        $value = (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $value );

        return $value;
    }

    /**
     * Render a single Prometheus metric line.
     *
     * @param string $metric_name Metric name (sanitized automatically).
     * @param string $site_name   Value for the wordpress_site label.
     * @param array  $labels      Extra key => value label pairs.
     * @param mixed  $value       Numeric metric value.
     * @return string Formatted line including trailing newline.
     */
    public static function metric( string $metric_name, string $site_name, array $labels = [], $value = 1 ): string {
        $metric_name = (string) preg_replace( '/[^a-zA-Z0-9_:]/', '_', $metric_name );
        if ( empty( $metric_name ) ) {
            Logger::error( 'Invalid metric name provided' );
            return '';
        }

        if ( ! is_numeric( $value ) ) {
            Logger::error( 'Non-numeric value provided for metric', array( 'metric' => $metric_name, 'value' => $value ) );
            $value = 0;
        }

        $label_string = 'wordpress_site="' . self::escape_label_value( $site_name ) . '"';

        foreach ( $labels as $key => $label_value ) {
            $key = (string) preg_replace( '/[^a-zA-Z0-9_]/', '_', (string) $key );
            if ( ! empty( $key ) ) {
                $label_string .= ',' . $key . '="' . self::escape_label_value( (string) $label_value ) . '"';
            }
        }

        return $metric_name . '{' . $label_string . '} ' . $value . "\n";
    }
}
