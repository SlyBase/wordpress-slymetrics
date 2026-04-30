<?php

namespace SlyMetrics\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Manages encrypted storage and retrieval of authentication tokens.
 *
 * Supports two modes:
 *   1. Environment-variable mode – encryption key (and optionally the Bearer
 *      token) come from SLYMETRICS_ENCRYPTION_KEY / SLYMETRICS_BEARER_TOKEN.
 *   2. Database mode – key is auto-generated and stored as base64 in wp_options.
 *
 * @package SlyMetrics\Auth
 */
class TokenManager {

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Return a token by option name, respecting environment variable overrides.
     *
     * @param string $option_name 'slymetrics_auth_token' or 'slymetrics_api_key'.
     * @return string Token or empty string if not configured.
     */
    public static function get_auth_token( string $option_name ): string {
        if ( self::is_env_key() && $option_name === 'slymetrics_auth_token' ) {
            $env_token = getenv( 'SLYMETRICS_BEARER_TOKEN' );
            if ( $env_token !== false && ! empty( $env_token ) ) {
                return $env_token;
            }
        }

        return self::get_decrypted( $option_name );
    }

    /**
     * Return both tokens as an array (for admin display).
     *
     * @return array{bearer_token: string, api_key: string}
     */
    public static function get_auth_settings(): array {
        return array(
            'bearer_token' => self::get_auth_token( 'slymetrics_auth_token' ),
            'api_key'      => self::get_auth_token( 'slymetrics_api_key' ),
        );
    }

    /**
     * Generate a cryptographically secure random token (64-char hex string).
     *
     * @return string
     */
    public static function generate(): string {
        return bin2hex( random_bytes( 32 ) );
    }

    /**
     * Create missing auth tokens (called on activation and first boot).
     */
    public static function ensure_tokens(): void {
        self::get_encryption_key(); // triggers key creation if absent
        if ( ! self::get_decrypted( 'slymetrics_auth_token' ) ) {
            self::set_encrypted( 'slymetrics_auth_token', self::generate() );
        }
        if ( ! self::get_decrypted( 'slymetrics_api_key' ) ) {
            self::set_encrypted( 'slymetrics_api_key', self::generate() );
        }
    }

    /**
     * Store an encrypted option value.
     *
     * @param string $option_name
     * @param string $value
     */
    public static function set_encrypted( string $option_name, string $value ): void {
        update_option( $option_name, self::encrypt( $value ) );
    }

    /**
     * Whether the encryption key is supplied via environment variable.
     *
     * @return bool
     */
    public static function is_env_key(): bool {
        $env_key = getenv( 'SLYMETRICS_ENCRYPTION_KEY' );
        return $env_key !== false && ! empty( $env_key );
    }

    /**
     * Migrate old hex-format keys to base64 and regenerate tokens if needed.
     * No-op when the environment key is in use.
     */
    public static function fix_key_if_needed(): void {
        if ( self::is_env_key() ) {
            return;
        }

        $key = get_option( 'slymetrics_encryption_key' );

        if ( $key ) {
            // Old format: 64-char hex string – replace with proper base64 key
            if ( strlen( $key ) === 64 && ctype_xdigit( $key ) ) {
                update_option( 'slymetrics_encryption_key', base64_encode( random_bytes( 32 ) ) );
                if ( get_option( 'slymetrics_auth_token' ) ) {
                    delete_option( 'slymetrics_auth_token' );
                }
                if ( get_option( 'slymetrics_api_key' ) ) {
                    delete_option( 'slymetrics_api_key' );
                }
            }
        } else {
            // No key yet – generate one
            $success = update_option( 'slymetrics_encryption_key', base64_encode( random_bytes( 32 ) ) );
            if ( ! $success && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( 'SlyMetrics: Failed to create encryption key in database' );
            }
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Return the raw 32-byte encryption key (from env or DB).
     *
     * @return string
     */
    private static function get_encryption_key(): string {
        $env_key = getenv( 'SLYMETRICS_ENCRYPTION_KEY' );
        if ( $env_key !== false && ! empty( $env_key ) ) {
            return (string) base64_decode( $env_key );
        }

        $key = get_option( 'slymetrics_encryption_key' );
        if ( ! $key ) {
            $raw_key = random_bytes( 32 );
            update_option( 'slymetrics_encryption_key', base64_encode( $raw_key ) );
            return $raw_key;
        }

        return (string) base64_decode( $key );
    }

    /**
     * Encrypt data with AES-256-CBC (falls back to base64 when OpenSSL is absent).
     *
     * @param string $data
     * @return string
     */
    private static function encrypt( string $data ): string {
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return base64_encode( $data );
        }

        $key       = self::get_encryption_key();
        $iv        = (string) openssl_random_pseudo_bytes( 16 );
        $encrypted = (string) openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv );

        return base64_encode( $iv . $encrypted );
    }

    /**
     * Decrypt data encrypted by {@see encrypt()}.
     *
     * @param string $encrypted_data
     * @return string|false Decrypted string or false on failure.
     */
    private static function decrypt( string $encrypted_data ) {
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return base64_decode( $encrypted_data );
        }

        $data = (string) base64_decode( $encrypted_data );
        if ( strlen( $data ) < 16 ) {
            return false;
        }

        $key       = self::get_encryption_key();
        $iv        = substr( $data, 0, 16 );
        $encrypted = substr( $data, 16 );

        return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
    }

    /**
     * Retrieve and decrypt a stored option.
     *
     * @param string $option_name
     * @return string Decrypted value or empty string on failure.
     */
    private static function get_decrypted( string $option_name ): string {
        $encrypted_value = get_option( $option_name, '' );
        if ( empty( $encrypted_value ) ) {
            return '';
        }

        $decrypted = self::decrypt( $encrypted_value );
        return $decrypted !== false ? (string) $decrypted : '';
    }
}
