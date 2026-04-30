<?php

namespace SlyMetrics\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Ability: metrics/get-plugins
 *
 * Returns plugin counts (active, inactive, total) and whether updates are available.
 * Also returns the number of themes installed.
 *
 * No parameters required. Requires manage_options capability.
 *
 * @package SlyMetrics\MCP\Ability
 */
class GetPluginsAbility {

    /**
     * Returns the ability definition array for wp_register_ability().
     *
     * @return array<string, mixed>
     */
    public static function definition(): array {
        return array(
            'name'               => 'metrics/get-plugins',
            'description'        => 'Returns plugin counts (active, inactive, total), how many plugins have updates available, and the number of installed themes. No parameters required.',
            'input_schema'       => array( 'type' => 'object', 'properties' => array(), 'required' => array() ),
            'output_schema'      => array(
                'type'       => 'object',
                'properties' => array(
                    'plugins' => array(
                        'type'        => 'object',
                        'description' => 'Plugin counts: active, inactive, total, updates_available',
                    ),
                    'themes'  => array(
                        'type'        => 'object',
                        'description' => 'Theme counts: total, parent, child',
                    ),
                ),
            ),
            'permission_callback' => static fn() => current_user_can( 'manage_options' ),
            'execute_callback'    => array( self::class, 'execute' ),
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        );
    }

    /**
     * Builds and returns plugin and theme metrics.
     *
     * @return array<string, mixed>
     */
    public static function execute(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = function_exists( 'get_plugins' ) ? get_plugins() : array();
        $active_plugins = (array) get_option( 'active_plugins', array() );
        $inactive_count = count( $all_plugins ) - count( $active_plugins );

        $updates          = get_site_transient( 'update_plugins' );
        $updates_response = isset( $updates->response ) && is_array( $updates->response )
            ? $updates->response
            : array();
        $updates_available = count( array_intersect( array_keys( $all_plugins ), array_keys( $updates_response ) ) );

        $themes       = wp_get_themes();
        $theme_total  = is_array( $themes ) ? count( $themes ) : 0;
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

        return array(
            'plugins' => array(
                'active'            => count( $active_plugins ),
                'inactive'          => max( 0, $inactive_count ),
                'total'             => count( $all_plugins ),
                'updates_available' => $updates_available,
            ),
            'themes'  => array(
                'total'  => $theme_total,
                'parent' => $parent_count,
                'child'  => $child_count,
            ),
        );
    }
}
