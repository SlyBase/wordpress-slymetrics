# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.6.0]
### Features
- Add three new MCP abilities exposing all remaining Prometheus metrics as AI agent tools: `metrics/get-content` (comments, categories, media, tags), `metrics/get-storage` (autoload options, database size, directory sizes), and `metrics/get-health-checks` (site health check summary and individual test results).

## [1.5.0]
### Features
- Expose WordPress metrics via the WordPress MCP Adapter (WP 6.9+): five abilities accessible as MCP tools for AI agents — `metrics/get-summary`, `metrics/get-users`, `metrics/get-posts`, `metrics/get-plugins`, and `metrics/get-site-health`.
- Add dedicated `slymetrics-mcp-server` custom MCP server registered via `mcp_adapter_init` hook when the MCP Adapter plugin is active.
- Add PHPUnit job to the CI pipeline covering PHP 8.1, 8.2, and 8.3.

## [1.4.2]
### Changes
- Refactored monolithic plugin class into focused namespaced classes under `src/` (Util, Auth, Endpoint, Metrics, Health, Admin) for improved maintainability; all existing public interfaces and tests remain unchanged.

### Fixes
- Database names containing hyphens (e.g. `slybase-com`) are now accepted; the character allowlist was too strict and blocked valid MySQL database names.
- Security check for invalid characters in request paths is now scoped to metrics endpoints only; previously it logged errors for every WordPress request (CSS, images, etc.).
- Internal and private IP addresses (e.g. Prometheus scrapers running in the same network or Kubernetes cluster) are now correctly identified; previously they all shared one rate-limit bucket under `unknown`.

## [1.4.1]
### Changes
- Moved GitHub repository to the SlyBase organization.

## [1.4.0]
### Features
- Plugin is now fully translatable using the WordPress translation system.
- Complete German (de_DE) translation included out-of-the-box.
- Added `languages/slymetrics.pot` (template), `slymetrics-de_DE.po`, and compiled `slymetrics-de_DE.mo`.
- All admin UI strings, error messages, and dialogs are now wrapped with `__()` / `esc_html_e()` / `esc_attr_e()`.

### Changes
- Verified compatibility with WordPress 7.0.
- Added `Domain Path: /languages` to plugin header for proper translation loading.

## [1.3.7]
### Fixes
- Fixed label encoding to properly support umlauts and special characters (e.g., ö, ä, ü, é, etc.).

## [1.3.6]
### Features
- Plugin now auto-initializes without requiring admin panel access (headless WordPress support).
- Automatic encryption key generation and token creation on first metrics request.
- Transient-based initialization check to avoid unnecessary database queries.
- Auth tokens are regenerated consistently across all WordPress pods in scaled deployments.

### Fixes
- Fixed Prometheus label values to properly decode HTML entities (e.g., &#039; → ').

## [1.3.5]
### Changes
- Added scheme parameter to Prometheus sample scraper configuration.

## [1.3.4]
### Features
- Added custom plugin icon.

## [1.3.3]
### Fixes
- Fixed Host header handling for Kubernetes ServiceMonitor compatibility.
- Metrics endpoint now works correctly when accessed via localhost or Pod IP addresses.

## [1.3.2]
### Features
- Added diagnostics.php for encryption key troubleshooting.
- Added comprehensive plugin data removal during uninstallation.
- Improved environment variable handling for `SLYMETRICS_ENCRYPTION_KEY`.

### Fixes
- Fixed encryption key creation during plugin installation.
- Proper base64 encoding for database-stored encryption keys (was incorrectly using hex format).
- Automatic detection and migration of old hex-format encryption keys.
- Auth tokens are regenerated when encryption key format is fixed.

## [1.3.1]
### Features
- Complete database cleanup when plugin is uninstalled (removes all options, transients, cached data, and custom endpoints).

## [1.3.0]
### Features
- Migrated from inline scripts/styles to proper `wp_enqueue_script/style` with `wp_add_inline_*`.
- Added unique `slymetrics-` prefix to all CSS classes to prevent conflicts.
- Restored admin interface copy-to-clipboard functionality with proper CSS targeting.

### Changes
- Complete compliance with WordPress.org Plugin Directory requirements.
- Fixed admin page hook from `tools_page_slymetrics` to `settings_page_slymetrics`.
- Removed generic `/metrics` route to reduce potential conflicts with other plugins.
- Enhanced WordPress Coding Standards compliance with proper escaping and nonce handling.

## [1.2.0]
### Features
- Complete refactoring with modular design and significant reduction in function complexity.
- Enterprise-grade input validation, SQL injection prevention, XSS protection, and security headers.
- IP-based rate limiting (60 requests/minute) with proper HTTP 429 responses.
- Centralized error logging with structured context and WP_DEBUG integration.
- Improved client IP detection with proxy support and enhanced token validation.

### Changes
- 3-tier intelligent caching strategy for improved performance.
- Lazy loading and optimized data structures for heavy operations.
- Full English code comments for international development standards.

## [1.1.0]
### Changes
- Updated all metrics to follow Prometheus best practices with `wordpress_` prefix.
- Standardized all labels to use `wordpress_site` instead of `wp_site`.
- Changed environment variable prefix to `SLYMETRICS_*` for better plugin identification.
- Consistent `/slymetrics/` endpoint usage across all configurations.
