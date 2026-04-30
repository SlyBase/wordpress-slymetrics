<?php

namespace SlyMetrics\Metrics\Builder;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Util\Formatter;

/**
 * Builds Prometheus metrics for comments, categories, media, and tags.
 *
 * @package SlyMetrics\Metrics\Builder
 */
class ContentMetrics {

    /**
     * @param string $site_name Value for the wordpress_site label.
     * @return string Prometheus exposition lines.
     */
    public static function build( string $site_name ): string {
        $out = '';

        // Comments by status
        $comments = wp_count_comments();
        $counts   = is_object( $comments ) ? get_object_vars( $comments ) : (array) $comments;

        $out .= "# HELP wordpress_comments_total Total number of comments by status.\n";
        $out .= "# TYPE wordpress_comments_total counter\n";

        foreach ( $counts as $status => $count ) {
            if ( $status === 'total_comments' || ! is_numeric( $count ) ) {
                continue;
            }

            if ( $status === 'awaiting_moderation' ) {
                $label = 'moderated';
            } elseif ( $status === 'post-trashed' ) {
                $label = 'post_trashed';
            } else {
                $label = $status;
            }

            $out .= Formatter::metric( 'wordpress_comments_total', $site_name, array( 'status' => $label ), (int) $count );
        }

        // Categories
        $category_count = (int) wp_count_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
        $out .= "# HELP wordpress_categories_total Total number of categories.\n";
        $out .= "# TYPE wordpress_categories_total counter\n";
        $out .= Formatter::metric( 'wordpress_categories_total', $site_name, array(), $category_count );

        // Media attachments
        $media_count = self::get_media_count();
        $out .= "# HELP wordpress_media_total Total number of media items.\n";
        $out .= "# TYPE wordpress_media_total counter\n";
        $out .= Formatter::metric( 'wordpress_media_total', $site_name, array(), $media_count );

        // Tags
        $tag_count = (int) wp_count_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false ) );
        $out .= "# HELP wordpress_tags_total Total number of tags.\n";
        $out .= "# TYPE wordpress_tags_total counter\n";
        $out .= Formatter::metric( 'wordpress_tags_total', $site_name, array(), $tag_count );

        return $out;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Return total media count with short-term caching.
     *
     * @return int
     */
    private static function get_media_count(): int {
        $cached = get_transient( 'slymetrics_media_count' );
        if ( false !== $cached ) {
            return (int) $cached;
        }

        $media_count = 0;
        $attachments = wp_count_posts( 'attachment' );

        if ( is_object( $attachments ) ) {
            foreach ( get_object_vars( $attachments ) as $count ) {
                $media_count += (int) $count;
            }
        }

        // Fallback for edge cases where wp_count_posts() returns nothing
        if ( $media_count === 0 ) {
            $media_count = (int) count( get_posts( array(
                'post_type'   => 'attachment',
                'post_status' => 'any',
                'numberposts' => -1,
            ) ) );
        }

        set_transient( 'slymetrics_media_count', $media_count, 300 );

        return $media_count;
    }
}
