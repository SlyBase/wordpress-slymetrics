<?php

namespace SlyMetrics\Util;

defined( 'ABSPATH' ) || exit;

/**
 * Detects a pending WordPress core update.
 *
 * Reads the `update_core` site transient directly. get_core_updates() lives in
 * wp-admin/includes/update.php and does not exist on front-end requests such as
 * the metrics endpoint.
 *
 * When the transient is empty (wp-cron has not run recently), the check forces
 * a fresh `wp_version_check()` call so the result reflects the current state.
 *
 * @package SlyMetrics\Util
 */
class CoreUpdate {

    /**
     * @return bool True when WordPress offers a core upgrade.
     */
    public static function is_available(): bool {
        if ( wp_installing() ) {
            return false;
        }

        $transient = get_site_transient( 'update_core' );

        // Force a fresh check when the transient is empty (wp-cron has not
        // run recently). wp_version_check() lives in wp-includes/update.php,
        // which is always loaded on front-end requests.
        if ( ! is_object( $transient ) ) {
            if ( function_exists( 'wp_version_check' ) ) {
                wp_version_check( array(), true );
                $transient = get_site_transient( 'update_core' );
            }
            if ( ! is_object( $transient ) ) {
                return false;
            }
        }

        if ( empty( $transient->updates ) || ! is_array( $transient->updates ) ) {
            return false;
        }

        // Same rule as get_core_updates(): the first offer decides.
        $latest = $transient->updates[0];

        return isset( $latest->response ) && 'upgrade' === $latest->response;
    }
}
