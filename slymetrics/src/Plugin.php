<?php

namespace SlyMetrics;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Admin\Controller;
use SlyMetrics\Auth\TokenManager;
use SlyMetrics\Endpoint\RestHandler;
use SlyMetrics\Endpoint\Router;
use SlyMetrics\Metrics\Cache;

/**
 * Main plugin class – registers all WordPress hooks and manages the plugin
 * lifecycle (activation, deactivation, first-boot initialization).
 *
 * @package SlyMetrics
 */
class Plugin {

    /**
     * Register all hooks. Called once from the entry-point file.
     */
    public static function init(): void {
        // REST API
        add_action( 'rest_api_init', array( RestHandler::class, 'register_routes' ) );
        add_filter( 'rest_pre_serve_request', array( RestHandler::class, 'pre_serve' ), 10, 4 );

        // Lifecycle
        register_activation_hook( SLYMET_PLUGIN_FILE, array( self::class, 'on_activate' ) );
        register_deactivation_hook( SLYMET_PLUGIN_FILE, array( self::class, 'on_deactivate' ) );

        // Admin
        add_action( 'admin_menu',            array( Controller::class, 'add_menu' ) );
        add_action( 'admin_init',            array( Controller::class, 'admin_init' ) );
        add_action( 'admin_enqueue_scripts', array( Controller::class, 'enqueue_scripts' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( SLYMET_PLUGIN_FILE ), array( Controller::class, 'add_plugin_action_links' ) );

        // URL routing
        add_action( 'init',          array( Router::class, 'add_rewrite_rules' ) );
        add_filter( 'query_vars',    array( Router::class, 'add_query_vars' ) );
        add_action( 'plugins_loaded', array( Router::class, 'early_metrics_check' ), 1 );
        add_action( 'parse_request', array( Router::class, 'handle_metrics_request' ) );
        add_action( 'admin_init',    array( Router::class, 'maybe_flush_rewrite_rules' ) );

        // Bootstrap
        add_action( 'init', array( self::class, 'ensure_initialized' ), 1 );
        add_action( 'init', array( self::class, 'load_textdomain' ) );
    }

    /**
     * Load plugin translations.
     */
    public static function load_textdomain(): void {
        load_plugin_textdomain( 'slymetrics', false, dirname( plugin_basename( SLYMET_PLUGIN_FILE ) ) . '/languages' );
    }

    /**
     * Activation hook: initialize tokens, rewrite rules, and flush.
     */
    public static function on_activate(): void {
        TokenManager::fix_key_if_needed();
        TokenManager::ensure_tokens();
        Router::add_rewrite_rules();
        flush_rewrite_rules();

        update_option( 'slymetrics_initialized', true );
        update_option( 'slymetrics_rewrite_rules_flushed', time() );
    }

    /**
     * Deactivation hook: flush caches and remove rewrite rules.
     */
    public static function on_deactivate(): void {
        Cache::flush();
        delete_option( 'slymetrics_initialized' );
        flush_rewrite_rules();
    }

    /**
     * Ensure the plugin is initialized even on headless/API-only WordPress
     * setups where wp-admin may never be visited.
     */
    public static function ensure_initialized(): void {
        // Use a transient so the check runs at most once per hour
        if ( false !== get_transient( 'slymetrics_init_check' ) ) {
            return;
        }

        $initialized = get_option( 'slymetrics_initialized', false );

        if ( ! $initialized ) {
            TokenManager::fix_key_if_needed();
            TokenManager::ensure_tokens();
            Router::add_rewrite_rules();
            flush_rewrite_rules();
            update_option( 'slymetrics_initialized', true );
            update_option( 'slymetrics_rewrite_rules_flushed', time() );
        } else {
            // Plugin is initialized – verify that our rewrite rules still exist
            // (they can disappear after container restarts with ephemeral databases)
            $rewrite_rules = get_option( 'rewrite_rules' );
            $rules_exist   = false;

            if ( is_array( $rewrite_rules ) ) {
                foreach ( $rewrite_rules as $pattern => $rewrite ) {
                    if ( strpos( $pattern, 'slymetrics' ) !== false ) {
                        $rules_exist = true;
                        break;
                    }
                }
            }

            if ( ! $rules_exist ) {
                Router::add_rewrite_rules();
                flush_rewrite_rules();
                update_option( 'slymetrics_rewrite_rules_flushed', time() );
            }
        }

        set_transient( 'slymetrics_init_check', true, HOUR_IN_SECONDS );
    }
}
