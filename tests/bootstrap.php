<?php
/**
 * Bootstrap file for PHPUnit tests.
 *
 * Sets up a minimal WordPress-like environment so the plugin class can be
 * loaded and Brain\Monkey can intercept WordPress function calls.
 *
 * @package SlyMetrics\Tests
 */

declare(strict_types=1);

// Composer autoload
require_once dirname(__DIR__) . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// Minimal WordPress constants required by the plugin
// ---------------------------------------------------------------------------
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname(__DIR__) . '/tmp-wp/' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
    define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
}
if ( ! defined( 'WPINC' ) ) {
    define( 'WPINC', 'wp-includes' );
}
if ( ! defined( 'DB_NAME' ) ) {
    define( 'DB_NAME', 'wordpress_test' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', false );
}
if ( ! defined( 'WP_DEBUG_LOG' ) ) {
    define( 'WP_DEBUG_LOG', false );
}

// ---------------------------------------------------------------------------
// Stub WP_REST_Request so the class is defined before the plugin loads
// ---------------------------------------------------------------------------
if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        /** @var array<string,mixed> */
        private array $params  = [];
        /** @var array<string,string> */
        private array $headers = [];
        private string $method = 'GET';
        private string $route  = '';

        public function __construct( string $method = 'GET', string $route = '' ) {
            $this->method = $method;
            $this->route  = $route;
        }

        public function get_header( string $name ): ?string {
            return $this->headers[ strtolower( $name ) ] ?? null;
        }

        public function set_header( string $name, string $value ): void {
            $this->headers[ strtolower( $name ) ] = $value;
        }

        public function get_param( string $key ): mixed {
            return $this->params[ $key ] ?? null;
        }

        public function set_param( string $key, mixed $value ): void {
            $this->params[ $key ] = $value;
        }

        public function get_route(): string {
            return $this->route;
        }

        public function get_method(): string {
            return $this->method;
        }
    }
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        private mixed $data;
        private int $status;
        /** @var array<string,string> */
        private array $headers = [];

        public function __construct( mixed $data = null, int $status = 200 ) {
            $this->data   = $data;
            $this->status = $status;
        }

        public function get_data(): mixed {
            return $this->data;
        }

        public function get_status(): int {
            return $this->status;
        }

        public function header( string $key, string $value ): void {
            $this->headers[ $key ] = $value;
        }

        public function get_headers(): array {
            return $this->headers;
        }
    }
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
    class WP_REST_Server {
        const READABLE = 'GET';
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private string $code;
        private string $message;
        /** @var array<string,mixed> */
        private array $data;

        public function __construct( string $code = '', string $message = '', array $data = [] ) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code(): string {
            return $this->code;
        }

        public function get_error_message(): string {
            return $this->message;
        }

        public function get_error_data(): array {
            return $this->data;
        }
    }
}

// ---------------------------------------------------------------------------
// WordPress function stubs required at plugin-load time
// (Brain\Monkey stubs are scoped to individual tests; these are always needed)
// ---------------------------------------------------------------------------
if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( string $file ): string {
        return trailingslashit( dirname( $file ) );
    }
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
    function plugin_dir_url( string $file ): string {
        return 'https://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
    }
}
if ( ! function_exists( 'plugin_basename' ) ) {
    function plugin_basename( string $file ): string {
        return basename( dirname( $file ) ) . '/' . basename( $file );
    }
}
if ( ! function_exists( 'trailingslashit' ) ) {
    function trailingslashit( string $string ): string {
        return rtrim( $string, '/\\' ) . '/';
    }
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
        return true;
    }
}
if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
        return true;
    }
}
if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook( string $file, $callback ): void {}
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
    function register_deactivation_hook( string $file, $callback ): void {}
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( mixed $thing ): bool {
        return $thing instanceof WP_Error;
    }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( string $option, mixed $default = false ): mixed {
        return $default;
    }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( string $option, mixed $value, bool $autoload = true ): bool {
        return true;
    }
}
if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( string $option ): bool {
        return true;
    }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( string $transient ): mixed {
        return false;
    }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( string $transient, mixed $value, int $expiration = 0 ): bool {
        return true;
    }
}
if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( string $transient ): bool {
        return true;
    }
}
if ( ! function_exists( 'get_site_transient' ) ) {
    function get_site_transient( string $transient ): mixed {
        return false;
    }
}
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( string $capability ): bool {
        return false;
    }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( string $show = '', string $filter = 'raw' ): string {
        return match ( $show ) {
            'name'    => 'Test Site',
            'version' => '6.5',
            'charset' => 'UTF-8',
            default   => '',
        };
    }
}
if ( ! function_exists( 'home_url' ) ) {
    function home_url( string $path = '', ?string $scheme = null ): string {
        return 'https://example.com' . $path;
    }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( string $url, int $component = -1 ): mixed {
        return parse_url( $url, $component );
    }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( string $str ): string {
        return trim( $str );
    }
}
if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( mixed $value ): mixed {
        return is_string( $value ) ? stripslashes( $value ) : $value;
    }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( mixed $data, int $options = 0, int $depth = 512 ): string|false {
        return json_encode( $data, $options, $depth );
    }
}
if ( ! function_exists( 'wp_installing' ) ) {
    function wp_installing( ?bool $is_installing = null ): bool {
        return false;
    }
}
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( string $path = '', string $scheme = 'admin' ): string {
        return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
    }
}
if ( ! function_exists( '__' ) ) {
    function __( string $text, string $domain = 'default' ): string {
        return $text;
    }
}
if ( ! function_exists( '_x' ) ) {
    function _x( string $text, string $context, string $domain = 'default' ): string {
        return $text;
    }
}
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( string $text, string $domain = 'default' ): string {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( string $text ): string {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
    function load_plugin_textdomain( string $domain, bool $deprecated = false, string|bool $plugin_rel_path = false ): bool {
        return true;
    }
}
if ( ! function_exists( 'getenv' ) ) {
    // getenv is a native PHP function, so it's always available – no stub needed.
}

// ---------------------------------------------------------------------------
// Load the plugin file (defines SlyMetrics_Plugin class)
// ---------------------------------------------------------------------------
require_once dirname(__DIR__) . '/slymetrics/slymetrics.php';
