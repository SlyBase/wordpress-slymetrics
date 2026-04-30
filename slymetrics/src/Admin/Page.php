<?php

namespace SlyMetrics\Admin;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Auth\TokenManager;

/**
 * Renders the SlyMetrics settings page in wp-admin.
 *
 * @package SlyMetrics\Admin
 */
class Page {

    /**
     * Output the full settings page HTML.
     * Registered as the callback for add_options_page().
     */
    public static function render(): void {
        $auth_settings = TokenManager::get_auth_settings();
        $endpoint_url  = home_url( '/wp-json/slymetrics/v1/metrics' );
        $is_env_key    = TokenManager::is_env_key();
        $copy_text     = __( 'Copy', 'slymetrics' );
        ?>
        <div class="wrap slymetrics-admin-wrap">
            <h1><?php esc_html_e( 'SlyMetrics Settings', 'slymetrics' ); ?></h1>

            <?php settings_errors( 'slymetrics_messages' ); ?>

            <div class="slymetrics-card">
                <h2><?php esc_html_e( 'Endpoint Information', 'slymetrics' ); ?></h2>
                <p><strong><?php esc_html_e( 'Primary Metrics Endpoint (Clean URL):', 'slymetrics' ); ?></strong> <code><?php echo esc_url( home_url( '/slymetrics/metrics' ) ); ?></code></p>
                <p><em><?php esc_html_e( 'Recommended endpoint with clean URL structure. Works out-of-the-box with WordPress permalinks enabled.', 'slymetrics' ); ?></em></p>

                <h3><?php esc_html_e( 'Alternative Endpoints (Fallback)', 'slymetrics' ); ?></h3>
                <p><?php esc_html_e( 'If the primary endpoint is not working, use these alternative URLs:', 'slymetrics' ); ?></p>
                <ul>
                    <li><strong><?php esc_html_e( 'REST API Endpoint:', 'slymetrics' ); ?></strong> <code><?php echo esc_url( $endpoint_url ); ?></code></li>
                    <li><strong><?php esc_html_e( 'REST API Fallback:', 'slymetrics' ); ?></strong> <code><?php echo esc_url( home_url( '/index.php?rest_route=/slymetrics/v1/metrics' ) ); ?></code> <em><?php esc_html_e( '(always works)', 'slymetrics' ); ?></em></li>
                    <li><strong><?php esc_html_e( 'Query Parameter:', 'slymetrics' ); ?></strong> <code><?php echo esc_url( home_url( '/?slymetrics=1' ) ); ?></code> <em><?php esc_html_e( '(always works)', 'slymetrics' ); ?></em></li>
                </ul>

                <p><em><?php esc_html_e( 'All endpoints are protected by authentication and return the same metrics data in Prometheus format.', 'slymetrics' ); ?></em></p>
                <?php if ( $is_env_key ) : ?>
                    <p><strong>🔐 <?php esc_html_e( 'Security:', 'slymetrics' ); ?></strong> <em><?php
                        printf(
                            /* translators: %s: environment variable name */
                            esc_html__( 'Encryption key is loaded from environment variable %s for enhanced security.', 'slymetrics' ),
                            '<code>SLYMETRICS_ENCRYPTION_KEY</code>'
                        );
                    ?></em></p>
                <?php endif; ?>
            </div>

            <div class="slymetrics-card">
                <h2><?php esc_html_e( 'Authentication Tokens', 'slymetrics' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Bearer Token', 'slymetrics' ); ?></th>
                        <td>
                            <div class="token-field">
                                <input type="text" class="regular-text" id="bearer-token" value="<?php echo esc_attr( $auth_settings['bearer_token'] ); ?>" readonly onclick="this.select();" />
                                <button type="button" class="button slymetrics-copy-btn" onclick="copyToClipboard('bearer-token')"><?php echo esc_html( $copy_text ); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e( 'For Authorization header:', 'slymetrics' ); ?> <code>Authorization: Bearer TOKEN</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'API Key', 'slymetrics' ); ?></th>
                        <td>
                            <div class="token-field">
                                <input type="text" class="regular-text" id="api-key" value="<?php echo esc_attr( $auth_settings['api_key'] ); ?>" readonly onclick="this.select();" />
                                <button type="button" class="button slymetrics-copy-btn" onclick="copyToClipboard('api-key')"><?php echo esc_html( $copy_text ); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e( 'As URL parameter:', 'slymetrics' ); ?> <code>?api_key=KEY</code></p>
                        </td>
                    </tr>
                </table>

                <?php if ( $is_env_key ) : ?>
                    <div class="notice notice-info inline">
                        <p><strong><?php esc_html_e( 'Bearer Token from Environment:', 'slymetrics' ); ?></strong> <?php esc_html_e( 'The Bearer Token is managed via environment variable and cannot be regenerated through the web interface. API Key can still be regenerated below.', 'slymetrics' ); ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php wp_nonce_field( 'slymetrics_settings' ); ?>
                    <input type="hidden" name="slymetrics_action" value="regenerate_tokens" />
                    <p class="submit">
                        <?php if ( $is_env_key ) : ?>
                            <input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Regenerate API Key', 'slymetrics' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure? Existing API Key configurations will need to be updated.', 'slymetrics' ) ); ?>');" />
                        <?php else : ?>
                            <input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Regenerate Tokens', 'slymetrics' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure? Existing configurations will need to be updated.', 'slymetrics' ) ); ?>');" />
                        <?php endif; ?>
                    </p>
                </form>
            </div>

            <div class="slymetrics-card">
                <h2><?php esc_html_e( 'Authentication Methods', 'slymetrics' ); ?></h2>
                <ul>
                    <li><strong><?php esc_html_e( 'WordPress Admin:', 'slymetrics' ); ?></strong> <?php esc_html_e( 'Logged-in administrators have automatic access', 'slymetrics' ); ?></li>
                    <li><strong><?php esc_html_e( 'Bearer Token:', 'slymetrics' ); ?></strong> <?php esc_html_e( 'Recommended for Prometheus and other monitoring tools (encrypted storage)', 'slymetrics' ); ?></li>
                    <li><strong><?php esc_html_e( 'API Key:', 'slymetrics' ); ?></strong> <?php esc_html_e( 'Simple for testing and URL-based access (encrypted storage)', 'slymetrics' ); ?></li>
                </ul>

                <h3><?php esc_html_e( 'Security Configuration', 'slymetrics' ); ?></h3>
                <ul>
                    <?php if ( $is_env_key ) : ?>
                        <li><strong>🔐 <?php esc_html_e( 'Environment Key:', 'slymetrics' ); ?></strong> <?php
                            printf(
                                /* translators: %s: environment variable name */
                                esc_html__( 'Using encryption key from %s environment variable', 'slymetrics' ),
                                '<code>SLYMETRICS_ENCRYPTION_KEY</code>'
                            );
                        ?></li>
                        <li><strong><?php esc_html_e( 'Bearer Token:', 'slymetrics' ); ?></strong> <?php
                            printf(
                                /* translators: %s: environment variable name */
                                esc_html__( 'Can be provided via %s environment variable', 'slymetrics' ),
                                '<code>SLYMETRICS_BEARER_TOKEN</code>'
                            );
                        ?></li>
                        <li><strong><?php esc_html_e( 'Enhanced Security:', 'slymetrics' ); ?></strong> <?php esc_html_e( 'Environment variables are not stored in database and cannot be accessed via web interface', 'slymetrics' ); ?></li>
                    <?php else : ?>
                        <li><strong><?php esc_html_e( 'Database Key:', 'slymetrics' ); ?></strong> <?php esc_html_e( 'Encryption key is auto-generated and stored in WordPress database', 'slymetrics' ); ?></li>
                        <li><strong><?php esc_html_e( 'Enhanced Security Option:', 'slymetrics' ); ?></strong> <?php
                            printf(
                                /* translators: %1$s: first env var name, %2$s: second env var name */
                                esc_html__( 'Set %1$s and %2$s environment variables for better security', 'slymetrics' ),
                                '<code>SLYMETRICS_ENCRYPTION_KEY</code>',
                                '<code>SLYMETRICS_BEARER_TOKEN</code>'
                            );
                        ?></li>
                    <?php endif; ?>
                </ul>

                <h3><?php esc_html_e( 'Environment Variable Setup', 'slymetrics' ); ?></h3>
                <p><?php esc_html_e( 'For production environments, use environment variables for enhanced security:', 'slymetrics' ); ?></p>

                <h4><?php esc_html_e( 'Docker', 'slymetrics' ); ?></h4>
                <div class="slymetrics-code-block">
                    <code id="docker-example">docker run -e SLYMETRICS_ENCRYPTION_KEY="$(openssl rand -base64 32)" -e SLYMETRICS_BEARER_TOKEN="$(openssl rand -hex 32)" your-wordpress-image</code>
                    <button type="button" class="button slymetrics-copy-btn" onclick="copyToClipboard('docker-example')"><?php echo esc_html( $copy_text ); ?></button>
                </div>

                <h4><?php esc_html_e( 'Kubernetes', 'slymetrics' ); ?></h4>
                <div class="slymetrics-code-block">
                    <pre id="k8s-example">env:
  - name: SLYMETRICS_ENCRYPTION_KEY
    valueFrom:
      secretKeyRef:
        name: wordpress-secrets
        key: slymetrics-encryption-key
  - name: SLYMETRICS_BEARER_TOKEN
    valueFrom:
      secretKeyRef:
        name: wordpress-secrets
        key: slymetrics-bearer-token</pre>
                    <button type="button" class="button slymetrics-copy-btn" onclick="copyToClipboard('k8s-example')"><?php echo esc_html( $copy_text ); ?></button>
                </div>

                <p><strong><?php esc_html_e( 'Environment Variables:', 'slymetrics' ); ?></strong></p>
                <ul>
                    <li><?php printf( /* translators: %s: env var */ esc_html__( '%s - Base64 encoded encryption key for API keys', 'slymetrics' ), '<code>SLYMETRICS_ENCRYPTION_KEY</code>' ); ?></li>
                    <li><?php printf( /* translators: %s: env var */ esc_html__( '%s - Bearer token for SlyMetrics authentication (plain text)', 'slymetrics' ), '<code>SLYMETRICS_BEARER_TOKEN</code>' ); ?></li>
                </ul>

                <p>📖 <strong><?php esc_html_e( 'More Examples:', 'slymetrics' ); ?></strong> <a href="https://github.com/slybase/wordpress-slymetrics#security-features" target="_blank"><?php esc_html_e( 'See GitHub Documentation', 'slymetrics' ); ?></a></p>
            </div>

            <div class="slymetrics-card">
                <h2><?php esc_html_e( 'Usage Examples', 'slymetrics' ); ?></h2>

                <h3><?php esc_html_e( 'Primary Endpoint (Clean URL)', 'slymetrics' ); ?></h3>
                <h4><?php esc_html_e( 'cURL with Bearer Token', 'slymetrics' ); ?></h4>
                <div class="slymetrics-code-block">
                    <code id="curl-primary">curl -H "Authorization: Bearer <?php echo esc_attr( $auth_settings['bearer_token'] ); ?>" "<?php echo esc_url( home_url( '/slymetrics/metrics' ) ); ?>"</code>
                    <button type="button" class="button slymetrics-copy-btn" onclick="copyToClipboard('curl-primary')"><?php echo esc_html( $copy_text ); ?></button>
                </div>

                <h4><?php esc_html_e( 'cURL with API Key', 'slymetrics' ); ?></h4>
                <div class="slymetrics-code-block">
                    <code id="curl-apikey-primary">curl "<?php echo esc_url( home_url( '/slymetrics/metrics' ) ); ?>?api_key=<?php echo esc_attr( $auth_settings['api_key'] ); ?>"</code>
                    <button type="button" class="button slymetrics-copy-btn" onclick="copyToClipboard('curl-apikey-primary')"><?php echo esc_html( $copy_text ); ?></button>
                </div>

                <h3><?php esc_html_e( 'Fallback Endpoints', 'slymetrics' ); ?></h3>
                <h4><?php esc_html_e( 'REST API Endpoint', 'slymetrics' ); ?></h4>
                <div class="slymetrics-code-block">
                    <code id="curl-rest">curl -H "Authorization: Bearer <?php echo esc_attr( $auth_settings['bearer_token'] ); ?>" "<?php echo esc_url( $endpoint_url ); ?>"</code>
                    <button type="button" class="button slymetrics-copy-btn" onclick="copyToClipboard('curl-rest')"><?php echo esc_html( $copy_text ); ?></button>
                </div>

                <h4><?php esc_html_e( 'Universal Fallback (Always Works)', 'slymetrics' ); ?></h4>
                <div class="slymetrics-code-block">
                    <code id="curl-fallback">curl -H "Authorization: Bearer <?php echo esc_attr( $auth_settings['bearer_token'] ); ?>" "<?php echo esc_url( home_url( '/?slymetrics=1' ) ); ?>"</code>
                    <button type="button" class="button slymetrics-copy-btn" onclick="copyToClipboard('curl-fallback')"><?php echo esc_html( $copy_text ); ?></button>
                </div>

                <h3><?php esc_html_e( 'Prometheus Configuration', 'slymetrics' ); ?></h3>
                <h4><?php esc_html_e( 'Primary Configuration', 'slymetrics' ); ?></h4>
                <div class="slymetrics-code-block">
                    <pre id="prometheus-config"># prometheus.yml
scrape_configs:
  - job_name: 'wordpress'
    scheme: https
    static_configs:
      - targets: ['<?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>']
    metrics_path: '/slymetrics/metrics'
    authorization:
      type: Bearer
      credentials: '<?php echo esc_attr( $auth_settings['bearer_token'] ); ?>'</pre>
                    <button type="button" class="button slymetrics-copy-btn" onclick="copyToClipboard('prometheus-config')"><?php echo esc_html( $copy_text ); ?></button>
                </div>

                <h4><?php esc_html_e( 'REST API Fallback Configuration', 'slymetrics' ); ?></h4>
                <div class="slymetrics-code-block">
                    <pre id="prometheus-fallback"># prometheus.yml (REST API fallback)
scrape_configs:
  - job_name: 'wordpress'
    scheme: https
    static_configs:
      - targets: ['<?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>']
    metrics_path: '/wp-json/slymetrics/v1/metrics'
    authorization:
      type: Bearer
      credentials: '<?php echo esc_attr( $auth_settings['bearer_token'] ); ?>'</pre>
                    <button type="button" class="button slymetrics-copy-btn" onclick="copyToClipboard('prometheus-fallback')"><?php echo esc_html( $copy_text ); ?></button>
                </div>

                <h4><?php esc_html_e( 'Universal Fallback Configuration (Always Works)', 'slymetrics' ); ?></h4>
                <div class="slymetrics-code-block">
                    <pre id="prometheus-alt"># prometheus.yml (universal fallback)
scrape_configs:
  - job_name: 'wordpress'
    scheme: https
    static_configs:
      - targets: ['<?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>']
    metrics_path: '/'
    params:
      slymetrics: ['1']
    authorization:
      type: Bearer
      credentials: '<?php echo esc_attr( $auth_settings['bearer_token'] ); ?>'</pre>
                    <button type="button" class="button slymetrics-copy-btn" onclick="copyToClipboard('prometheus-alt')"><?php echo esc_html( $copy_text ); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}
