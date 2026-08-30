<?php

namespace SlyMetrics\Endpoint;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Auth\Guard;
use SlyMetrics\Metrics\Cache;

/**
 * WordPress REST API handler for the /slymetrics/v1/metrics endpoint.
 *
 * @package SlyMetrics\Endpoint
 */
class RestHandler {

    /**
     * Register the REST route.
     */
    public static function register_routes(): void {
        register_rest_route(
            'slymetrics/v1',
            '/metrics',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( self::class, 'callback' ),
                'permission_callback' => array( Guard::class, 'check' ),
            )
        );
    }

    /**
     * REST callback – returns metrics as a plain-text REST response.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function callback( \WP_REST_Request $request ): \WP_REST_Response {
        $metrics  = Cache::get();
        $response = new \WP_REST_Response( $metrics, 200 );
        $response->header( 'Content-Type', 'text/plain; charset=' . ( get_option( 'blog_charset' ) ?: 'UTF-8' ) );
        return $response;
    }

    /**
     * Intercept the REST framework's final output for our route and send raw
     * plain text instead of JSON-encoded string.
     *
     * @param bool                   $served  Whether the request has already been served.
     * @param \WP_REST_Response|mixed $result  The result to be served.
     * @param \WP_REST_Request        $request Current REST request.
     * @param \WP_REST_Server         $server  Server instance.
     * @return bool True if we served the response; original $served value otherwise.
     */
    public static function pre_serve( bool $served, $result, \WP_REST_Request $request, \WP_REST_Server $server ): bool {
        $route = is_callable( array( $request, 'get_route' ) ) ? $request->get_route() : '';

        if ( $route !== '/slymetrics/v1/metrics' ) {
            return $served;
        }

        // Extract the raw payload
        if ( $result instanceof \WP_REST_Response ) {
            $payload = $result->get_data();
        } elseif ( is_string( $result ) ) {
            $payload = $result;
        } elseif ( is_wp_error( $result ) ) {
            return $served; // let WP handle error responses
        } else {
            $payload = wp_json_encode( $result );
        }

        if ( is_array( $payload ) || is_object( $payload ) ) {
            $payload = wp_json_encode( $payload );
        }

        if ( ! headers_sent() ) {
            header( 'Content-Type: text/plain; charset=' . ( get_option( 'blog_charset' ) ?: 'UTF-8' ) );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw Prometheus exposition format
        echo (string) $payload;

        return true;
    }
}
