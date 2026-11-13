<?php
/**
 * Uninstall SlyMetrics Plugin
 *
 * Fired when the plugin is uninstalled.
 * Removes all plugin data from the database.
 *
 * @package SlyMetrics
 * @author Timon Först
 * @since 1.3.2
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Remove all SlyMetrics plugin data from the database
 */
function slymetrics_uninstall_cleanup() {
    // Remove plugin options
    $plugin_options = array(
        'slymetrics_auth_token',
        'slymetrics_api_key',
        'slymetrics_encryption_key',
        'slymetrics_rewrite_rules_flushed',
    );

    foreach ( $plugin_options as $option ) {
        delete_option( $option );
    }

    // Remove transients/cache data
    $transients = array(
        'slymetrics_cache',
        'slymetrics_heavy_cache',
        'slymetrics_static_cache',
        'slymetrics_checksum_cache',
        'slymetrics_media_count',
    );

    foreach ( $transients as $transient ) {
        delete_transient( $transient );
    }

    // Remove all rate limiting transients
    // We need to use a more comprehensive approach since these are dynamic
    global $wpdb;
    
    // Remove rate limiting transients (these have MD5 hashes in the name)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for uninstall cleanup
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_slymetrics_rate_limit_%'
        )
    );

    // Remove transient timeout entries as well
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for uninstall cleanup
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_slymetrics_rate_limit_%'
        )
    );

    // Clean up any other transients that might have been created
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for uninstall cleanup
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_slymetrics_%',
            '_transient_timeout_slymetrics_%'
        )
    );

    // Flush rewrite rules to clean up custom endpoints
    flush_rewrite_rules();
}

// Execute cleanup
slymetrics_uninstall_cleanup();