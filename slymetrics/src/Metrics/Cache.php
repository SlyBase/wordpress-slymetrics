<?php

namespace SlyMetrics\Metrics;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Metrics\Builder\ContentMetrics;
use SlyMetrics\Metrics\Builder\HeavyMetrics;
use SlyMetrics\Metrics\Builder\PluginMetrics;
use SlyMetrics\Metrics\Builder\PostMetrics;
use SlyMetrics\Metrics\Builder\StaticMetrics;
use SlyMetrics\Metrics\Builder\UserMetrics;

/**
 * Layered transient cache for Prometheus metrics output.
 *
 * Three cache tiers:
 *   fast   –  10 s  (posts, users, plugins, content)
 *   heavy  –   5 min (DB sizes, directory sizes, health checks)
 *   static –  60 min (WP + PHP version / config)
 *
 * @package SlyMetrics\Metrics
 */
class Cache {

    const KEY        = 'slymetrics_cache';
    const TTL        = 10;

    const KEY_HEAVY  = 'slymetrics_heavy_cache';
    const TTL_HEAVY  = 300;

    const KEY_STATIC = 'slymetrics_static_cache';
    const TTL_STATIC = 3600;

    /**
     * Return cached metrics or rebuild and cache them.
     *
     * @return string Complete Prometheus exposition output.
     */
    public static function get(): string {
        $metrics = get_transient( self::KEY );

        if ( false === $metrics ) {
            $metrics = self::build();
            set_transient( self::KEY, $metrics, self::TTL );
        }

        return $metrics;
    }

    /**
     * Invalidate all cache tiers (called on deactivation).
     */
    public static function flush(): void {
        delete_transient( self::KEY );
        delete_transient( self::KEY . '_fast' );
        delete_transient( self::KEY_HEAVY );
        delete_transient( self::KEY_STATIC );
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Build complete metrics output using tiered caching.
     *
     * @return string
     */
    private static function build(): string {
        $site_name = get_bloginfo( 'name' );
        $out       = '';

        // Fast tier
        $fast = get_transient( self::KEY . '_fast' );
        if ( false === $fast ) {
            $fast  = UserMetrics::build( $site_name );
            $fast .= PostMetrics::build( $site_name );
            $fast .= PluginMetrics::build( $site_name );
            $fast .= ContentMetrics::build( $site_name );
            set_transient( self::KEY . '_fast', $fast, self::TTL );
        }
        $out .= $fast;

        // Heavy tier
        $heavy = get_transient( self::KEY_HEAVY );
        if ( false === $heavy ) {
            $heavy = HeavyMetrics::build( $site_name );
            set_transient( self::KEY_HEAVY, $heavy, self::TTL_HEAVY );
        }
        $out .= $heavy;

        // Static tier
        $static = get_transient( self::KEY_STATIC );
        if ( false === $static ) {
            $static = StaticMetrics::build( $site_name );
            set_transient( self::KEY_STATIC, $static, self::TTL_STATIC );
        }
        $out .= $static;

        return $out;
    }
}
