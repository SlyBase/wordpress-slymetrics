<?php

namespace SlyMetrics\Metrics\Builder;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Util\Formatter;

/**
 * Builds Prometheus metrics for posts and pages.
 *
 * @package SlyMetrics\Metrics\Builder
 */
class PostMetrics {

    /**
     * @param string $site_name Value for the wordpress_site label.
     * @return string Prometheus exposition lines.
     */
    public static function build( string $site_name ): string {
        $out = '';

        // Posts
        $posts       = wp_count_posts();
        $posts_pub   = isset( $posts->publish ) ? (int) $posts->publish : 0;
        $posts_draft = isset( $posts->draft )   ? (int) $posts->draft   : 0;
        $posts_total = 0;

        if ( is_object( $posts ) ) {
            foreach ( get_object_vars( $posts ) as $cnt ) {
                $posts_total += (int) $cnt;
            }
        }

        $out .= "# HELP wordpress_posts_total Number of posts.\n";
        $out .= "# TYPE wordpress_posts_total counter\n";
        $out .= Formatter::metric( 'wordpress_posts_total', $site_name, array( 'status' => 'published' ), $posts_pub );
        $out .= Formatter::metric( 'wordpress_posts_total', $site_name, array( 'status' => 'draft' ),     $posts_draft );
        $out .= Formatter::metric( 'wordpress_posts_total', $site_name, array( 'status' => 'all' ),       $posts_total );

        // Pages
        $pages       = wp_count_posts( 'page' );
        $pages_pub   = isset( $pages->publish ) ? (int) $pages->publish : 0;
        $pages_draft = isset( $pages->draft )   ? (int) $pages->draft   : 0;

        $out .= "# HELP wordpress_pages_total Number of pages.\n";
        $out .= "# TYPE wordpress_pages_total counter\n";
        $out .= Formatter::metric( 'wordpress_pages_total', $site_name, array( 'status' => 'published' ), $pages_pub );
        $out .= Formatter::metric( 'wordpress_pages_total', $site_name, array( 'status' => 'draft' ),     $pages_draft );
        $out .= Formatter::metric( 'wordpress_pages_total', $site_name, array( 'status' => 'all' ),       ( $pages_pub + $pages_draft ) );

        return $out;
    }
}
