<?php

namespace SlyMetrics\Metrics\Builder;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Util\Formatter;
use SlyMetrics\Util\Logger;

/**
 * Builds Prometheus metrics for file integrity of WordPress core and
 * installed plugins against the official wordpress.org checksums (issue #9).
 *
 * Core references come from api.wordpress.org (MD5 per file), plugin
 * references from downloads.wordpress.org/plugin-checksums (MD5 + SHA-256).
 * Plugins without official checksums (custom/premium) are reported as
 * status="unavailable" instead of failing the scrape.
 *
 * @package SlyMetrics\Metrics\Builder
 */
class ChecksumMetrics {

	const CORE_API_URL   = 'https://api.wordpress.org/core/checksums/1.0/';
	const PLUGIN_API_URL = 'https://downloads.wordpress.org/plugin-checksums/';
	const HTTP_TIMEOUT   = 5;

	/** Official checksums are immutable per version – cache them for a day. */
	const REF_TTL = 86400;

	/** Seconds allowed for remote fetches per build; local hashing is unlimited. */
	const TIME_BUDGET = 20;

	/**
	 * @param string $site_name Value for the wordpress_site label.
	 * @return string Prometheus exposition lines.
	 */
	public static function build( string $site_name ): string {
		$out  = "# HELP wordpress_checksum_valid Integrity of core and plugin files against official wordpress.org checksums (1 = verified, 0 = modified or unavailable).\n";
		$out .= "# TYPE wordpress_checksum_valid gauge\n";
		$out .= "# HELP wordpress_checksum_files_total Number of files per checksum verification result (match, mismatch, missing).\n";
		$out .= "# TYPE wordpress_checksum_files_total gauge\n";

		$deadline = microtime( true ) + self::TIME_BUDGET;

		$out .= self::build_core( $site_name, $deadline );

		foreach ( self::get_installed_plugins() as $plugin_file => $data ) {
			// hello.php ships with core but differs from the hello-dolly
			// directory package; its integrity is covered by the core check.
			if ( 'hello.php' === $plugin_file ) {
				continue;
			}
			$out .= self::build_plugin( $site_name, (string) $plugin_file, is_array( $data ) ? $data : array(), $deadline );
		}

		return $out;
	}

	/**
	 * Compare a map of relative file paths => expected MD5 against real files.
	 *
	 * The expected value may be a single hash or a list of acceptable hashes
	 * (wordpress.org ships multiple official variants for some files).
	 *
	 * @internal Public only for unit testing.
	 * @param array  $checksums Relative path => expected MD5 hash(es).
	 * @param string $base_dir  Directory the paths are relative to (trailing slash).
	 * @return array{match:int,mismatch:int,missing:int}
	 */
	public static function verify_files( array $checksums, string $base_dir ): array {
		$counts = array(
			'match'    => 0,
			'mismatch' => 0,
			'missing'  => 0,
		);

		foreach ( $checksums as $file => $expected ) {
			$path = $base_dir . $file;
			if ( ! is_file( $path ) ) {
				$counts['missing']++;
				continue;
			}
			$actual = (string) md5_file( $path );
			$ok     = false;
			foreach ( (array) $expected as $hash ) {
				if ( hash_equals( (string) $hash, $actual ) ) {
					$ok = true;
					break;
				}
			}
			if ( $ok ) {
				$counts['match']++;
			} else {
				$counts['mismatch']++;
			}
		}

		return $counts;
	}

	// -----------------------------------------------------------------------
	// Private builders
	// -----------------------------------------------------------------------

	/**
	 * @param string $site_name
	 * @param float  $deadline Unix timestamp (microtime) after which no remote fetch is started.
	 * @return string
	 */
	private static function build_core( string $site_name, float $deadline ): string {
		$version   = get_bloginfo( 'version' );
		$locale    = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		$checksums = self::core_checksums( $version, $locale, $deadline );

		if ( null === $checksums ) {
			Logger::error( 'Core checksums unavailable', array( 'version' => $version, 'locale' => $locale ) );
			return self::unavailable( $site_name, 'core', 'wordpress', $version );
		}

		$counts = self::verify_files( $checksums, ABSPATH );
		return self::render( $site_name, 'core', 'wordpress', $version, $counts );
	}

	/**
	 * Merge the official core checksums of the site locale and the en_US base
	 * package. A file is accepted when it matches ANY official variant:
	 * localized packages (e.g. de_DE) ship a handful of files with different
	 * content (wp-includes/version.php, wp-config-sample.php), and many hosts
	 * run en_US-based images with a non-en_US UI locale.
	 *
	 * @param string $version
	 * @param string $locale
	 * @param float  $deadline
	 * @return array|null Merged path => hash(es) map, or null if no reference could be fetched.
	 */
	private static function core_checksums( string $version, string $locale, float $deadline ): ?array {
		$locales = array( $locale );
		if ( 'en_US' !== $locale ) {
			$locales[] = 'en_US';
		}

		$merged = null;
		foreach ( $locales as $loc ) {
			$url  = self::CORE_API_URL . '?version=' . rawurlencode( $version ) . '&locale=' . rawurlencode( $loc );
			$data = self::fetch_json( $url, $deadline );
			if ( null === $data || ! isset( $data['checksums'] ) || ! is_array( $data['checksums'] ) ) {
				continue;
			}
			if ( null === $merged ) {
				$merged = array();
			}
			foreach ( $data['checksums'] as $file => $md5 ) {
				if ( ! isset( $merged[ $file ] ) ) {
					$merged[ $file ] = $md5;
					continue;
				}
				$accepted = (array) $merged[ $file ];
				foreach ( (array) $md5 as $hash ) {
					if ( ! in_array( $hash, $accepted, true ) ) {
						$accepted[] = $hash;
					}
				}
				$merged[ $file ] = $accepted;
			}
		}

		return $merged;
	}

	/**
	 * @param string $site_name
	 * @param string $plugin_file Plugin basename, e.g. "akismet/akismet.php" or "hello.php".
	 * @param array  $data        Plugin header data from get_plugins().
	 * @param float  $deadline    Unix timestamp (microtime) after which no remote fetch is started.
	 * @return string
	 */
	private static function build_plugin( string $site_name, string $plugin_file, array $data, float $deadline ): string {
		$slug    = self::plugin_slug( $plugin_file );
		$version = isset( $data['Version'] ) ? (string) $data['Version'] : '';

		if ( '' === $version ) {
			return self::unavailable( $site_name, 'plugin', $slug, $version );
		}

		$url       = self::PLUGIN_API_URL . rawurlencode( $slug ) . '/' . rawurlencode( $version ) . '.json';
		$reference = self::fetch_json( $url, $deadline );

		if ( null === $reference || ! isset( $reference['files'] ) || ! is_array( $reference['files'] ) ) {
			return self::unavailable( $site_name, 'plugin', $slug, $version );
		}

		$md5s = array();
		foreach ( $reference['files'] as $file => $hashes ) {
			// Keep string or list of acceptable hashes; verify_files handles both.
			$md5s[ $file ] = ( is_array( $hashes ) && isset( $hashes['md5'] ) ) ? $hashes['md5'] : '';
		}

		// Checksum paths are relative to the plugin directory; single-file
		// plugins live directly in WP_PLUGIN_DIR.
		$base_dir = WP_PLUGIN_DIR . '/' . ( false !== strpos( $plugin_file, '/' ) ? dirname( $plugin_file ) . '/' : '' );
		$counts   = self::verify_files( $md5s, $base_dir );

		return self::render( $site_name, 'plugin', $slug, $version, $counts );
	}

	/**
	 * Derive the wordpress.org plugin slug from a plugin basename.
	 *
	 * @param string $plugin_file e.g. "akismet/akismet.php" or "hello.php".
	 * @return string e.g. "akismet" or "hello".
	 */
	private static function plugin_slug( string $plugin_file ): string {
		$slash = strpos( $plugin_file, '/' );
		if ( false !== $slash ) {
			return substr( $plugin_file, 0, $slash );
		}
		return basename( $plugin_file, '.php' );
	}

	/**
	 * @return array<string,array> Plugin basename => header data.
	 */
	private static function get_installed_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		return is_array( $plugins ) ? $plugins : array();
	}

	/**
	 * Fetch and decode a checksum reference document, with a 24 h transient
	 * cache (official checksums never change for a given version) and a hard
	 * time budget so a slow API can never stall the metrics endpoint.
	 *
	 * Cache key uses SHA-256 (lesson from #12: no weak hashes, safe key chars).
	 *
	 * @internal Public only for unit testing.
	 * @param string $url      Reference document URL.
	 * @param float  $deadline Unix timestamp (microtime) after which no remote fetch is started.
	 * @return array|null Decoded JSON or null on any failure.
	 */
	public static function fetch_json( string $url, float $deadline ): ?array {
		$cache_key = 'slymetrics_cs_' . hash( 'sha256', $url );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( microtime( true ) >= $deadline ) {
			return null;
		}

		$response = wp_remote_get( $url, array(
			'timeout'    => self::HTTP_TIMEOUT,
			'user-agent' => 'SlyMetrics (+https://github.com/slybase/wordpress-slymetrics)',
		) );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		set_transient( $cache_key, $data, self::REF_TTL );
		return $data;
	}

	/**
	 * Render the valid gauge plus per-result file counts.
	 *
	 * Missing files (e.g. removed bundled themes) are reported in the detail
	 * metric but do not flip the valid bit – only content mismatches do
	 * (same distinction wp-cli makes between warnings and errors).
	 *
	 * @param string $site_name
	 * @param string $type    "core" or "plugin".
	 * @param string $slug
	 * @param string $version
	 * @param array  $counts  Result of verify_files().
	 * @return string
	 */
	private static function render( string $site_name, string $type, string $slug, string $version, array $counts ): string {
		$valid  = ( 0 === $counts['mismatch'] ) ? 1 : 0;
		$labels = array(
			'type'    => $type,
			'slug'    => $slug,
			'version' => $version,
		);

		$out  = Formatter::metric( 'wordpress_checksum_valid', $site_name, $labels + array( 'status' => $valid ? 'verified' : 'modified' ), $valid );
		foreach ( array( 'match', 'mismatch', 'missing' ) as $result ) {
			$out .= Formatter::metric( 'wordpress_checksum_files_total', $site_name, $labels + array( 'result' => $result ), $counts[ $result ] );
		}
		return $out;
	}

	/**
	 * Render a single "unavailable" gauge (no official checksums could be fetched).
	 *
	 * @param string $site_name
	 * @param string $type
	 * @param string $slug
	 * @param string $version
	 * @return string
	 */
	private static function unavailable( string $site_name, string $type, string $slug, string $version ): string {
		return Formatter::metric( 'wordpress_checksum_valid', $site_name, array(
			'type'    => $type,
			'slug'    => $slug,
			'version' => $version,
			'status'  => 'unavailable',
		), 0 );
	}
}