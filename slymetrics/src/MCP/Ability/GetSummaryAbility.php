<?php

namespace SlyMetrics\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Ability: metrics/get-summary
 *
 * Returns a complete snapshot of all key WordPress metrics in a single call:
 * users by role, post/page counts by status, plugin counts (active/inactive/total),
 * active WordPress version, and PHP version.
 *
 * No parameters required. Requires manage_options capability.
 *
 * @package SlyMetrics\MCP\Ability
 */
class GetSummaryAbility {

    /**
     * Returns the ability definition array for wp_register_ability().
     *
     * @return array<string, mixed>
     */
    public static function definition(): array {
        return array(
            'name'               => 'metrics/get-summary',
            'description'        => 'Returns a complete WordPress metrics snapshot: users by role, post/page counts by status, plugin counts (active/inactive/total), WordPress version, PHP version. No parameters required.',
            'input_schema'       => array( 'type' => 'object', 'properties' => array(), 'required' => array() ),
            'output_schema'      => array(
                'type'       => 'object',
                'properties' => array(
                    'site'              => array( 'type' => 'string',  'description' => 'WordPress site name' ),
                    'users'             => array( 'type' => 'object',  'description' => 'User counts: total and by_role map' ),
                    'posts'             => array( 'type' => 'object',  'description' => 'Post counts by status: published, draft' ),
                    'pages'             => array( 'type' => 'object',  'description' => 'Page counts by status: published, draft' ),
                    'plugins'           => array( 'type' => 'object',  'description' => 'Plugin counts: active, inactive, total' ),
                    'wordpress_version' => array( 'type' => 'string',  'description' => 'Installed WordPress version' ),
                    'php_version'       => array( 'type' => 'string',  'description' => 'Active PHP version' ),
                ),
            ),
            'permission_callback' => static fn() => current_user_can( 'manage_options' ),
            'execute_callback'    => array( self::class, 'execute' ),
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        );
    }

    /**
     * Builds and returns the metrics summary.
     *
     * @return array<string, mixed>
     */
    public static function execute(): array {
        $users_data = count_users();

        $posts = wp_count_posts();
        $pages = wp_count_posts( 'page' );

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = function_exists( 'get_plugins' ) ? get_plugins() : array();
        $active_plugins = (array) get_option( 'active_plugins', array() );
        $inactive_count = count( $all_plugins ) - count( $active_plugins );

        return array(
            'site'              => get_bloginfo( 'name' ),
            'users'             => array(
                'total'   => (int) ( $users_data['total_users'] ?? 0 ),
                'by_role' => array_map( 'intval', $users_data['avail_roles'] ?? array() ),
            ),
            'posts'             => array(
                'published' => (int) ( $posts->publish ?? 0 ),
                'draft'     => (int) ( $posts->draft   ?? 0 ),
            ),
            'pages'             => array(
                'published' => (int) ( $pages->publish ?? 0 ),
                'draft'     => (int) ( $pages->draft   ?? 0 ),
            ),
            'plugins'           => array(
                'active'   => count( $active_plugins ),
                'inactive' => max( 0, $inactive_count ),
                'total'    => count( $all_plugins ),
            ),
            'wordpress_version' => get_bloginfo( 'version' ),
            'php_version'       => PHP_VERSION,
        );
    }
}
