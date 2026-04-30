<?php

namespace SlyMetrics\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Ability: metrics/get-content
 *
 * Returns WordPress content counts: comments by status, categories,
 * media attachments, and tags. Mirrors ContentMetrics Prometheus data.
 *
 * No parameters required. Requires manage_options capability.
 *
 * @package SlyMetrics\MCP\Ability
 */
class GetContentAbility {

    /**
     * Returns the ability definition array for wp_register_ability().
     *
     * @return array<string, mixed>
     */
    public static function definition(): array {
        return array(
            'name'                => 'metrics/get-content',
            'label'               => 'Content Metrics',
            'category'            => 'site',
            'description'         => 'Returns WordPress content counts: comments by status (approved, spam, trash, moderated, total), total categories, total media attachments, and total tags. No parameters required.',
            'input_schema'        => array( 'type' => array( 'object', 'null' ), 'properties' => (object) array(), 'required' => array() ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'comments'   => array( 'type' => 'object',  'description' => 'Comment counts keyed by status (approved, spam, trash, moderated, total_comments, etc.)' ),
                    'categories' => array( 'type' => 'integer', 'description' => 'Total number of categories' ),
                    'media'      => array( 'type' => 'integer', 'description' => 'Total number of media attachments' ),
                    'tags'       => array( 'type' => 'integer', 'description' => 'Total number of tags' ),
                ),
            ),
            'permission_callback' => static fn() => current_user_can( 'manage_options' ),
            'execute_callback'    => array( self::class, 'execute' ),
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        );
    }

    /**
     * Builds and returns content metrics.
     *
     * @return array<string, mixed>
     */
    public static function execute(): array {
        $raw     = wp_count_comments();
        $raw_arr = is_object( $raw ) ? get_object_vars( $raw ) : (array) $raw;

        $comments = array();
        foreach ( $raw_arr as $status => $count ) {
            if ( ! is_numeric( $count ) ) {
                continue;
            }
            // Normalize label names to match ContentMetrics conventions.
            if ( $status === 'awaiting_moderation' ) {
                $key = 'moderated';
            } elseif ( $status === 'post-trashed' ) {
                $key = 'post_trashed';
            } else {
                $key = $status;
            }
            $comments[ $key ] = (int) $count;
        }

        $attachments = wp_count_posts( 'attachment' );
        $media       = 0;
        if ( is_object( $attachments ) ) {
            foreach ( get_object_vars( $attachments ) as $count ) {
                $media += (int) $count;
            }
        }

        return array(
            'comments'   => $comments,
            'categories' => (int) wp_count_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ),
            'media'      => $media,
            'tags'       => (int) wp_count_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false ) ),
        );
    }
}
