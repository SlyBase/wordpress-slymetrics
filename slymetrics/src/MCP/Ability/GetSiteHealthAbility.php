<?php

namespace SlyMetrics\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Ability: metrics/get-site-health
 *
 * Returns runtime environment information useful for diagnostics:
 * WordPress version (and whether an update is available), PHP version,
 * database server version, debug mode status, and key PHP ini limits.
 *
 * No parameters required. Requires manage_options capability.
 *
 * @package SlyMetrics\MCP\Ability
 */
class GetSiteHealthAbility {

    /**
     * Returns the ability definition array for wp_register_ability().
     *
     * @return array<string, mixed>
     */
    public static function definition(): array {
        return array(
            'name'               => 'metrics/get-site-health',
            'description'        => 'Returns WordPress runtime diagnostics: WP version, update availability, PHP version, database version, WP_DEBUG status, and key PHP ini limits (memory_limit, max_execution_time, upload_max_filesize). No parameters required.',
            'input_schema'       => array( 'type' => 'object', 'properties' => array(), 'required' => array() ),
            'output_schema'      => array(
                'type'       => 'object',
                'properties' => array(
                    'wordpress' => array( 'type' => 'object', 'description' => 'WP version and update status' ),
                    'php'       => array( 'type' => 'object', 'description' => 'PHP version and key ini values' ),
                    'database'  => array( 'type' => 'object', 'description' => 'Database server version' ),
                    'debug'     => array( 'type' => 'object', 'description' => 'WP_DEBUG and WP_DEBUG_LOG flags' ),
                ),
            ),
            'permission_callback' => static fn() => current_user_can( 'manage_options' ),
            'execute_callback'    => array( self::class, 'execute' ),
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        );
    }

    /**
     * Builds and returns site health / environment data.
     *
     * @return array<string, mixed>
     */
    public static function execute(): array {
        global $wpdb;

        $wp_version       = get_bloginfo( 'version' );
        $update_available = false;

        if ( ! wp_installing() && function_exists( 'get_core_updates' ) ) {
            $core_updates = get_core_updates();
            if ( is_array( $core_updates ) && ! empty( $core_updates ) ) {
                $latest = $core_updates[0];
                if ( isset( $latest->response ) && $latest->response === 'upgrade' ) {
                    $update_available = true;
                }
            }
        }

        $db_version = isset( $wpdb ) && method_exists( $wpdb, 'db_version' )
            ? $wpdb->db_version()
            : '';

        return array(
            'wordpress' => array(
                'version'          => $wp_version,
                'update_available' => $update_available,
            ),
            'php'       => array(
                'version'           => PHP_VERSION,
                'memory_limit'      => function_exists( 'ini_get' ) ? ini_get( 'memory_limit' )      : '',
                'max_execution_time'=> function_exists( 'ini_get' ) ? ini_get( 'max_execution_time' ) : '',
                'upload_max_filesize'=> function_exists( 'ini_get' ) ? ini_get( 'upload_max_filesize' ): '',
            ),
            'database'  => array(
                'version' => $db_version,
            ),
            'debug'     => array(
                'wp_debug'     => defined( 'WP_DEBUG' )     && WP_DEBUG,
                'wp_debug_log' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
            ),
        );
    }
}
