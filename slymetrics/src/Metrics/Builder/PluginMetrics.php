<?php

namespace SlyMetrics\Metrics\Builder;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Util\Formatter;

/**
 * Builds Prometheus metrics for installed plugins and themes.
 *
 * @package SlyMetrics\Metrics\Builder
 */
class PluginMetrics {

    /**
     * @param string $site_name Value for the wordpress_site label.
     * @return string Prometheus exposition lines.
     */
    public static function build( string $site_name ): string {
        $out = '';

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins     = function_exists( 'get_plugins' ) ? get_plugins() : array();
        $total_installed = is_array( $all_plugins ) ? count( $all_plugins ) : 0;
        $active_plugins  = get_option( 'active_plugins', array() );
        $active_count    = is_array( $active_plugins ) ? count( $active_plugins ) : 0;
        $inactive_count  = count( array_diff( array_keys( $all_plugins ), $active_plugins ) );

        $out .= "# HELP wordpress_plugins_total Number of active and inactive plugins.\n";
        $out .= "# TYPE wordpress_plugins_total counter\n";
        $out .= Formatter::metric( 'wordpress_plugins_total', $site_name, array( 'status' => 'active' ),   $active_count );
        $out .= Formatter::metric( 'wordpress_plugins_total', $site_name, array( 'status' => 'inactive' ), $inactive_count );
        $out .= Formatter::metric( 'wordpress_plugins_total', $site_name, array( 'status' => 'all' ),      $total_installed );

        // Update availability
        $updates          = get_site_transient( 'update_plugins' );
        $updates_response = isset( $updates->response ) && is_array( $updates->response ) ? $updates->response : array();
        $need_update      = array_intersect( array_keys( $all_plugins ), array_keys( $updates_response ) );
        $up_to_date       = array_diff( array_keys( $all_plugins ), $need_update );

        $out .= "# HELP wordpress_plugins_update_total Plugin update status.\n";
        $out .= "# TYPE wordpress_plugins_update_total counter\n";
        $out .= Formatter::metric( 'wordpress_plugins_update_total', $site_name, array( 'status' => 'available' ), count( $need_update ) );
        $out .= Formatter::metric( 'wordpress_plugins_update_total', $site_name, array( 'status' => 'uptodate' ),  count( $up_to_date ) );

        // Themes
        $themes      = wp_get_themes();
        $child_count  = 0;
        $parent_count = 0;

        if ( is_array( $themes ) ) {
            foreach ( $themes as $theme ) {
                if ( $theme instanceof \WP_Theme && method_exists( $theme, 'is_child_theme' ) && $theme->is_child_theme() ) {
                    $child_count++;
                } else {
                    $parent_count++;
                }
            }
        }

        $out .= "# HELP wordpress_themes_total Number of installed themes.\n";
        $out .= "# TYPE wordpress_themes_total counter\n";
        $out .= Formatter::metric( 'wordpress_themes_total', $site_name, array( 'type' => 'child' ),  $child_count );
        $out .= Formatter::metric( 'wordpress_themes_total', $site_name, array( 'type' => 'parent' ), $parent_count );

        return $out;
    }
}
