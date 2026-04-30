<?php

namespace SlyMetrics\Util;

defined( 'ABSPATH' ) || exit;

/**
 * Converts PHP INI size strings (e.g. "128M") to bytes.
 *
 * @package SlyMetrics\Util
 */
class SizeConverter {

    /**
     * Convert a size string like "128M", "1G", "512K" to bytes.
     *
     * @param string $size Raw size string from ini_get().
     * @return float Size in bytes.
     */
    public static function to_bytes( string $size ): float {
        $size  = trim( $size );
        $unit  = strtoupper( substr( $size, -1 ) );
        $value = (float) substr( $size, 0, -1 );

        switch ( $unit ) {
            case 'G':
                return $value * 1024 * 1024 * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'K':
                return $value * 1024;
            default:
                return (float) $size;
        }
    }
}
