<?php

namespace SlyMetrics\MCP\Ability;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Util\Logger;

/**
 * Ability: metrics/get-storage
 *
 * Returns WordPress storage data: autoloaded options count and size,
 * database size, and directory sizes for uploads/themes/plugins.
 * Mirrors HeavyMetrics Prometheus data (excludes health checks).
 *
 * No parameters required. Requires manage_options capability.
 *
 * @package SlyMetrics\MCP\Ability
 */
class GetStorageAbility {

    /**
     * Returns the ability definition array for wp_register_ability().
     *
     * @return array<string, mixed>
     */
    public static function definition(): array {
        return array(
            'name'                => 'metrics/get-storage',
            'label'               => 'Storage Metrics',
            'category'            => 'site',
            'description'         => 'Returns WordPress storage data: autoloaded options count, size in bytes and transient count; database size in bytes; and directory sizes in bytes for uploads, themes, plugins, and total. No parameters required.',
            'input_schema'        => array( 'type' => 'object', 'properties' => array(), 'required' => array() ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'autoload'    => array(
                        'type'        => 'object',
                        'description' => 'Autoloaded options: total (count), size_bytes, transients (count)',
                    ),
                    'database'    => array(
                        'type'        => 'object',
                        'description' => 'Database size_bytes',
                    ),
                    'directories' => array(
                        'type'        => 'object',
                        'description' => 'Directory sizes in bytes: uploads, themes, plugins, total',
                    ),
                ),
            ),
            'permission_callback' => static fn() => current_user_can( 'manage_options' ),
            'execute_callback'    => array( self::class, 'execute' ),
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        );
    }

    /**
     * Builds and returns storage metrics.
     *
     * @return array<string, mixed>
     */
    public static function execute(): array {
        return array(
            'autoload'    => self::get_autoload_data(),
            'database'    => self::get_database_data(),
            'directories' => self::get_directory_data(),
        );
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * @return array{total: int, size_bytes: int, transients: int}
     */
    private static function get_autoload_data(): array {
        $result = array( 'total' => 0, 'size_bytes' => 0, 'transients' => 0 );

        global $wpdb;
        if ( ! isset( $wpdb ) || empty( $wpdb->options ) ) {
            return $result;
        }

        $options_table = $wpdb->options;
        if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', str_replace( $wpdb->prefix, '', $options_table ) ) ) {
            return $result;
        }

        try {
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
                $result['total']      = max( 0, (int) $data->total_count );
                $result['size_bytes'] = max( 0, (int) $data->size_kb ) * 1024;
                $result['transients'] = max( 0, (int) $data->transient_count );
            }
        } catch ( \Exception $e ) {
            Logger::error( 'GetStorageAbility: failed to get autoload data', array( 'error' => $e->getMessage() ) );
        }

        return $result;
    }

    /**
     * @return array{size_bytes: int}
     */
    private static function get_database_data(): array {
        $result = array( 'size_bytes' => 0 );

        if ( ! defined( 'DB_NAME' ) || empty( DB_NAME ) ) {
            return $result;
        }

        $db_name = preg_replace( '/[^a-zA-Z0-9_\-]/', '', DB_NAME );
        if ( $db_name !== DB_NAME ) {
            return $result;
        }

        global $wpdb;
        if ( ! isset( $wpdb ) ) {
            return $result;
        }

        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $db_size_mb = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(ROUND(((data_length + index_length) / 1024 / 1024), 2)) as value
                 FROM information_schema.TABLES
                 WHERE table_schema = %s AND table_type = 'BASE TABLE'",
                $db_name
            ) );

            if ( $db_size_mb > 0 ) {
                $result['size_bytes'] = (int) round( $db_size_mb * 1024 * 1024 );
            }
        } catch ( \Exception $e ) {
            Logger::error( 'GetStorageAbility: failed to get database size', array( 'error' => $e->getMessage() ) );
        }

        return $result;
    }

    /**
     * @return array{uploads: int, themes: int, plugins: int, total: int}
     */
    private static function get_directory_data(): array {
        $sizes = array( 'uploads' => 0, 'themes' => 0, 'plugins' => 0, 'total' => 0 );

        try {
            $upload_dir = wp_upload_dir();
            if ( isset( $upload_dir['basedir'] ) && is_dir( $upload_dir['basedir'] ) ) {
                $sizes['uploads'] = self::directory_size( $upload_dir['basedir'] );
            }

            $themes_dir = get_theme_root();
            if ( is_string( $themes_dir ) && is_dir( $themes_dir ) ) {
                $sizes['themes'] = self::directory_size( $themes_dir );
            }

            $plugins_dir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '';
            if ( $plugins_dir && is_dir( $plugins_dir ) ) {
                $sizes['plugins'] = self::directory_size( $plugins_dir );
            }

            $sizes['total'] = $sizes['uploads'] + $sizes['themes'] + $sizes['plugins'];
        } catch ( \Exception $e ) {
            // Return partial results on error.
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
