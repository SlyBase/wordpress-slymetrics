<?php

namespace SlyMetrics\Metrics\Builder;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Health\Checker;
use SlyMetrics\Util\Formatter;
use SlyMetrics\Util\Logger;

/**
 * Builds computationally expensive Prometheus metrics (DB queries, directory
 * sizes, health checks).  Results are cached for 5 minutes by the caller.
 *
 * @package SlyMetrics\Metrics\Builder
 */
class HeavyMetrics {

    /**
     * @param string $site_name Value for the wordpress_site label.
     * @return string Prometheus exposition lines.
     */
    public static function build( string $site_name ): string {
        $out = '';
        global $wpdb;

        $out .= self::build_autoload_metrics( $site_name, $wpdb );
        $out .= self::build_db_size_metrics( $site_name, $wpdb );
        $out .= self::build_directory_metrics( $site_name );
        $out .= self::build_health_metrics( $site_name );

        return $out;
    }

    // -----------------------------------------------------------------------
    // Private builders
    // -----------------------------------------------------------------------

    /**
     * @param string   $site_name
     * @param \wpdb    $wpdb
     * @return string
     */
    private static function build_autoload_metrics( string $site_name, $wpdb ): string {
        $out = '';

        try {
            if ( empty( $wpdb->options ) ) {
                Logger::error( 'Options table not available for autoload calculation' );
                return $out;
            }

            $options_table = $wpdb->options;
            if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', str_replace( $wpdb->prefix, '', $options_table ) ) ) {
                Logger::error( 'Options table name contains invalid characters', array( 'table' => $options_table ) );
                return $out;
            }

            $sql = $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_count,
                    ROUND(SUM(LENGTH(option_value)) / 1024) as size_kb,
                    SUM(CASE WHEN option_name LIKE %s THEN 1 ELSE 0 END) as transient_count
                 FROM " . esc_sql( $options_table ) . "
                 WHERE autoload = %s",
                '%transient%',
                'yes'
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $data = $wpdb->get_row( $sql );

            if ( $data && is_object( $data ) ) {
                $total_count     = max( 0, (int) $data->total_count );
                $size_kb         = max( 0, (int) $data->size_kb );
                $transient_count = max( 0, (int) $data->transient_count );

                $out .= "# HELP wordpress_autoload_options_total Number of autoloaded options.\n";
                $out .= "# TYPE wordpress_autoload_options_total gauge\n";
                $out .= Formatter::metric( 'wordpress_autoload_options_total', $site_name, array(), $total_count );

                $out .= "# HELP wordpress_autoload_size_bytes Size of autoloaded options in bytes.\n";
                $out .= "# TYPE wordpress_autoload_size_bytes gauge\n";
                $out .= Formatter::metric( 'wordpress_autoload_size_bytes', $site_name, array(), ( $size_kb * 1024 ) );

                $out .= "# HELP wordpress_autoload_transients_total Number of autoloaded transients.\n";
                $out .= "# TYPE wordpress_autoload_transients_total gauge\n";
                $out .= Formatter::metric( 'wordpress_autoload_transients_total', $site_name, array(), $transient_count );
            }
        } catch ( \Exception $e ) {
            Logger::error( 'Failed to get autoload options data', array( 'error' => $e->getMessage() ) );
        }

        return $out;
    }

    /**
     * @param string   $site_name
     * @param \wpdb    $wpdb
     * @return string
     */
    private static function build_db_size_metrics( string $site_name, $wpdb ): string {
        $out = '';

        try {
            if ( ! defined( 'DB_NAME' ) || empty( DB_NAME ) ) {
                Logger::error( 'Database name not available for size calculation' );
                return $out;
            }

            // Hyphens are valid in MySQL database names and therefore allowed.
            $db_name = preg_replace( '/[^a-zA-Z0-9_\-]/', '', DB_NAME );
            if ( $db_name !== DB_NAME ) {
                Logger::error( 'Database name contains invalid characters', array( 'db_name' => DB_NAME ) );
                return $out;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $db_size_mb = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(ROUND(((data_length + index_length) / 1024 / 1024), 2)) as value
                 FROM information_schema.TABLES
                 WHERE table_schema = %s AND table_type = 'BASE TABLE'",
                $db_name
            ) );

            if ( $db_size_mb > 0 ) {
                $out .= "# HELP wordpress_database_size_bytes Database size in bytes.\n";
                $out .= "# TYPE wordpress_database_size_bytes gauge\n";
                $out .= Formatter::metric( 'wordpress_database_size_bytes', $site_name, array(), ( $db_size_mb * 1024 * 1024 ) );
            }
        } catch ( \Exception $e ) {
            Logger::error( 'Failed to get database size', array( 'error' => $e->getMessage() ) );
        }

        return $out;
    }

    /**
     * @param string $site_name
     * @return string
     */
    private static function build_directory_metrics( string $site_name ): string {
        $out   = '';
        $sizes = self::get_directory_sizes();

        if ( ! empty( $sizes ) ) {
            $out .= "# HELP wordpress_directory_size_bytes Directory sizes in bytes.\n";
            $out .= "# TYPE wordpress_directory_size_bytes gauge\n";

            foreach ( $sizes as $dir_type => $size_mb ) {
                $out .= Formatter::metric( 'wordpress_directory_size_bytes', $site_name, array( 'directory' => $dir_type ), ( $size_mb * 1024 * 1024 ) );
            }
        }

        return $out;
    }

    /**
     * @param string $site_name
     * @return string
     */
    private static function build_health_metrics( string $site_name ): string {
        $out = '';

        $health_summary = Checker::get_summary();
        if ( ! empty( $health_summary ) ) {
            $out .= "# HELP wordpress_health_check_total Site health check results.\n";
            $out .= "# TYPE wordpress_health_check_total gauge\n";
            foreach ( $health_summary as $category => $count ) {
                $out .= Formatter::metric( 'wordpress_health_check_total', $site_name, array( 'category' => $category ), $count );
            }
        }

        $health_details = Checker::get_details();
        if ( ! empty( $health_details ) ) {
            $out .= "# HELP wordpress_health_check_detail_info Individual health check test results.\n";
            $out .= "# TYPE wordpress_health_check_detail_info gauge\n";

            foreach ( $health_details as $test_detail ) {
                switch ( $test_detail['status'] ) {
                    case 'good':
                        $status_value = 1;
                        break;
                    case 'critical':
                        $status_value = -1;
                        break;
                    default: // recommended
                        $status_value = 0;
                        break;
                }

                $out .= Formatter::metric( 'wordpress_health_check_detail_info', $site_name, array(
                    'test_name'   => $test_detail['test'],
                    'status'      => $test_detail['status'],
                    'category'    => $test_detail['category'],
                    'description' => $test_detail['description'],
                ), $status_value );
            }
        }

        return $out;
    }

    // -----------------------------------------------------------------------
    // Directory size helpers
    // -----------------------------------------------------------------------

    /**
     * Return directory sizes in MB for uploads, themes, and plugins.
     *
     * @return array<string, float>
     */
    private static function get_directory_sizes(): array {
        $sizes = array();

        try {
            $upload_dir = wp_upload_dir();
            if ( isset( $upload_dir['basedir'] ) && is_dir( $upload_dir['basedir'] ) ) {
                $sizes['uploads'] = round( self::directory_size( $upload_dir['basedir'] ) / ( 1024 * 1024 ), 2 );
            }

            $themes_dir = get_theme_root();
            if ( is_dir( $themes_dir ) ) {
                $sizes['themes'] = round( self::directory_size( $themes_dir ) / ( 1024 * 1024 ), 2 );
            }

            $plugins_dir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : SLYMET_PLUGIN_DIR . '../..';
            if ( is_dir( $plugins_dir ) ) {
                $sizes['plugins'] = round( self::directory_size( $plugins_dir ) / ( 1024 * 1024 ), 2 );
            }

            $sizes['total'] = round( array_sum( $sizes ), 2 );
        } catch ( \Exception $e ) {
            // Return partial results on error
        }

        return $sizes;
    }

    /**
     * Recursively calculate directory size in bytes.
     *
     * @param string $directory Absolute path.
     * @return int Size in bytes.
     */
    private static function directory_size( string $directory ): int {
        if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
            return 0;
        }

        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $directory, \RecursiveDirectoryIterator::SKIP_DOTS ),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ( $iterator as $file ) {
                if ( $file->isFile() && $file->isReadable() ) {
                    $size += $file->getSize();
                }
            }
        } catch ( \Exception $e ) {
            // Fallback: manual traversal
            $files = glob( rtrim( $directory, '/' ) . '/*', GLOB_MARK );
            if ( is_array( $files ) ) {
                foreach ( $files as $file ) {
                    if ( is_file( $file ) ) {
                        $size += filesize( $file );
                    } elseif ( is_dir( $file ) ) {
                        $size += self::directory_size( $file );
                    }
                }
            }
        }

        return $size;
    }
}
