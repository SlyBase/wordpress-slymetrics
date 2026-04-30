<?php

namespace SlyMetrics\Admin;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Auth\TokenManager;

/**
 * Admin-area controller: menu registration, settings handling, asset loading.
 *
 * @package SlyMetrics\Admin
 */
class Controller {

    /**
     * Register the options sub-page.
     */
    public static function add_menu(): void {
        add_options_page(
            __( 'SlyMetrics Settings', 'slymetrics' ),
            __( 'SlyMetrics', 'slymetrics' ),
            'manage_options',
            'slymetrics',
            array( Page::class, 'render' )
        );
    }

    /**
     * Add a "Settings" link to the plugin's row on the Plugins screen.
     *
     * @param array $links Existing action links.
     * @return array
     */
    public static function add_plugin_action_links( array $links ): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'options-general.php?page=slymetrics' ),
            __( 'Settings', 'slymetrics' )
        );

        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * Handle settings form submissions on admin_init.
     */
    public static function admin_init(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below
        if ( ! isset( $_POST['slymetrics_action'] ) ) {
            return;
        }

        if ( ! check_admin_referer( 'slymetrics_settings' ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['slymetrics_action'] ) );

        if ( 'regenerate_tokens' !== $action ) {
            return;
        }

        // API key can always be regenerated
        TokenManager::set_encrypted( 'slymetrics_api_key', TokenManager::generate() );

        if ( ! TokenManager::is_env_key() ) {
            TokenManager::set_encrypted( 'slymetrics_auth_token', TokenManager::generate() );
            add_settings_error( 'slymetrics_messages', 'tokens_regenerated', __( 'Bearer Token and API Key successfully regenerated!', 'slymetrics' ), 'updated' );
        } else {
            add_settings_error( 'slymetrics_messages', 'api_key_regenerated', __( 'API Key successfully regenerated! Bearer Token is managed via environment variable.', 'slymetrics' ), 'updated' );
        }
    }

    /**
     * Enqueue styles and scripts for the SlyMetrics settings page only.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public static function enqueue_scripts( string $hook_suffix ): void {
        if ( 'settings_page_slymetrics' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style( 'wp-admin' );
        wp_add_inline_style( 'wp-admin', self::get_admin_css() );

        wp_enqueue_script( 'jquery' );
        wp_add_inline_script( 'jquery', self::get_admin_js() );
    }

    // -----------------------------------------------------------------------
    // Private asset builders
    // -----------------------------------------------------------------------

    /**
     * Return inline CSS for the settings page.
     *
     * @return string
     */
    private static function get_admin_css(): string {
        return '
            .slymetrics-admin-wrap { max-width: none !important; margin-right: 20px; }
            .slymetrics-card {
                background: #fff !important; border: 1px solid #ccd0d4 !important;
                padding: 20px !important; margin: 20px 0 !important;
                width: calc(100% - 40px) !important; max-width: none !important; box-sizing: border-box !important;
            }
            .slymetrics-code-block {
                position: relative; background: #f6f7f7; border: 1px solid #ddd; border-radius: 4px; margin: 15px 0;
            }
            .slymetrics-code-block pre {
                background: #f6f7f7; padding: 15px; margin: 0; border: none; border-radius: 4px;
                font-family: Monaco, Consolas, "Lucida Console", monospace; white-space: pre-wrap;
                word-wrap: break-word; max-width: none; overflow-x: auto;
            }
            .slymetrics-copy-btn {
                position: absolute; top: 10px; right: 10px; background: #0073aa; color: white;
                border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 12px; z-index: 10;
            }
            .slymetrics-copy-btn:hover { background: #005a87; }
            .slymetrics-auth-token-display {
                font-family: Monaco, Consolas, "Lucida Console", monospace; background: #f6f7f7;
                padding: 10px; border: 1px solid #ddd; border-radius: 4px; word-break: break-all;
            }
        ';
    }

    /**
     * Return inline JavaScript for the copy-to-clipboard functionality.
     *
     * @return string
     */
    private static function get_admin_js(): string {
        $copied_text = esc_js( __( 'Kopiert!', 'slymetrics' ) );

        return '
            function copyToClipboard(elementId) {
                var element = document.getElementById(elementId);
                if (!element) return;
                var text = (element.tagName === "INPUT" || element.tagName === "TEXTAREA")
                    ? element.value
                    : (element.textContent || element.innerText || "");
                text = text.trim();
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text)
                        .then(function() { showCopyFeedback(element); })
                        .catch(function() { fallbackCopy(text, element); });
                } else {
                    fallbackCopy(text, element);
                }
            }

            function fallbackCopy(text, element) {
                var textarea = document.createElement("textarea");
                textarea.value = text;
                textarea.style.position = "fixed";
                textarea.style.opacity = "0";
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                try { document.execCommand("copy"); showCopyFeedback(element); } catch (err) {}
                document.body.removeChild(textarea);
            }

            function showCopyFeedback(element) {
                var button = element.parentNode.querySelector(".slymetrics-copy-btn");
                if (button) {
                    var originalText = button.textContent;
                    button.textContent = "' . $copied_text . '";
                    button.style.backgroundColor = "#00a32a";
                    setTimeout(function() {
                        button.textContent = originalText;
                        button.style.backgroundColor = "";
                    }, 2000);
                }
            }
        ';
    }
}
