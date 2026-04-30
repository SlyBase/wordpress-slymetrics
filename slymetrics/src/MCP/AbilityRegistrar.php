<?php

namespace SlyMetrics\MCP;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\MCP\Ability\GetPluginsAbility;
use SlyMetrics\MCP\Ability\GetPostsAbility;
use SlyMetrics\MCP\Ability\GetSiteHealthAbility;
use SlyMetrics\MCP\Ability\GetSummaryAbility;
use SlyMetrics\MCP\Ability\GetUsersAbility;

/**
 * Registers WordPress Abilities for the SlyMetrics MCP server.
 *
 * This class wires up:
 *   1. Five metric abilities via the WordPress Abilities API (WP 6.9+).
 *   2. A dedicated "slymetrics-mcp-server" via the MCP Adapter plugin hook,
 *      if the MCP Adapter plugin is active on the site.
 *
 * Gracefully no-ops on WordPress < 6.9 (no wp_register_ability function).
 *
 * Exposed MCP tools (prefixed metrics/):
 *   metrics/get-summary      – full metrics snapshot (no params)
 *   metrics/get-users        – user counts by role (no params)
 *   metrics/get-posts        – post counts by status (param: post_type)
 *   metrics/get-plugins      – plugin + theme counts (no params)
 *   metrics/get-site-health  – WP/PHP/DB diagnostics (no params)
 *
 * All abilities require the manage_options capability.
 *
 * @package SlyMetrics\MCP
 */
class AbilityRegistrar {

    /** MCP server identifier used for WP-CLI and REST routing. */
    const SERVER_ID      = 'slymetrics-mcp-server';

    /** REST API namespace for the custom MCP server endpoint. */
    const REST_NAMESPACE = 'slymetrics-mcp-server';

    /** REST API route suffix for the custom MCP server. */
    const REST_ROUTE     = 'mcp';

    /**
     * Register all WordPress hooks. Called once from Plugin::init().
     *
     * No-ops silently if wp_register_ability() is not available (WP < 6.9).
     */
    public static function init(): void {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        add_action( 'init',             array( self::class, 'register_abilities' ), 20 );
        add_action( 'mcp_adapter_init', array( self::class, 'register_mcp_server' ) );
    }

    /**
     * Register all five metric abilities with the WordPress Abilities API.
     * Called on the 'init' action at priority 20.
     */
    public static function register_abilities(): void {
        $definitions = array(
            GetSummaryAbility::definition(),
            GetUsersAbility::definition(),
            GetPostsAbility::definition(),
            GetPluginsAbility::definition(),
            GetSiteHealthAbility::definition(),
        );

        foreach ( $definitions as $definition ) {
            wp_register_ability( $definition['name'], $definition );
        }
    }

    /**
     * Create the dedicated slymetrics-mcp-server custom MCP server.
     *
     * Called on 'mcp_adapter_init' – only fires when the WordPress MCP Adapter
     * plugin is active. Exits silently if the McpAdapter class is unavailable.
     *
     * The server is accessible at:
     *   wp-json/slymetrics-mcp-server/mcp
     */
    public static function register_mcp_server(): void {
        if ( ! class_exists( \WP\MCP\Core\McpAdapter::class ) ) {
            return;
        }

        $adapter = \WP\MCP\Core\McpAdapter::instance();
        $adapter->create_server(
            self::SERVER_ID,
            self::REST_NAMESPACE,
            self::REST_ROUTE,
            'SlyMetrics MCP Server',
            'Exposes WordPress site metrics as MCP tools for AI agents. Provides user counts, post/page counts, plugin status, and site health diagnostics.',
            'v1.5.0',
            array( \WP\MCP\Transport\HttpTransport::class ),
            \WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
            \WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
            array(
                'metrics/get-summary',
                'metrics/get-users',
                'metrics/get-posts',
                'metrics/get-plugins',
                'metrics/get-site-health',
            )
        );
    }
}
