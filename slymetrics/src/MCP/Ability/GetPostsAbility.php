<?php

namespace SlyMetrics\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Ability: metrics/get-posts
 *
 * Returns post counts by status for a given post type.
 *
 * Parameters:
 *   post_type (string, optional, default "post") – the registered post type slug.
 *     Allowed values: any registered public post type (e.g. "post", "page", "product").
 *
 * Requires manage_options capability.
 *
 * @package SlyMetrics\MCP\Ability
 */
class GetPostsAbility {

    /** Post types considered valid even without full WP post-type registry in tests. */
    private const SAFE_TYPES = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item' );

    /**
     * Returns the ability definition array for wp_register_ability().
     *
     * @return array<string, mixed>
     */
    public static function definition(): array {
        return array(
            'name'               => 'metrics/get-posts',
            'label'              => 'Post Metrics',
            'category'           => 'site',
            'description'        => 'Returns post counts by status for a given post type. Parameter: post_type (string, optional, default "post"). Returns an object with status names as keys (publish, draft, trash, etc.) and counts as values, plus a "total" field.',
            'input_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'post_type' => array(
                        'type'        => 'string',
                        'description' => 'Registered post type slug (e.g. "post", "page", "product"). Defaults to "post".',
                        'default'     => 'post',
                    ),
                ),
                'required'   => array(),
            ),
            'output_schema'      => array(
                'type'       => 'object',
                'properties' => array(
                    'post_type' => array( 'type' => 'string',  'description' => 'The queried post type slug' ),
                    'counts'    => array( 'type' => 'object',  'description' => 'Map of status => count (publish, draft, trash, …)' ),
                    'total'     => array( 'type' => 'integer', 'description' => 'Sum of all status counts' ),
                ),
            ),
            'permission_callback' => static fn() => current_user_can( 'manage_options' ),
            'execute_callback'    => array( self::class, 'execute' ),
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        );
    }

    /**
     * Builds and returns post counts for the requested post type.
     *
     * @param array<string, mixed> $params Ability input parameters.
     * @return array<string, mixed>
     */
    public static function execute( array $params = array() ): array {
        $post_type = isset( $params['post_type'] ) ? sanitize_key( $params['post_type'] ) : 'post';

        if ( empty( $post_type ) ) {
            $post_type = 'post';
        }

        $raw    = wp_count_posts( $post_type );
        $counts = is_object( $raw ) ? (array) $raw : array();
        $total  = 0;

        $clean = array();
        foreach ( $counts as $status => $count ) {
            $int_count          = (int) $count;
            $clean[ $status ]   = $int_count;
            $total             += $int_count;
        }

        return array(
            'post_type' => $post_type,
            'counts'    => $clean,
            'total'     => $total,
        );
    }
}
