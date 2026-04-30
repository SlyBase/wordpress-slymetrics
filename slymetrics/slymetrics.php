<?php
/**
 * Plugin Name: SlyMetrics - Metrics Exporter for Prometheus
 * Plugin URI: https://github.com/slybase/wordpress-slymetrics
 * Description: Export comprehensive WordPress metrics in Prometheus format for monitoring and observability.
 * Version: 1.5.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Timon Först
 * Author URI: https://slybase.com
 * Update URI: https://github.com/slybase/wordpress-slymetrics
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: slymetrics
 * Domain Path: /languages
 *
 * @package SlyMetrics
 * @author Timon Först
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
defined( 'SLYMET_PLUGIN_FILE' ) || define( 'SLYMET_PLUGIN_FILE', __FILE__ );
defined( 'SLYMET_PLUGIN_DIR' )  || define( 'SLYMET_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
defined( 'SLYMET_PLUGIN_URL' )  || define( 'SLYMET_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// Load all namespaced classes from src/
// ---------------------------------------------------------------------------
$slymetrics_src_files = array(
    'Util/Logger.php',
    'Util/SizeConverter.php',
    'Util/Formatter.php',
    'Util/IpDetector.php',
    'Auth/TokenManager.php',
    'Auth/Guard.php',
    'Health/Checker.php',
    'Metrics/Builder/UserMetrics.php',
    'Metrics/Builder/PostMetrics.php',
    'Metrics/Builder/PluginMetrics.php',
    'Metrics/Builder/ContentMetrics.php',
    'Metrics/Builder/HeavyMetrics.php',
    'Metrics/Builder/StaticMetrics.php',
    'Metrics/Cache.php',
    'Endpoint/Router.php',
    'Endpoint/RestHandler.php',
    'Admin/Page.php',
    'Admin/Controller.php',
    'MCP/Ability/GetSummaryAbility.php',
    'MCP/Ability/GetUsersAbility.php',
    'MCP/Ability/GetPostsAbility.php',
    'MCP/Ability/GetPluginsAbility.php',
    'MCP/Ability/GetSiteHealthAbility.php',
    'MCP/Ability/GetContentAbility.php',
    'MCP/Ability/GetStorageAbility.php',
    'MCP/Ability/GetHealthChecksAbility.php',
    'MCP/AbilityRegistrar.php',
    'Plugin.php',
);

foreach ( $slymetrics_src_files as $slymetrics_file ) {
    require_once SLYMET_PLUGIN_DIR . 'src/' . $slymetrics_file;
}

unset( $slymetrics_src_files, $slymetrics_file );

// ---------------------------------------------------------------------------
// Backward-compatible SlyMetrics_Plugin class
//
// Delegates every public/private method call to the matching namespaced class
// so that existing tests and any external code referencing SlyMetrics_Plugin
// continue to work without modification.
// ---------------------------------------------------------------------------
if ( ! class_exists( 'SlyMetrics_Plugin' ) ) {

    /**
     * Backward-compatible facade.
     *
     * All public methods delegate to the appropriate namespaced class so that
     * existing tests and third-party code continue to work unchanged.
     */
    class SlyMetrics_Plugin {

        // Cache constants – kept for backward compatibility
        const CACHE_TTL        = \SlyMetrics\Metrics\Cache::TTL;
        const CACHE_KEY        = \SlyMetrics\Metrics\Cache::KEY;
        const CACHE_KEY_HEAVY  = \SlyMetrics\Metrics\Cache::KEY_HEAVY;
        const CACHE_TTL_HEAVY  = \SlyMetrics\Metrics\Cache::TTL_HEAVY;
        const CACHE_KEY_STATIC = \SlyMetrics\Metrics\Cache::KEY_STATIC;
        const CACHE_TTL_STATIC = \SlyMetrics\Metrics\Cache::TTL_STATIC;

        // -----------------------------------------------------------------------
        // Public methods (used by WordPress hooks and tests)
        // -----------------------------------------------------------------------

        public static function init() {
            \SlyMetrics\Plugin::init();
        }

        public static function check_auth( $request ) {
            return \SlyMetrics\Auth\Guard::check( $request );
        }

        public static function rest_pre_serve_request( $served, $result, $request, $server ) {
            return \SlyMetrics\Endpoint\RestHandler::pre_serve( $served, $result, $request, $server );
        }

        public static function add_plugin_action_links( array $links ) {
            return \SlyMetrics\Admin\Controller::add_plugin_action_links( $links );
        }

        public static function add_query_vars( array $vars ) {
            return \SlyMetrics\Endpoint\Router::add_query_vars( $vars );
        }

        // -----------------------------------------------------------------------
        // Private methods accessed via reflection in tests
        // -----------------------------------------------------------------------

        private static function format_metric( string $metric_name, string $site_name, array $labels = [], $value = 1 ): string {
            return \SlyMetrics\Util\Formatter::metric( $metric_name, $site_name, $labels, $value );
        }

        private static function escape_label_value( string $value ): string {
            return \SlyMetrics\Util\Formatter::escape_label_value( $value );
        }

        private static function convert_to_bytes( string $size ): float {
            return \SlyMetrics\Util\SizeConverter::to_bytes( $size );
        }

        private static function get_client_ip(): string {
            return \SlyMetrics\Util\IpDetector::get_client_ip();
        }
    }
}

SlyMetrics_Plugin::init();
