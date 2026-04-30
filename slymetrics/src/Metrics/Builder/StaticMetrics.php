<?php

namespace SlyMetrics\Metrics\Builder;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Util\Formatter;
use SlyMetrics\Util\Logger;
use SlyMetrics\Util\SizeConverter;

/**
 * Builds Prometheus metrics for mostly-static data: WordPress version,
 * PHP version, and PHP configuration values.  Results are cached for 1 hour.
 *
 * @package SlyMetrics\Metrics\Builder
 */
class StaticMetrics {

    /**
     * @param string $site_name Value for the wordpress_site label.
     * @return string Prometheus exposition lines.
     */
    public static function build( string $site_name ): string {
        $out = '';

        $out .= self::build_wp_version_metrics( $site_name );
        $out .= self::build_php_metrics( $site_name );

        return $out;
    }

    // -----------------------------------------------------------------------
    // Private builders
    // -----------------------------------------------------------------------

    /**
     * @param string $site_name
     * @return string
     */
    private static function build_wp_version_metrics( string $site_name ): string {
        $wp_version       = get_bloginfo( 'version' );
        $update_available = 0;

        if ( ! wp_installing() && function_exists( 'get_core_updates' ) ) {
            try {
                $core_updates = get_core_updates();
                if ( is_array( $core_updates ) && ! empty( $core_updates ) ) {
                    $latest = $core_updates[0];
                    if ( isset( $latest->response ) && $latest->response === 'upgrade' ) {
                        $update_available = 1;
                    }
                }
            } catch ( \Exception $e ) {
                // Ignore errors in update check
            }
        }

        $out  = "# HELP wordpress_version WordPress version information.\n";
        $out .= "# TYPE wordpress_version gauge\n";
        $out .= Formatter::metric( 'wordpress_version', $site_name, array(
            'version'          => $wp_version,
            'update_available' => (string) $update_available,
        ), 1 );

        return $out;
    }

    /**
     * @param string $site_name
     * @return string
     */
    private static function build_php_metrics( string $site_name ): string {
        $out = '';

        try {
            $out .= "# HELP wordpress_php_info PHP configuration information.\n";
            $out .= "# TYPE wordpress_php_info gauge\n";
            $out .= Formatter::metric( 'wordpress_php_info', $site_name, array( 'type' => 'version',         'label' => PHP_VERSION                    ), PHP_VERSION_ID );
            $out .= Formatter::metric( 'wordpress_php_info', $site_name, array( 'type' => 'major_version',   'label' => (string) PHP_MAJOR_VERSION     ), PHP_MAJOR_VERSION );
            $out .= Formatter::metric( 'wordpress_php_info', $site_name, array( 'type' => 'minor_version',   'label' => (string) PHP_MINOR_VERSION     ), PHP_MINOR_VERSION );
            $out .= Formatter::metric( 'wordpress_php_info', $site_name, array( 'type' => 'release_version', 'label' => (string) PHP_RELEASE_VERSION   ), PHP_RELEASE_VERSION );

            $out .= "# HELP wordpress_php_version_info PHP version as readable string.\n";
            $out .= "# TYPE wordpress_php_version_info gauge\n";
            $out .= Formatter::metric( 'wordpress_php_version_info', $site_name, array( 'php_version' => PHP_VERSION ), 1 );

            $out .= "# HELP wordpress_config_info WordPress and PHP configuration values.\n";
            $out .= "# TYPE wordpress_config_info gauge\n";
            $out .= "# HELP wordpress_memory_limit_info Memory limit for table display.\n";
            $out .= "# TYPE wordpress_memory_limit_info gauge\n";
            $out .= "# HELP wordpress_upload_max_info Upload max filesize for table display.\n";
            $out .= "# TYPE wordpress_upload_max_info gauge\n";
            $out .= "# HELP wordpress_post_max_info Post max size for table display.\n";
            $out .= "# TYPE wordpress_post_max_info gauge\n";
            $out .= "# HELP wordpress_exec_time_info Max execution time for table display.\n";
            $out .= "# TYPE wordpress_exec_time_info gauge\n";

            if ( function_exists( 'ini_get' ) ) {
                $php_configs = array( 'max_input_vars', 'max_execution_time', 'memory_limit', 'max_input_time', 'upload_max_filesize', 'post_max_size' );

                foreach ( $php_configs as $php_variable ) {
                    $php_value     = ini_get( $php_variable );
                    $numeric_value = SizeConverter::to_bytes( $php_value );

                    $out .= Formatter::metric( 'wordpress_php_info',    $site_name, array( 'type'   => $php_variable, 'label' => $php_value ), $numeric_value );
                    $out .= Formatter::metric( 'wordpress_config_info', $site_name, array( 'config' => $php_variable, 'value' => $php_value ), $numeric_value );

                    if ( $php_variable === 'memory_limit' ) {
                        $out .= Formatter::metric( 'wordpress_memory_limit_info', $site_name, array( 'memory_limit' => $php_value ), 1 );
                    } elseif ( $php_variable === 'upload_max_filesize' ) {
                        $out .= Formatter::metric( 'wordpress_upload_max_info',   $site_name, array( 'upload_max'   => $php_value ), 1 );
                    } elseif ( $php_variable === 'post_max_size' ) {
                        $out .= Formatter::metric( 'wordpress_post_max_info',     $site_name, array( 'post_max'     => $php_value ), 1 );
                    } elseif ( $php_variable === 'max_execution_time' ) {
                        $out .= Formatter::metric( 'wordpress_exec_time_info',    $site_name, array( 'exec_time'    => $php_value ), 1 );
                    }
                }
            }
        } catch ( \Exception $e ) {
            Logger::error( 'Failed to get PHP information', array( 'error' => $e->getMessage() ) );
        }

        return $out;
    }
}
