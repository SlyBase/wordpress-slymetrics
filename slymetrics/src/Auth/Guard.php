<?php

namespace SlyMetrics\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Authenticates incoming metrics requests.
 *
 * Supports three methods (evaluated in order):
 *   1. Bearer token in Authorization header.
 *   2. api_key query parameter.
 *   3. Logged-in WordPress administrator.
 *
 * @package SlyMetrics\Auth
 */
class Guard {

    /**
     * Verify the request and return true on success or a WP_Error on failure.
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public static function check( \WP_REST_Request $request ) {
        // Option 1: Bearer token
        $auth_header = self::extract_auth_header( $request );
        if ( $auth_header && preg_match( '/Bearer\s+(.+)/', $auth_header, $matches ) ) {
            $token       = trim( $matches[1] );
            $valid_token = TokenManager::get_auth_token( 'slymetrics_auth_token' );
            if ( $valid_token && hash_equals( $valid_token, $token ) ) {
                return true;
            }
        }

        // Option 2: API key query parameter
        $api_key = $request->get_param( 'api_key' );
        if ( $api_key ) {
            $valid_api_key = TokenManager::get_auth_token( 'slymetrics_api_key' );
            if ( $valid_api_key && hash_equals( $valid_api_key, $api_key ) ) {
                return true;
            }
        }

        // Option 3: WordPress administrator
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        return new \WP_Error(
            'rest_forbidden',
            __( 'Authentication required for metrics endpoint.', 'slymetrics' ),
            array( 'status' => 401 )
        );
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Extract the Authorization header value from all available sources.
     *
     * @param \WP_REST_Request|null $request
     * @return string|false Header value or false if not present.
     */
    private static function extract_auth_header( ?\WP_REST_Request $request = null ) {
        $auth_header = false;

        if ( $request ) {
            $auth_header = $request->get_header( 'authorization' );
            if ( ! $auth_header ) {
                $auth_header = $request->get_header( 'HTTP_AUTHORIZATION' );
            }
        }

        if ( ! $auth_header && isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            $auth_header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
        }

        if ( ! $auth_header && function_exists( 'getallheaders' ) ) {
            $headers = getallheaders();
            if ( isset( $headers['Authorization'] ) ) {
                $auth_header = $headers['Authorization'];
            }
        }

        return $auth_header;
    }
}
