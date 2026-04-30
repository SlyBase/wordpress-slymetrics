<?php

namespace SlyMetrics\Metrics\Builder;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Util\Formatter;

/**
 * Builds Prometheus metrics for WordPress users.
 *
 * @package SlyMetrics\Metrics\Builder
 */
class UserMetrics {

    /**
     * @param string $site_name Value for the wordpress_site label.
     * @return string Prometheus exposition lines.
     */
    public static function build( string $site_name ): string {
        $users       = count_users();
        $total_users = isset( $users['total_users'] ) ? (int) $users['total_users'] : 0;
        $avail_roles = isset( $users['avail_roles'] ) && is_array( $users['avail_roles'] )
            ? $users['avail_roles']
            : array();

        $out  = "# HELP wordpress_users_total Number of users per role.\n";
        $out .= "# TYPE wordpress_users_total counter\n";

        foreach ( $avail_roles as $role => $count ) {
            $out .= Formatter::metric( 'wordpress_users_total', $site_name, array( 'role' => $role ), (int) $count );
        }

        $out .= Formatter::metric( 'wordpress_users_total', $site_name, array( 'role' => 'total' ), $total_users );

        return $out;
    }
}
