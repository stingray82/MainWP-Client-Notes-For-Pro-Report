<?php
/**
 * Universal Updater Drop-In (UUPD) 2.0 for Plugins & Themes
 * =========================================================
 *
 * A lightweight, self-contained WordPress updater supporting both
 * private JSON endpoints and GitHub Releases (public or private),
 * now with vendor + slug scoped identity.
 *
 * Designed to be copied directly into plugins or themes with no
 * external dependencies.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Supported Features
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * ✔ Private update servers (JSON metadata)
 * ✔ GitHub Releases-based updates (public or private)
 * ✔ Manual “Check for updates” trigger
 * ✔ WordPress-native update UI integration
 * ✔ Private GitHub release assets (via API + token)
 * ✔ Caching via WordPress transients
 * ✔ Pre-release (alpha/beta/RC/dev) handling
 * ✔ Optional branding (icons, banners, screenshots)
 * ✔ Vendor + slug scoped filters and cache keys
 *
 * Safe to include multiple times, as long as namespace/class naming is isolated
 * when bundling separate copies.
 *
 * ───────────────────────── Compatibility / Upgrade Notes ─────────────────────
 *
 * Version 2.0 is a breaking upgrade from the legacy slug-only identity model.
 *
 * Every updater registration is now uniquely identified by:
 *
 *   vendor + slug
 *
 * This prevents collisions when multiple plugin authors bundle UUPD in
 * their own products.
 *
 * Important upgrade notes from V1:
 *
 *   • `vendor` is now REQUIRED
 *   • Core filters now support layered resolution: base, vendor-wide, and vendor + slug scoped
 *   • Cache keys are vendor-aware by default
 *   • Manual checks are vendor-aware
 *   • Slug-only filter naming is not supported; use base, vendor-wide, or vendor + slug filters
 *
 * Existing V1-style slug-only registrations must be updated before using V2.
 *
 * ⚠️ Notes for edge cases:
 *
 *   • GitHub auto-detection is strict:
 *     GitHub Releases mode is triggered ONLY when `server` is a repo-root URL:
 *
 *         ✅ https://github.com/owner/repo
 *         ❌ https://github.com/owner/repo/releases
 *         ❌ https://raw.githubusercontent.com/...
 *
 *     If you want to force GitHub Releases mode, explicitly set:
 *
 *         'mode' => 'github_release'
 *
 *   • If a GitHub token is configured, UUPD automatically injects
 *     Authorization headers for outgoing GitHub API and asset requests
 *     when appropriate.
 *
 *     This is required for private repositories, private assets,
 *     and may help avoid GitHub API rate limits.
 *
 * ───────────────────────────── Update Modes ─────────────────────────────
 *
 * UUPD supports two update modes:
 *
 * 1) JSON Mode (Private Update Server)
 * -----------------------------------
 *    Set `server` to a JSON metadata URL (recommended: ends with `.json`)
 *
 *    Example:
 *      https://example.com/uupd/index.json
 *
 *    JSON metadata may include:
 *      - version
 *      - download_url
 *      - homepage
 *      - author / author_homepage
 *      - sections (changelog, description, installation, etc)
 *      - icons, banners, screenshots
 *
 * 2) GitHub Releases Mode
 * -----------------------
 *    Set `server` to the GitHub repository root:
 *
 *      https://github.com/<owner>/<repo>
 *
 *    UUPD will call:
 *      https://api.github.com/repos/<owner>/<repo>/releases/latest
 *
 *    • Public repos work without a token
 *    • Private repos and/or private assets REQUIRE a GitHub token
 *
 * ───────────────────────── Mode Auto-Detection ─────────────────────────
 *
 * Default mode: 'auto'
 *
 *   • If `server` is a GitHub repo root → GitHub Releases mode
 *   • Otherwise → JSON mode
 *
 * You may force a mode explicitly:
 *
 *   'mode' => 'auto'            // default
 *   'mode' => 'json'            // always use JSON metadata
 *   'mode' => 'github_release'  // always use GitHub Releases
 *
 * ───────────────────────── Required Config ─────────────────────────
 *
 *   vendor       Unique creator/vendor namespace, e.g. 'tdlab'
 *   slug         Plugin or theme slug
 *   name         Human-readable name
 *   version      Current installed version
 *   server       Update server base URL, JSON metadata URL, or GitHub repo root
 *
 * Optional keys include:
 *
 *   plugin_file        plugin_basename( __FILE__ ) for plugins
 *   real_slug          Theme folder slug where needed
 *   key                License/auth key appended to JSON metadata requests
 *   github_token       Token for private GitHub release access
 *   github_asset_name  Preferred asset name for GitHub Releases
 *   mode               auto|json|github_release
 *   allow_prerelease   true|false
 *   cache_prefix       Transient key prefix. Default: 'uupd_<vendor>__'
 *   icons              Optional icons array
 *   banners            Optional banners array
 *   screenshots        Optional screenshots array
 *   screenshot         Optional single screenshot URL
 *
 * ───────────────────────── Filter Hierarchy ─────────────────────────
 *
 * UUPD 2.0 supports layered filter resolution for flexibility during
 * development, testing, fleet-wide overrides, and per-product exceptions.
 *
 * Filters are applied in this order:
 *
 *   1) Base/global filter:
 *        uupd/<filter>
 *
 *   2) Vendor-wide filter:
 *        uupd/<filter>/<vendor>
 *
 *   3) Fully scoped filter:
 *        uupd/<filter>/<vendor>/<slug>
 *
 * Later filters receive the result of earlier ones and may override them.
 *
 * Example:
 *
 *   add_filter( 'uupd/server_url', function( $url, $vendor, $slug ) {
 *       if ( $vendor === 'tdlab' ) {
 *           return 'https://updates.example.com/';
 *       }
 *       return $url;
 *   }, 10, 3 );
 *
 *   add_filter( 'uupd/server_url/tdlab', function( $url, $vendor, $slug ) {
 *       return 'https://staging.example.com/';
 *   }, 10, 3 );
 *
 *   add_filter( 'uupd/server_url/tdlab/my-plugin', function( $url, $vendor, $slug ) {
 *       return 'https://example.com/custom-endpoint.json';
 *   }, 10, 3 );
 *
 * Common filters:
 *
 *   uupd/filter_config
 *   uupd/filter_config/<vendor>
 *   uupd/filter_config/<vendor>/<slug>
 *   uupd/server_url
 *   uupd/server_url/<vendor>
 *   uupd/server_url/<vendor>/<slug>
 *   uupd/cache_prefix
 *   uupd/cache_prefix/<vendor>
 *   uupd/cache_prefix/<vendor>/<slug>
 *   uupd/github_token_override
 *   uupd/github_token_override/<vendor>
 *   uupd/github_token_override/<vendor>/<slug>
 *   uupd/icons
 *   uupd/icons/<vendor>
 *   uupd/icons/<vendor>/<slug>
 *   uupd/banners
 *   uupd/banners/<vendor>
 *   uupd/banners/<vendor>/<slug>
 *   uupd/screenshots
 *   uupd/screenshots/<vendor>
 *   uupd/screenshots/<vendor>/<slug>
 *   uupd/screenshot
 *   uupd/screenshot/<vendor>
 *   uupd/screenshot/<vendor>/<slug>
 *   uupd_success_cache_ttl
 *   uupd_success_cache_ttl/<vendor>
 *   uupd_success_cache_ttl/<vendor>/<slug>
 *   uupd_fetch_remote_error_ttl
 *   uupd_fetch_remote_error_ttl/<vendor>
 *   uupd_fetch_remote_error_ttl/<vendor>/<slug>
 *   uupd/manual_check_redirect
 *   uupd/manual_check_redirect/<vendor>
 *   uupd/manual_check_redirect/<vendor>/<slug>
 *
 * ───────────────────────── Actions ─────────────────────────
 *
 * Generic actions:
 *
 *   uupd/before_fetch_remote
 *   uupd_metadata_fetch_failed
 *   uupd/log
 *
 * Scoped actions:
 *
 *   uupd/before_fetch_remote/<vendor>/<slug>
 *   uupd_metadata_fetch_failed/<vendor>/<slug>
 *
 * Legacy slug-only failure actions may also be emitted for compatibility:
 *
 *   uupd_metadata_fetch_failed/<slug>
 *
 * ───────────────────────── GitHub Token Filters ─────────────────────────
 *
 * Override GitHub tokens per vendor + slug:
 *
 *   add_filter( 'uupd/github_token_override/tdlab/my-plugin', function( $token, $vendor, $slug ) {
 *       return 'ghp_tokenForThisProject';
 *   }, 10, 3 );
 *
 * Token scopes:
 *   • Private repos generally require appropriate `repo` access
 *
 * ───────────────────────── Visual Assets & Branding ─────────────────────────
 *
 * In JSON mode, icons/banners are usually read directly from metadata.
 *
 * In GitHub Releases mode, UUPD does not fetch separate remote JSON metadata
 * unless you explicitly use JSON mode, so branding may be supplied via config
 * or via scoped filters.
 *
 * Via config:
 *
 *   'icons' => [
 *       '1x' => 'https://cdn.example.com/icon-128.png',
 *       '2x' => 'https://cdn.example.com/icon-256.png',
 *   ],
 *
 *   'banners' => [
 *       'low'  => 'https://cdn.example.com/banner-772x250.png',
 *       'high' => 'https://cdn.example.com/banner-1544x500.png',
 *   ],
 *
 * Via scoped filters:
 *
 *   add_filter( 'uupd/icons/tdlab/my-plugin', function( $icons ) {
 *       return [
 *           '1x' => 'https://cdn.example.com/icon-128.png',
 *           '2x' => 'https://cdn.example.com/icon-256.png',
 *       ];
 *   } );
 *
 *   add_filter( 'uupd/banners/tdlab/my-plugin', function( $banners ) {
 *       return [
 *           'low'  => 'https://cdn.example.com/banner-772x250.png',
 *           'high' => 'https://cdn.example.com/banner-1544x500.png',
 *       ];
 *   } );
 *
 * ─────────────────────────── Plugin Integration ───────────────────────────
 *
 *   add_action( 'plugins_loaded', function() {
 *       require_once __DIR__ . '/includes/updater.php';
 *
 *       \UUPD\V2\Updater_V2::register( [
 *           'vendor'      => 'tdlab',
 *           'plugin_file' => plugin_basename( __FILE__ ),
 *           'slug'        => 'my-plugin-slug',
 *           'name'        => 'My Plugin Name',
 *           'version'     => MY_PLUGIN_VERSION,
 *           'server'      => 'https://github.com/user/repo',
 *           'github_token'=> 'ghp_YourTokenHere',
 *       ] );
 *   }, 1 );
 *
 * ─────────────────────────── Theme Integration ───────────────────────────
 *
 *   add_action( 'after_setup_theme', function() {
 *       require_once get_stylesheet_directory() . '/includes/updater.php';
 *
 *       add_action( 'admin_init', function() {
 *           \UUPD\V2\Updater_V2::register( [
 *               'vendor'       => 'tdlab',
 *               'slug'         => 'my-theme-folder',
 *               'real_slug'    => 'my-theme-folder',
 *               'name'         => 'My Theme Name',
 *               'version'      => '1.0.0',
 *               'server'       => 'https://github.com/user/repo',
 *               'github_token' => 'ghp_YourTokenHere',
 *           ] );
 *       } );
 *   } );
 *
 * ───────────────────────── Cache Duration Filters ─────────────────────────
 *
 *   add_filter( 'uupd_success_cache_ttl/tdlab/my-plugin', function( $ttl, $vendor, $slug ) {
 *       return 1 * HOUR_IN_SECONDS;
 *   }, 10, 3 );
 *
 *   add_filter( 'uupd_fetch_remote_error_ttl/tdlab/my-plugin', function( $ttl, $vendor, $slug ) {
 *       return 15 * MINUTE_IN_SECONDS;
 *   }, 10, 3 );
 *
 * ───────────────────────── Optional Debugging ─────────────────────────
 *
 *   add_filter( 'updater_enable_debug', '__return_true' );
 *
 *   In wp-config.php:
 *     define( 'WP_DEBUG', true );
 *     define( 'WP_DEBUG_LOG', true );
 *
 * ───────────────────────── Summary ─────────────────────────
 *
 * • Fetches update metadata from JSON or GitHub Releases
 * • Injects updates into native WordPress transients
 * • Supports private repos, private assets, and branding
 * • Uses vendor + slug scoped identity to avoid collisions
 * • Zero dependencies, safe to bundle anywhere
 *
 * @package RUP\Updater
 */
namespace RUP\Updater;

if ( ! class_exists( __NAMESPACE__ . '\Updater_V2' ) ) {

	class Updater_V2 {

		const VERSION = '2.0.0-alpha.1';

		/** @var array Configuration settings */
		private $config;

		private static function sanitize_identity_part( $value ) {
			return sanitize_key( (string) $value );
		}

		private static function build_instance_key( $vendor, $slug ) {
			return self::sanitize_identity_part( $vendor ) . '__' . self::sanitize_identity_part( $slug );
		}

		/**
		 * Apply a layered filter using base, vendor-wide, and vendor+slug scopes.
		 *
		 * Filters are resolved in this order:
		 *   1) {$filter_base}
		 *   2) {$filter_base}/{$vendor}
		 *   3) {$filter_base}/{$vendor}/{$slug}
		 *
		 * Each later filter receives the value returned by the previous stage.
		 *
		 * Callbacks receive:
		 *   - $value
		 *   - $vendor
		 *   - $slug
		 *   - $instance_key
		 *
		 * @param string $filter_base Filter base name without trailing identity.
		 * @param mixed  $default     Default value.
		 * @param string $vendor      Vendor identity.
		 * @param string $slug        Plugin/theme slug.
		 * @return mixed
		 */
		private static function apply_filters_scoped( $filter_base, $default, $vendor, $slug ) {
			$vendor       = self::sanitize_identity_part( $vendor );
			$slug         = self::sanitize_identity_part( $slug );
			$instance_key = self::build_instance_key( $vendor, $slug );

			$value = apply_filters(
				$filter_base,
				$default,
				$vendor,
				$slug,
				$instance_key
			);

			$value = apply_filters(
				"{$filter_base}/{$vendor}",
				$value,
				$vendor,
				$slug,
				$instance_key
			);

			$value = apply_filters(
				"{$filter_base}/{$vendor}/{$slug}",
				$value,
				$vendor,
				$slug,
				$instance_key
			);

			return $value;
		}

		/**
		 * Emit metadata failure actions in generic, legacy slug-only, and vendor+slug-scoped forms.
		 *
		 * @param string $vendor Vendor.
		 * @param string $slug   Slug.
		 * @param array  $data   Failure payload.
		 * @return void
		 */
		private static function do_metadata_failure_actions( $vendor, $slug, array $data ) {
			$vendor = self::sanitize_identity_part( $vendor );
			$slug   = self::sanitize_identity_part( $slug );

			do_action( 'uupd_metadata_fetch_failed', $data );

			// Legacy slug-only compatibility.
			do_action( "uupd_metadata_fetch_failed/{$slug}", $data );

			// Vendor-aware scoped action.
			do_action( "uupd_metadata_fetch_failed/{$vendor}/{$slug}", $data );
		}

		/**
		 * Constructor.
		 *
		 * @param array $config {
		 *   @type string $vendor           Vendor namespace/identity. Required.
		 *   @type string $slug             Plugin or theme slug. Required.
		 *   @type string $name             Human-readable name.
		 *   @type string $version          Current version.
		 *   @type string $key              Secret/auth key for JSON metadata requests.
		 *   @type string $server           Base URL, JSON metadata URL, or GitHub repo root URL.
		 *   @type string $plugin_file      Optional plugin_basename(__FILE__) for plugins.
		 *   @type bool   $allow_prerelease Optional whether prerelease versions are allowed.
		 *   @type string $cache_prefix     Optional transient prefix. Default 'uupd_<vendor>__'.
		 *   @type string $mode             Optional mode: auto|json|github_release.
		 *   @type string $github_token     Optional GitHub token for private release access.
		 *   @type string $github_asset_name Optional preferred release asset filename.
		 * }
		 */
		public function __construct( array $config ) {
			$config['vendor'] = self::sanitize_identity_part( $config['vendor'] ?? '' );
			$config['slug']   = self::sanitize_identity_part( $config['slug'] ?? '' );

			if ( $config['vendor'] === '' ) {
				_doing_it_wrong( __METHOD__, __( 'Missing vendor in Updater_V2 configuration.', 'default' ), self::VERSION );
				return;
			}

			if ( $config['slug'] === '' ) {
				_doing_it_wrong( __METHOD__, __( 'Missing slug in Updater_V2 configuration.', 'default' ), self::VERSION );
				return;
			}

			$config['instance_key'] = self::build_instance_key( $config['vendor'], $config['slug'] );

			$config = self::apply_filters_scoped( 'uupd/filter_config', $config, $config['vendor'], $config['slug'] );

			$config['allow_prerelease'] = self::apply_filters_scoped(
				'uupd/allow_prerelease',
				$config['allow_prerelease'] ?? false,
				$config['vendor'],
				$config['slug']
			);

			$config['server'] = self::apply_filters_scoped(
				'uupd/server_url',
				$config['server'] ?? '',
				$config['vendor'],
				$config['slug']
			);

			$default_cache_prefix = 'uupd_' . $config['vendor'] . '__';
			$config['cache_prefix'] = self::apply_filters_scoped(
				'uupd/cache_prefix',
				$config['cache_prefix'] ?? $default_cache_prefix,
				$config['vendor'],
				$config['slug']
			);

			$this->config = $config;
			$this->log( '✓ Using Updater_V2 version ' . self::VERSION );
			$this->register_hooks();
		}

		/**
		 * Filter outgoing HTTP requests so GitHub downloads include auth headers when needed.
		 *
		 * @param array  $args HTTP request arguments.
		 * @param string $url  Request URL.
		 * @return array
		 */
		public function filter_http_request_args( $args, $url ) {
			$url = (string) $url;

			// Only touch GitHub URLs (public + API).
			if ( strpos( $url, 'github.com/' ) === false && strpos( $url, 'api.github.com/' ) === false ) {
				return $args;
			}

			return $this->add_github_auth_headers_for_download( $args, $url );
		}

		/** Attach update and info filters for plugin or theme. */
		private function register_hooks() {
			if ( ! empty( $this->config['plugin_file'] ) ) {
				add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'plugin_update' ] );
				add_filter( 'site_transient_update_plugins', [ $this, 'plugin_update' ] ); // WP 6.8+
				add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
			} else {
				add_filter( 'pre_set_site_transient_update_themes', [ $this, 'theme_update' ] );
				add_filter( 'site_transient_update_themes', [ $this, 'theme_update' ] ); // WP 6.8+
				add_filter( 'themes_api', [ $this, 'theme_info' ], 10, 3 );
			}

			// Add GitHub auth headers when WP downloads metadata or zip packages.
			add_filter( 'http_request_args', [ $this, 'filter_http_request_args' ], 10, 2 );
		}

		/**
		 * Resolve a download URL from various common metadata keys.
		 *
		 * Supports multiple providers that may return different field names.
		 *
		 * @param object $meta Metadata object.
		 * @return string
		 */
		private function resolve_download_url( $meta ) {
			if ( ! is_object( $meta ) ) {
				return '';
			}

			$candidates = [
				$meta->download_url ?? '',
				$meta->package ?? '',
				$meta->download_link ?? '',
				$meta->download_uri ?? '',
				$meta->trunk ?? '',
			];

			foreach ( $candidates as $u ) {
				$u = trim( (string) $u );
				if ( $u !== '' ) {
					return $u;
				}
			}

			return '';
		}

		/** Fetch metadata JSON from remote server and cache it. */
		private function fetch_remote() {
			$c          = $this->config;
			$slug_plain = $c['slug'] ?? '';
			$vendor     = $c['vendor'] ?? '';
			$prefix     = $c['cache_prefix'] ?? 'uupd_' . $vendor . '__';

			if ( empty( $c['server'] ) ) {
				$this->log( 'No server URL configured — skipping fetch and caching an error state.' );
				$ttl = self::apply_filters_scoped( 'uupd_fetch_remote_error_ttl', 6 * HOUR_IN_SECONDS, $vendor, $slug_plain );
				set_transient( $prefix . $slug_plain . '_error', time(), $ttl );

				self::do_metadata_failure_actions( $vendor, $slug_plain, [
					'vendor'  => $vendor,
					'slug'    => $slug_plain,
					'server'  => '',
					'message' => 'No server configured',
				] );
				return;
			}

			$slug_qs = rawurlencode( $slug_plain );
			$key_qs  = rawurlencode( isset( $c['key'] ) ? $c['key'] : '' );
			$host_qs = rawurlencode( wp_parse_url( untrailingslashit( home_url() ), PHP_URL_HOST ) );

			$is_json = self::ends_with( $c['server'], '.json' );

			if ( $is_json ) {
				$url = $c['server'];
			} else {
				$separator = strpos( $c['server'], '?' ) === false ? '?' : '&';
				$url = untrailingslashit( $c['server'] ) . $separator . "action=get_metadata&slug={$slug_qs}&key={$key_qs}&domain={$host_qs}";
			}

			$url = self::apply_filters_scoped( 'uupd/remote_url', $url, $vendor, $slug_plain );

			$failure_cache_key = $prefix . $slug_plain . '_error';

			$this->log( " Fetching metadata: {$url}" );
			do_action( 'uupd/before_fetch_remote', $vendor, $slug_plain, $c );
			do_action( "uupd/before_fetch_remote/{$vendor}/{$slug_plain}", $c );
			$this->log( "→ Triggered action: uupd/before_fetch_remote for '{$slug_plain}'" );

			$resp = wp_remote_get(
				$url,
				[
					'timeout' => 15,
					'headers' => [ 'Accept' => 'application/json' ],
				]
			);

			if ( is_wp_error( $resp ) ) {
				$msg = $resp->get_error_message();
				$this->log( " WP_Error: $msg — caching failure for 6 hours" );
				$ttl = self::apply_filters_scoped( 'uupd_fetch_remote_error_ttl', 6 * HOUR_IN_SECONDS, $vendor, $slug_plain );
				set_transient( $failure_cache_key, time(), $ttl );

				self::do_metadata_failure_actions( $vendor, $slug_plain, [
					'vendor'  => $vendor,
					'slug'    => $slug_plain,
					'server'  => $c['server'],
					'message' => $msg,
				] );
				return;
			}

			$code = wp_remote_retrieve_response_code( $resp );
			$body = wp_remote_retrieve_body( $resp );

			$this->log( '← HTTP ' . $code . ': ' . trim( $body ) );

			if ( 200 !== (int) $code ) {
				$this->log( "Unexpected HTTP {$code} — update fetch will pause until next cycle" );
				$ttl = self::apply_filters_scoped( 'uupd_fetch_remote_error_ttl', 6 * HOUR_IN_SECONDS, $vendor, $slug_plain );
				set_transient( $failure_cache_key, time(), $ttl );

				self::do_metadata_failure_actions( $vendor, $slug_plain, [
					'vendor' => $vendor,
					'slug'   => $slug_plain,
					'server' => $c['server'],
					'code'   => $code,
				] );
				return;
			}

			$meta = json_decode( $body );
			if ( ! $meta ) {
				$this->log( ' JSON decode failed — caching error state' );
				$ttl = self::apply_filters_scoped( 'uupd_fetch_remote_error_ttl', 6 * HOUR_IN_SECONDS, $vendor, $slug_plain );
				set_transient( $failure_cache_key, time(), $ttl );

				self::do_metadata_failure_actions( $vendor, $slug_plain, [
					'vendor'  => $vendor,
					'slug'    => $slug_plain,
					'server'  => $c['server'],
					'code'    => 200,
					'message' => 'Invalid JSON',
				] );
				return;
			}

			$meta = self::apply_filters_scoped( 'uupd/metadata_result', $meta, $vendor, $slug_plain );

			$ttl = self::apply_filters_scoped( 'uupd_success_cache_ttl', 6 * HOUR_IN_SECONDS, $vendor, $slug_plain );
			set_transient( $prefix . $slug_plain, $meta, $ttl );
			delete_transient( $failure_cache_key );
			$this->log( " Cached metadata '{$slug_plain}' → v" . ( $meta->version ?? 'unknown' ) );
		}

		private function normalize_version( $v ) {
			$v = trim( (string) $v );

			$v = preg_replace( '/\+.*$/', '', $v );
			$v = ltrim( $v, 'vV' );
			$v = str_replace( '_', '-', $v );

			if ( preg_match( '/^\d+\.\d+$/', $v ) ) {
				$v .= '.0';
			} elseif ( preg_match( '/^\d+$/', $v ) ) {
				$v .= '.0.0';
			}

			if ( preg_match( '/^(\d+\.\d+\.\d+)[\.\-]?((?:alpha|a|beta|b|rc|dev|pre|preview))(?:(?:[\.\-]?)(\d+))?$/i', $v, $m ) ) {
				$core = $m[1];
				$tag  = strtolower( $m[2] );
				$num  = isset( $m[3] ) && $m[3] !== '' ? $m[3] : '0';

				switch ( $tag ) {
					case 'a':
						$tag = 'alpha';
						break;
					case 'b':
						$tag = 'beta';
						break;
					case 'pre':
					case 'preview':
						$tag = 'beta';
						break;
					case 'rc':
						$tag = 'rc';
						break;
					case 'dev':
						$tag = 'dev';
						break;
				}

				$v = "{$core}-{$tag}.{$num}";
			}

			$v = preg_replace( '/^(\d+\.\d+\.\d+)-(alpha|beta|rc|dev)(?=$)/i', '$1-$2.0', $v );

			return $v;
		}

		/**
		 * Resolve icons/banners/screenshots from config and allow scoped filters.
		 *
		 * Supports both:
		 *  - config values: 'icons', 'banners', 'screenshots', 'screenshot'
		 *  - filters: uupd/icons, uupd/banners, uupd/screenshots, uupd/screenshot
		 *
		 * @return array{icons:array,banners:array,screenshots:array,screenshot:string}
		 */
		private function resolve_visual_assets() {
			$slug   = $this->config['slug'] ?? '';
			$vendor = $this->config['vendor'] ?? '';

			$icons       = $this->config['icons'] ?? [];
			$banners     = $this->config['banners'] ?? [];
			$screenshots = $this->config['screenshots'] ?? [];
			$screenshot  = $this->config['screenshot'] ?? '';

			$icons       = (array) self::apply_filters_scoped( 'uupd/icons', $icons, $vendor, $slug );
			$banners     = (array) self::apply_filters_scoped( 'uupd/banners', $banners, $vendor, $slug );
			$screenshots = (array) self::apply_filters_scoped( 'uupd/screenshots', $screenshots, $vendor, $slug );
			$screenshot  = (string) self::apply_filters_scoped( 'uupd/screenshot', $screenshot, $vendor, $slug );

			return [
				'icons'       => $icons,
				'banners'     => $banners,
				'screenshots' => $screenshots,
				'screenshot'  => $screenshot,
			];
		}

		/**
		 * Apply visual assets (icons/banners/screenshots) from config/filters to meta.
		 * Metadata wins; only missing fields are backfilled.
		 *
		 * @param object $meta Metadata.
		 * @return object
		 */
		private function apply_visual_assets_to_meta( $meta ) {
			if ( ! is_object( $meta ) ) {
				return $meta;
			}

			$va = $this->resolve_visual_assets();

			if ( empty( $meta->icons ) && ! empty( $va['icons'] ) ) {
				$meta->icons = $va['icons'];
			}
			if ( empty( $meta->banners ) && ! empty( $va['banners'] ) ) {
				$meta->banners = $va['banners'];
			}
			if ( empty( $meta->screenshots ) && ! empty( $va['screenshots'] ) ) {
				$meta->screenshots = $va['screenshots'];
			}
			if ( empty( $meta->screenshot ) && ! empty( $va['screenshot'] ) ) {
				$meta->screenshot = $va['screenshot'];
			}

			return $meta;
		}

		/** Handle plugin update injection. */
		public function plugin_update( $trans ) {
			if ( ! is_object( $trans ) || ! isset( $trans->checked ) || ! is_array( $trans->checked ) ) {
				return $trans;
			}

			$c         = $this->config;
			$file      = $c['plugin_file'];
			$slug      = $c['slug'];
			$vendor    = $c['vendor'] ?? '';
			$prefix    = $c['cache_prefix'] ?? 'uupd_' . $vendor . '__';
			$cache_id  = $prefix . $slug;
			$error_key = $cache_id . '_error';

			$this->log( "Plugin-update hook for '{$slug}'" );

			$current = $trans->checked[ $file ] ?? $c['version'];
			$meta    = get_transient( $cache_id );

			if ( false === $meta && get_transient( $error_key ) ) {
				$this->log( " Skipping plugin update check for '{$slug}' — previous error cached" );
				return $trans;
			}

			if ( false === $meta ) {
				if ( $this->should_use_github_release_mode() ) {
					$repo_url  = rtrim( $c['server'], '/' );
					$cache_key = 'uupd_github_release_' . ( $c['instance_key'] ?? self::build_instance_key( $vendor, $slug ) ) . '_' . md5( $repo_url );
					$release   = get_transient( $cache_key );

					if ( false === $release ) {
						$api_url = $this->github_latest_release_api_url( $repo_url );

						$token = self::apply_filters_scoped(
							'uupd/github_token_override',
							$c['github_token'] ?? '',
							$vendor,
							$slug
						);

						$headers = [
							'Accept'     => 'application/vnd.github.v3+json',
							'User-Agent' => 'WordPress-UUPD',
						];

						if ( $token ) {
							$headers['Authorization'] = 'token ' . $token;
						}

						$this->log( " GitHub fetch: $api_url" );
						$response = wp_remote_get( $api_url, [ 'headers' => $headers, 'timeout' => 15 ] );

						if ( ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) === 200 ) {
							$release = json_decode( wp_remote_retrieve_body( $response ) );
							$ttl     = self::apply_filters_scoped( 'uupd_success_cache_ttl', 6 * HOUR_IN_SECONDS, $vendor, $slug );
							set_transient( $cache_key, $release, $ttl );
						} else {
							$msg = is_wp_error( $response ) ? $response->get_error_message() : ( 'HTTP ' . wp_remote_retrieve_response_code( $response ) );
							$this->log( "✗ GitHub API failed — {$msg} — caching error state" );

							set_transient(
								$error_key,
								time(),
								self::apply_filters_scoped( 'uupd_fetch_remote_error_ttl', 6 * HOUR_IN_SECONDS, $vendor, $slug )
							);

							self::do_metadata_failure_actions( $vendor, $slug, [
								'vendor'  => $vendor,
								'slug'    => $slug,
								'server'  => $repo_url,
								'message' => $msg,
							] );
							return $trans;
						}
					}

					if ( isset( $release->tag_name ) ) {
						$zip_url = $this->github_release_download_url( $repo_url, $release );

						$meta = (object) [
							'version'      => ltrim( (string) $release->tag_name, 'v' ),
							'download_url' => $zip_url,
							'homepage'     => $release->html_url ?? $repo_url,
							'sections'     => [ 'changelog' => $release->body ?? '' ],
						];
					} else {
						$meta = (object) [
							'version'      => $c['version'],
							'download_url' => '',
							'homepage'     => $repo_url,
							'sections'     => [ 'changelog' => '' ],
						];
					}

					$meta = $this->apply_visual_assets_to_meta( $meta );

					set_transient(
						$cache_id,
						$meta,
						self::apply_filters_scoped( 'uupd_success_cache_ttl', 6 * HOUR_IN_SECONDS, $vendor, $slug )
					);

					delete_transient( $error_key );
				} else {
					$this->fetch_remote();
					$meta = get_transient( $cache_id );

					if ( $meta ) {
						$meta = $this->apply_visual_assets_to_meta( $meta );
						set_transient(
							$cache_id,
							$meta,
							self::apply_filters_scoped( 'uupd_success_cache_ttl', 6 * HOUR_IN_SECONDS, $vendor, $slug )
						);
					}
				}
			}

			if ( ! $meta ) {
				$this->log( 'No metadata found, skipping update logic.' );
				return $trans;
			}

			$resolved_pkg = $this->resolve_download_url( $meta );

			if ( $resolved_pkg && empty( $meta->download_url ) ) {
				$meta->download_url = $resolved_pkg;
			}

			$this->log( 'Resolved package URL (normalized): ' . ( $resolved_pkg ? $resolved_pkg : 'EMPTY' ) );

			$remote_version   = $meta->version ?? '0.0.0';
			$allow_prerelease = $this->config['allow_prerelease'] ?? false;

			$current_normalized = $this->normalize_version( $current );
			$remote_normalized  = $this->normalize_version( $remote_version );

			$this->log( "Original versions: installed={$current}, remote={$remote_version}" );
			$this->log( "Normalized versions: installed={$current_normalized}, remote={$remote_normalized}" );
			$this->log( "Comparing (normalized): installed={$current_normalized} vs remote={$remote_normalized}" );

			if (
				( ! $allow_prerelease && preg_match( '/^\d+\.\d+\.\d+-(alpha|beta|rc|dev|preview)(?:[.\-]\d+)?$/i', $remote_normalized ) ) ||
				version_compare( $current_normalized, $remote_normalized, '>=' )
			) {
				$this->log( "Plugin '{$slug}' is up to date (v{$current})" );
				$trans->no_update[ $file ] = (object) [
					'id'            => $file,
					'slug'          => $slug,
					'plugin'        => $file,
					'new_version'   => $current,
					'url'           => $meta->homepage ?? '',
					'package'       => '',
					'icons'         => (array) ( $meta->icons ?? [] ),
					'banners'       => (array) ( $meta->banners ?? [] ),
					'tested'        => $meta->tested ?? '',
					'requires'      => $meta->requires ?? $meta->min_wp_version ?? '',
					'requires_php'  => $meta->requires_php ?? '',
					'compatibility' => new \stdClass(),
				];
				return $trans;
			}

			$this->log( "Injecting plugin update '{$slug}' → v{$meta->version}" );
			$pkg = $resolved_pkg;
			$this->log( 'Resolved package URL: ' . ( $pkg ? $pkg : 'EMPTY' ) );

			$trans->response[ $file ] = (object) [
				'id'            => $file,
				'name'          => $c['name'],
				'slug'          => $slug,
				'plugin'        => $file,
				'new_version'   => $meta->version ?? $c['version'],
				'package'       => $pkg,
				'url'           => $meta->homepage ?? '',
				'tested'        => $meta->tested ?? '',
				'requires'      => $meta->requires ?? $meta->min_wp_version ?? '',
				'requires_php'  => $meta->requires_php ?? '',
				'sections'      => (array) ( $meta->sections ?? [] ),
				'icons'         => (array) ( $meta->icons ?? [] ),
				'banners'       => (array) ( $meta->banners ?? [] ),
				'compatibility' => new \stdClass(),
			];

			unset( $trans->no_update[ $file ] );
			return $trans;
		}

		/** Handle theme update injection. */
		public function theme_update( $trans ) {
			if ( ! is_object( $trans ) || ! isset( $trans->checked ) || ! is_array( $trans->checked ) ) {
				return $trans;
			}

			$c         = $this->config;
			$slug      = $c['real_slug'] ?? $c['slug'];
			$vendor    = $c['vendor'] ?? '';
			$prefix    = $c['cache_prefix'] ?? 'uupd_' . $vendor . '__';
			$cache_id  = $prefix . $c['slug'];
			$error_key = $cache_id . '_error';

			$this->log( "Theme-update hook for '{$c['slug']}'" );

			$current = $trans->checked[ $slug ] ?? wp_get_theme( $slug )->get( 'Version' );
			$meta    = get_transient( $cache_id );

			if ( false === $meta && get_transient( $error_key ) ) {
				$this->log( "Skipping theme update check for '{$c['slug']}' — previous error cached" );
				return $trans;
			}

			if ( false === $meta ) {
				if ( $this->should_use_github_release_mode() ) {
					$repo_url  = rtrim( $c['server'], '/' );
					$cache_key = 'uupd_github_release_' . ( $c['instance_key'] ?? self::build_instance_key( $vendor, $c['slug'] ) ) . '_' . md5( $repo_url );
					$release   = get_transient( $cache_key );

					if ( false === $release ) {
						$api_url = $this->github_latest_release_api_url( $repo_url );

						$token = self::apply_filters_scoped(
							'uupd/github_token_override',
							$c['github_token'] ?? '',
							$vendor,
							$c['slug'] ?? ''
						);

						$headers = [
							'Accept'     => 'application/vnd.github.v3+json',
							'User-Agent' => 'WordPress-UUPD',
						];

						if ( $token ) {
							$headers['Authorization'] = 'token ' . $token;
						}

						$this->log( " GitHub fetch: $api_url" );
						$response = wp_remote_get( $api_url, [ 'headers' => $headers, 'timeout' => 15 ] );

						if ( ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) === 200 ) {
							$release = json_decode( wp_remote_retrieve_body( $response ) );
							$ttl = self::apply_filters_scoped( 'uupd_success_cache_ttl', 6 * HOUR_IN_SECONDS, $vendor, $c['slug'] );
							set_transient( $cache_key, $release, $ttl );
						} else {
							$msg = is_wp_error( $response ) ? $response->get_error_message() : ( 'HTTP ' . wp_remote_retrieve_response_code( $response ) );
							$this->log( "✗ GitHub API failed — {$msg} — caching error state" );

							set_transient(
								$error_key,
								time(),
								self::apply_filters_scoped( 'uupd_fetch_remote_error_ttl', 6 * HOUR_IN_SECONDS, $vendor, $c['slug'] )
							);

							self::do_metadata_failure_actions( $vendor, $c['slug'], [
								'vendor'  => $vendor,
								'slug'    => $c['slug'],
								'server'  => $repo_url,
								'message' => $msg,
							] );
							return $trans;
						}
					}

					if ( isset( $release->tag_name ) ) {
						$zip_url = $this->github_release_download_url( $repo_url, $release );

						$meta = (object) [
							'version'      => ltrim( (string) $release->tag_name, 'v' ),
							'download_url' => $zip_url,
							'homepage'     => $release->html_url ?? $repo_url,
							'sections'     => [ 'changelog' => $release->body ?? '' ],
						];
					} else {
						$meta = (object) [
							'version'      => $c['version'],
							'download_url' => '',
							'homepage'     => $repo_url,
							'sections'     => [ 'changelog' => '' ],
						];
					}

					$meta = $this->apply_visual_assets_to_meta( $meta );

					set_transient(
						$cache_id,
						$meta,
						self::apply_filters_scoped( 'uupd_success_cache_ttl', 6 * HOUR_IN_SECONDS, $vendor, $c['slug'] )
					);

					delete_transient( $error_key );
				} else {
					$this->fetch_remote();
					$meta = get_transient( $cache_id );

					if ( $meta ) {
						$meta = $this->apply_visual_assets_to_meta( $meta );
						set_transient(
							$cache_id,
							$meta,
							self::apply_filters_scoped( 'uupd_success_cache_ttl', 6 * HOUR_IN_SECONDS, $vendor, $c['slug'] ?? $slug )
						);
					}
				}
			}

			if ( ! $meta ) {
				$this->log( 'No metadata found, skipping update logic.' );
				return $trans;
			}

			$resolved_pkg = $this->resolve_download_url( $meta );

			if ( $resolved_pkg && empty( $meta->download_url ) ) {
				$meta->download_url = $resolved_pkg;
			}

			$this->log( 'Resolved package URL (normalized): ' . ( $resolved_pkg ? $resolved_pkg : 'EMPTY' ) );

			$base_info = [
				'theme'        => $slug,
				'url'          => $meta->homepage ?? '',
				'requires'     => $meta->requires ?? $meta->min_wp_version ?? '',
				'requires_php' => $meta->requires_php ?? '',
				'screenshot'   => $meta->screenshot ?? '',
				'tested'       => $meta->tested ?? '',
			];

			$remote_version   = $meta->version ?? '0.0.0';
			$allow_prerelease = $this->config['allow_prerelease'] ?? false;

			$current_normalized = $this->normalize_version( $current );
			$remote_normalized  = $this->normalize_version( $remote_version );

			$this->log( "Original versions: installed={$current}, remote={$remote_version}" );
			$this->log( "Normalized versions: installed={$current_normalized}, remote={$remote_normalized}" );
			$this->log( "Comparing (normalized): installed={$current_normalized} vs remote={$remote_normalized}" );

			if (
				( ! $allow_prerelease && preg_match( '/^\d+\.\d+\.\d+-(alpha|beta|rc|dev|preview)(?:[.\-]\d+)?$/i', $remote_normalized ) ) ||
				version_compare( $current_normalized, $remote_normalized, '>=' )
			) {
				$this->log( " Theme '{$c['slug']}' is up to date (v{$current})" );
				$trans->no_update[ $slug ] = (object) array_merge(
					$base_info,
					[
						'new_version' => $current,
						'package'     => '',
					]
				);
				return $trans;
			}

			$this->log( " Injecting theme update '{$c['slug']}' → v{$meta->version}" );
			$pkg = $resolved_pkg;
			$this->log( 'Resolved package URL: ' . ( $pkg ? $pkg : 'EMPTY' ) );

			$trans->response[ $slug ] = array_merge(
				$base_info,
				[
					'new_version' => $meta->version ?? $current,
					'package'     => $pkg,
				]
			);

			unset( $trans->no_update[ $slug ] );
			return $trans;
		}

		/** Provide plugin information for the details popup. */
		public function plugin_info( $res, $action, $args ) {
			$c = $this->config;
			if ( 'plugin_information' !== $action || $args->slug !== $c['slug'] ) {
				return $res;
			}

			$prefix = $c['cache_prefix'] ?? 'uupd_' . ( $c['vendor'] ?? '' ) . '__';
			$meta   = get_transient( $prefix . $c['slug'] );
			if ( ! $meta ) {
				return $res;
			}

			$sections = [];
			if ( isset( $meta->sections ) ) {
				foreach ( (array) $meta->sections as $key => $content ) {
					$sections[ $key ] = $content;
				}
			}

			return (object) [
				'name'            => $c['name'],
				'title'           => $c['name'],
				'slug'            => $c['slug'],
				'version'         => $meta->version ?? '',
				'author'          => $meta->author ?? '',
				'author_homepage' => $meta->author_homepage ?? '',
				'requires'        => $meta->requires ?? $meta->min_wp_version ?? '',
				'tested'          => $meta->tested ?? '',
				'requires_php'    => $meta->requires_php ?? '',
				'last_updated'    => $meta->last_updated ?? '',
				'download_link'   => $this->resolve_download_url( $meta ),
				'homepage'        => $meta->homepage ?? '',
				'sections'        => $sections,
				'icons'           => isset( $meta->icons ) ? (array) $meta->icons : [],
				'banners'         => isset( $meta->banners ) ? (array) $meta->banners : [],
				'screenshots'     => isset( $meta->screenshots ) ? (array) $meta->screenshots : [],
			];
		}

		/** Provide theme information for the details popup. */
		public function theme_info( $res, $action, $args ) {
			$c    = $this->config;
			$slug = $c['real_slug'] ?? $c['slug'];

			if ( 'theme_information' !== $action || $args->slug !== $slug ) {
				return $res;
			}

			$prefix = $c['cache_prefix'] ?? 'uupd_' . ( $c['vendor'] ?? '' ) . '__';
			$meta   = get_transient( $prefix . $c['slug'] );
			if ( ! $meta ) {
				return $res;
			}

			if ( isset( $meta->changelog_html ) ) {
				$changelog = $meta->changelog_html;
			} elseif ( isset( $meta->sections ) ) {
				if ( is_array( $meta->sections ) ) {
					$changelog = $meta->sections['changelog'] ?? '';
				} elseif ( is_object( $meta->sections ) ) {
					$changelog = $meta->sections->changelog ?? '';
				} else {
					$changelog = '';
				}
			} else {
				$changelog = '';
			}

			return (object) [
				'name'          => $c['name'],
				'slug'          => $c['real_slug'] ?? $c['slug'],
				'version'       => $meta->version ?? '',
				'tested'        => $meta->tested ?? '',
				'requires'      => $meta->min_wp_version ?? '',
				'sections'      => [ 'changelog' => $changelog ],
				'download_link' => $this->resolve_download_url( $meta ),
				'icons'         => isset( $meta->icons ) ? (array) $meta->icons : [],
				'banners'       => isset( $meta->banners ) ? (array) $meta->banners : [],
			];
		}

		/** Optional debug logger. */
		private function log( $msg ) {
			$slug = $this->config['slug'] ?? '';

			if ( apply_filters( 'updater_enable_debug', false, $slug ) ) {
				error_log( "[Updater][{$slug}] {$msg}" );
				do_action( 'uupd/log', $msg, $slug );
			}
		}

		private function is_github_repo_root_url( $url ) {
			$url = trim( (string) $url );
			if ( $url === '' ) {
				return false;
			}

			$parts = wp_parse_url( $url );
			if ( empty( $parts['host'] ) ) {
				return false;
			}

			if ( strtolower( $parts['host'] ) !== 'github.com' ) {
				return false;
			}

			$path = trim( $parts['path'] ?? '', '/' );
			if ( $path === '' ) {
				return false;
			}

			$segments = explode( '/', $path );
			return count( $segments ) === 2;
		}

		private function get_mode() {
			$mode = $this->config['mode'] ?? 'auto';
			$mode = strtolower( trim( (string) $mode ) );
			return in_array( $mode, [ 'auto', 'json', 'github_release' ], true ) ? $mode : 'auto';
		}

		private function should_use_github_release_mode() {
			$mode   = $this->get_mode();
			$server = $this->config['server'] ?? '';

			if ( $mode === 'json' ) {
				return false;
			}
			if ( $mode === 'github_release' ) {
				return true;
			}

			return $this->is_github_repo_root_url( $server );
		}

		/**
		 * Add GitHub auth headers for downloads/metadata when a scoped token is configured.
		 *
		 * @param array  $args Request args.
		 * @param string $url  Request URL.
		 * @return array
		 */
		private function add_github_auth_headers_for_download( $args, $url ) {
			$vendor = $this->config['vendor'] ?? '';
			$slug   = $this->config['slug'] ?? '';

			$token = self::apply_filters_scoped(
				'uupd/github_token_override',
				$this->config['github_token'] ?? '',
				$vendor,
				$slug
			);

			if ( ! $token ) {
				return $args;
			}

			$args['headers'] = $args['headers'] ?? [];
			$args['headers']['Authorization'] = 'token ' . $token;
			$args['headers']['User-Agent']    = $args['headers']['User-Agent'] ?? 'WordPress-UUPD';

			if ( strpos( $url, 'api.github.com/repos/' ) !== false && strpos( $url, '/releases/assets/' ) !== false ) {
				$args['headers']['Accept'] = 'application/octet-stream';
			}

			return $args;
		}

		/**
		 * Determine which asset name to pick from a GitHub release.
		 *
		 * Priority:
		 *  1) config['github_asset_name']
		 *  2) config['slug'] . '.zip'
		 *  3) config['real_slug'] . '.zip'
		 *  4) null (means first .zip asset)
		 *
		 * @return string|null
		 */
		private function get_github_asset_name() {
			$c = $this->config;

			if ( ! empty( $c['github_asset_name'] ) ) {
				return (string) $c['github_asset_name'];
			}

			if ( ! empty( $c['slug'] ) ) {
				return (string) $c['slug'] . '.zip';
			}

			if ( ! empty( $c['real_slug'] ) ) {
				return (string) $c['real_slug'] . '.zip';
			}

			return null;
		}

		/**
		 * Build the GitHub API URL for /releases/latest from a repo root URL.
		 *
		 * @param string $repo_url GitHub repo root URL.
		 * @return string
		 */
		private function github_latest_release_api_url( $repo_url ) {
			$repo_url = rtrim( (string) $repo_url, '/' );
			$path     = trim( (string) wp_parse_url( $repo_url, PHP_URL_PATH ), '/' );
			return "https://api.github.com/repos/{$path}/releases/latest";
		}

		/**
		 * Resolve the download URL for a release:
		 * - Prefer a matching .zip asset
		 * - If a token is available, prefer the private-safe API asset endpoint
		 * - Otherwise fall back to browser_download_url
		 * - Finally fall back to zipball_url
		 *
		 * @param string $repo_url Repo root URL.
		 * @param object $release  Release payload.
		 * @return string
		 */
		private function github_release_download_url( $repo_url, $release ) {
			$repo_url = rtrim( (string) $repo_url, '/' );
			$path     = trim( (string) wp_parse_url( $repo_url, PHP_URL_PATH ), '/' );

			$vendor = $this->config['vendor'] ?? '';
			$slug   = $this->config['slug'] ?? '';

			$token = self::apply_filters_scoped(
				'uupd/github_token_override',
				$this->config['github_token'] ?? '',
				$vendor,
				$slug
			);
			$use_api_assets = ! empty( $token );

			$wanted    = $this->get_github_asset_name();
			$wanted_lc = $wanted ? strtolower( $wanted ) : null;

			if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
				if ( $wanted_lc ) {
					foreach ( $release->assets as $asset ) {
						if ( ! empty( $asset->name ) && strtolower( (string) $asset->name ) === $wanted_lc ) {
							if ( $use_api_assets && ! empty( $asset->id ) ) {
								return "https://api.github.com/repos/{$path}/releases/assets/{$asset->id}";
							}
							return $asset->browser_download_url ?? '';
						}
					}
				}

				foreach ( $release->assets as $asset ) {
					if ( ! empty( $asset->name ) && self::ends_with( strtolower( (string) $asset->name ), '.zip' ) ) {
						if ( $use_api_assets && ! empty( $asset->id ) ) {
							return "https://api.github.com/repos/{$path}/releases/assets/{$asset->id}";
						}
						return $asset->browser_download_url ?? '';
					}
				}
			}

			return $release->zipball_url ?? '';
		}

		private static function ends_with( $haystack, $needle ) {
			if ( function_exists( 'str_ends_with' ) ) {
				return \str_ends_with( (string) $haystack, (string) $needle );
			}
			$haystack = (string) $haystack;
			$needle   = (string) $needle;
			if ( $needle === '' ) {
				return true;
			}
			if ( strlen( $needle ) > strlen( $haystack ) ) {
				return false;
			}
			return substr( $haystack, -strlen( $needle ) ) === $needle;
		}

		/**
		 * Register the updater and the manual-check action.
		 *
		 * @param array $config Updater config.
		 * @return void
		 */
		public static function register( array $config ) {
			$config['vendor'] = self::sanitize_identity_part( $config['vendor'] ?? '' );
			$config['slug']   = self::sanitize_identity_part( $config['slug'] ?? '' );

			if ( $config['vendor'] === '' || $config['slug'] === '' ) {
				_doing_it_wrong( __METHOD__, __( 'Updater_V2::register() requires both vendor and slug.', 'default' ), self::VERSION );
				return;
			}

			$config['instance_key'] = self::build_instance_key( $config['vendor'], $config['slug'] );
			$config['cache_prefix'] = $config['cache_prefix'] ?? 'uupd_' . $config['vendor'] . '__';

			new self( $config );

			$our_file   = $config['plugin_file'] ?? null;
			$slug       = $config['slug'];
			$vendor     = $config['vendor'];
			$textdomain = ! empty( $config['textdomain'] ) ? $config['textdomain'] : $slug;

			if ( $our_file ) {
				add_filter(
					'plugin_row_meta',
					function( array $links, string $file, array $plugin_data ) use ( $our_file, $vendor, $slug, $textdomain ) {
						if ( $file === $our_file ) {
							$nonce     = wp_create_nonce( 'uupd_v2_manual_check_' . $vendor . '__' . $slug );
							$check_url = admin_url(
								sprintf(
									'admin.php?action=uupd_v2_manual_check&vendor=%s&slug=%s&_wpnonce=%s',
									rawurlencode( $vendor ),
									rawurlencode( $slug ),
									rawurlencode( $nonce )
								)
							);

							$links[] = sprintf(
								'<a href="%s">%s</a>',
								esc_url( $check_url ),
								esc_html__( 'Check for updates', $textdomain )
							);
						}
						return $links;
					},
					10,
					3
				);
			}

			add_action(
				'admin_action_uupd_v2_manual_check',
				function() use ( $vendor, $slug, $config ) {
					$request_vendor = isset( $_REQUEST['vendor'] ) ? sanitize_key( wp_unslash( $_REQUEST['vendor'] ) ) : '';
					$request_slug   = isset( $_REQUEST['slug'] ) ? sanitize_key( wp_unslash( $_REQUEST['slug'] ) ) : '';

					if ( $request_vendor !== $vendor || $request_slug !== $slug ) {
						return;
					}

					if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'update_themes' ) ) {
						wp_die( __( 'Cheatin’ uh?' ) );
					}

					$nonce     = isset( $_REQUEST['_wpnonce'] ) ? wp_unslash( $_REQUEST['_wpnonce'] ) : '';
					$checkname = 'uupd_v2_manual_check_' . $vendor . '__' . $slug;
					if ( ! wp_verify_nonce( $nonce, $checkname ) ) {
						wp_die( __( 'Security check failed.' ) );
					}

					$prefix = $config['cache_prefix'] ?? 'uupd_' . $vendor . '__';
					delete_transient( $prefix . $slug );
					delete_transient( $prefix . $slug . '_error' );

					if ( isset( $config['server'] ) && strpos( $config['server'], 'github.com' ) !== false ) {
						$repo_url = rtrim( $config['server'], '/' );
						$gh_key   = 'uupd_github_release_' . self::build_instance_key( $vendor, $slug ) . '_' . md5( $repo_url );
						delete_transient( $gh_key );
					}

					if ( ! empty( $config['plugin_file'] ) ) {
						wp_update_plugins();
						$redirect = wp_get_referer() ?: admin_url( 'plugins.php' );
					} else {
						wp_update_themes();
						$redirect = wp_get_referer() ?: admin_url( 'themes.php' );
					}

					$redirect = self::apply_filters_scoped( 'uupd/manual_check_redirect', $redirect, $vendor, $slug );
					wp_safe_redirect( $redirect );
					exit;
				}
			);
		}
	}
}
