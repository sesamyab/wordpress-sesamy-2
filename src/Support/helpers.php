<?php
/**
 * Helpers module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Helpers;

/**
 * Defaults applied when a sesamy_settings sub-key has never been saved.
 * Centralised here so the UI renderers, runtime consumers, and any future
 * REST endpoints all see the same baseline. Renderers that allow unsetting
 * (e.g. the checkbox) submit an explicit `0` so the default doesn't keep
 * re-asserting itself after the user opts out.
 *
 * @return array<string, mixed>
 */
function sesamy_setting_defaults() {
	return [
		'lock_mode'             => 'capsule',
		'use_first_party_proxy' => true,
	];
}

/**
 * Get Sesamy setting.
 *
 * @param string $key The key of the setting to retrieve.
 * @return mixed The value of the setting, or the documented default for the
 *               key if absent, or null if neither.
 */
function get_sesamy_setting( $key ) {
	$options = get_option( 'sesamy_settings' );
	if ( is_array( $options ) && array_key_exists( $key, $options ) ) {
		return $options[ $key ];
	}
	$defaults = sesamy_setting_defaults();
	return $defaults[ $key ] ?? null;
}

/**
 * The publisher domain advertised as `iss` in resourceJWT, in capsule AAD,
 * and used as the path segment when registering with the api-proxy. Single
 * source of truth so the value the issuer verifies against (`Capsule\Service`)
 * matches the one we send to `PUT /management/capsule/publishers/{domain}`
 * (`Capsule\Registration`) — drift here means the issuer rejects every
 * unlock.
 *
 * @return string
 */
function publisher_domain() {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$host = strtolower( rtrim( $host, '.' ) );
	return '' !== $host ? $host : 'localhost';
}

/**
 * Whether this WordPress install is a local/dev environment. Mirrors the
 * detection used in `sesamy2.php` to load fast-refresh.
 *
 * Used to gate developer-only UI (e.g. the "Development mode" toggle pointing
 * at Sesamy's `.dev` cluster) so production installs don't see it.
 *
 * @return bool
 */
function is_local_install() {
	if ( in_array( wp_get_environment_type(), [ 'local', 'development' ], true ) ) {
		return true;
	}
	$home = (string) home_url();
	return false !== strpos( $home, '.test' )
		|| false !== strpos( $home, '.local' )
		|| false !== strpos( $home, '://localhost' )
		|| false !== strpos( $home, '://127.0.0.1' );
}

/**
 * Get enabled post types.
 *
 * @return array
 */
function get_enabled_post_types() {
		return get_sesamy_setting( 'enabled_content_types' ) ?? [];
}

/**
 * Parsed "Automatic locking" term rules from the `locked_terms` setting.
 *
 * Each rule is `['taxonomy' => string, 'term_id' => int]`. The object shape is
 * deliberate — a future per-term `pass` key (capsule scope mapping) can be
 * added without a settings migration.
 *
 * @return array<int, array{taxonomy: string, term_id: int}>
 */
function get_locked_term_rules() {
	$raw = get_sesamy_setting( 'locked_terms' );
	if ( ! is_array( $raw ) ) {
		return [];
	}
	$rules = [];
	foreach ( $raw as $rule ) {
		if ( is_array( $rule ) && ! empty( $rule['taxonomy'] ) && ! empty( $rule['term_id'] ) ) {
			$rules[] = [
				'taxonomy' => (string) $rule['taxonomy'],
				'term_id'  => (int) $rule['term_id'],
			];
		}
	}
	return $rules;
}

/**
 * First "Automatic locking" rule matching the post's terms, or null.
 *
 * Evaluated at read time (`has_term`) rather than synced to post meta, so
 * rules apply retroactively and removing a term immediately unlocks.
 *
 * @param \WP_Post|int $post Post object or id.
 * @return array{taxonomy: string, term_id: int}|null
 */
function get_post_locking_term( $post ) {
	$post_id = $post instanceof \WP_Post ? (int) $post->ID : (int) $post;
	foreach ( get_locked_term_rules() as $rule ) {
		if ( has_term( $rule['term_id'], $rule['taxonomy'], $post_id ) ) {
			return $rule;
		}
	}
	return null;
}

/**
 * Effective lock state for a post — the single source of truth consumed by
 * rendering, head meta tags, REST, and admin UI.
 *
 * Locked when the per-post `_sesamy_locked` meta is set OR the post has a
 * term selected under "Automatic locking" (additive; the rule never writes
 * post meta). The result passes through the `sesamy_is_post_locked` filter
 * as an escape hatch for bespoke lock rules.
 *
 * @param \WP_Post|int $post Post object or id.
 * @return bool
 */
function is_post_locked( $post ) {
	$post_id = $post instanceof \WP_Post ? (int) $post->ID : (int) $post;
	$locked  = ! empty( get_post_meta( $post_id, '_sesamy_locked', true ) );
	if ( ! $locked && null !== get_post_locking_term( $post_id ) ) {
		$locked = true;
	}
	return (bool) apply_filters( 'sesamy_is_post_locked', $locked, $post_id );
}

/**
 * Get the stored Sesamy connection bundle written at the end of the connect flow.
 *
 * Synthesizes a stub bundle from legacy `sesamy_settings.client_id` when no
 * `sesamy_connection` option exists. Pre-rewrite installs stored the publisher
 * identifier under `sesamy_settings.client_id` and used it as the script-host
 * segment — so the same value seeds both `client_id` (read by
 * `get_sesamy_client_id()` to keep `is_config_valid()` true) and `tenant`
 * (read by `get_sesamy_vendor_id()` to feed the frontend bootstrap's
 * `clientId` / bundle URL). Keeps locked articles rendering paywalls across
 * the upgrade without a destructive DB write, so downgrades remain safe and
 * the stub is replaced wholesale once the publisher runs Connect.
 *
 * `integration_type = 'legacy_upgrade'` marks the stub so callers can
 * distinguish it from a real connection. `is_sesamy_connected()` keys off
 * this to return false on legacy installs — the settings page then shows
 * "Connect to Sesamy" and skips the publisher-registration panel (which
 * needs M2M credentials the stub doesn't have). Disconnect-revoke is also a
 * no-op for legacy stubs since `registration_client_uri` /
 * `registration_access_token` are absent.
 *
 * @return array<string, mixed>|null
 */
function get_sesamy_connection() {
	$connection = get_option( 'sesamy_connection' );
	if ( is_array( $connection ) ) {
		return $connection;
	}
	$legacy = get_option( 'sesamy_settings' );
	if ( is_array( $legacy ) && ! empty( $legacy['client_id'] ) ) {
		return [
			'client_id'        => (string) $legacy['client_id'],
			'tenant'           => (string) $legacy['client_id'],
			'environment'      => ! empty( $legacy['development_mode'] ) ? 'dev' : 'prod',
			'integration_type' => 'legacy_upgrade',
		];
	}
	return null;
}

/**
 * Get the connected Sesamy client_id, or null if not connected.
 *
 * @return string|null
 */
function get_sesamy_client_id() {
	$connection = get_sesamy_connection();
	return ! empty( $connection['client_id'] ) ? (string) $connection['client_id'] : null;
}

/**
 * Get the publisher's Sesamy vendor id (AuthHero tenant) used by the
 * front-end bundle. Distinct from `client_id`, which is the backend M2M
 * credential — the vendor id is stable across credential rotations and is
 * what the script host and Sesamy JS use to identify the publisher.
 *
 * @return string|null
 */
function get_sesamy_vendor_id() {
	$connection = get_sesamy_connection();
	return ! empty( $connection['tenant'] ) ? (string) $connection['tenant'] : null;
}

/**
 * Whether the site has completed the Sesamy connect flow.
 *
 * Legacy stubs synthesised by `get_sesamy_connection()` deliberately fail
 * this check — they lack the M2M credentials needed for server-side calls
 * (publisher registration, capsule, management API), so treating them as
 * "connected" on the settings page produces a misleading status alongside
 * the API errors those modules raise. Returning false here surfaces the
 * Connect button instead and hides the publisher-registration panel.
 *
 * @return bool
 */
function is_sesamy_connected() {
	$connection = get_sesamy_connection();
	if ( ! is_array( $connection ) || empty( $connection['client_id'] ) ) {
		return false;
	}
	return 'legacy_upgrade' !== ( $connection['integration_type'] ?? '' );
}

/**
 * Get the active Sesamy routing mode.
 *
 * The settings checkbox `use_first_party_proxy` is the source of truth for
 * direct vs proxied routing. When unset (legacy installs, or before the
 * publisher visits the settings page), fall back to `sesamy_routing.mode`
 * — ConnectFlow seeds that to `wordpress_proxy` on first connect, so a
 * fresh install lands in proxy mode without the publisher having to opt in.
 * Stage 1 recognises `direct` and `wordpress_proxy`; later stages will add
 * `cloudflare_worker` and `sesamy_managed`.
 *
 * @return string
 */
function get_sesamy_routing_mode() {
	$toggle = get_sesamy_setting( 'use_first_party_proxy' );
	if ( null !== $toggle ) {
		return $toggle ? 'wordpress_proxy' : 'direct';
	}
	$routing = get_option( 'sesamy_routing', [] );
	return is_array( $routing ) && ! empty( $routing['mode'] ) ? (string) $routing['mode'] : 'direct';
}

/**
 * Resolve the active Sesamy cluster (`'dev'` or `'prod'`).
 *
 * Prefers the cluster bound to the stored connection — that's the cluster the
 * `client_id` was registered on and the only one whose token endpoint will
 * recognise it. Falls back to the `development_mode` setting when no
 * connection is stored yet (pre-connect URL building) and finally to `prod`.
 *
 * @return string
 */
function get_sesamy_environment() {
	$connection = get_sesamy_connection();
	if ( is_array( $connection ) ) {
		if ( ! empty( $connection['environment'] ) ) {
			return 'dev' === $connection['environment'] ? 'dev' : 'prod';
		}
		// Pre-fix connections didn't persist `environment`. Infer it from the
		// AuthHero-returned `registration_client_uri`, which is the only field
		// in the bundle that carries the cluster host. Avoids forcing a
		// disconnect/reconnect on installs that connected before the fix.
		if ( ! empty( $connection['registration_client_uri'] ) ) {
			$host = (string) wp_parse_url( (string) $connection['registration_client_uri'], PHP_URL_HOST );
			if ( '' !== $host && str_ends_with( $host, '.sesamy.dev' ) ) {
				return 'dev';
			}
			if ( '' !== $host && str_ends_with( $host, '.sesamy.com' ) ) {
				return 'prod';
			}
		}
	}
	return get_sesamy_setting( 'development_mode' ) ? 'dev' : 'prod';
}

/**
 * Build a URL pointing at a Sesamy service.
 *
 * `$type` selects the destination service. Hosts switch from the `.com`
 * production cluster to the `.dev` staging cluster when the
 * `development_mode` setting is enabled:
 *   - api      → https://api2.sesamy.{com,dev}
 *   - auth     → https://auth2.sesamy.{com,dev}
 *   - scripts  → https://scripts.sesamy.{com,dev}
 *   - assets   → https://js.sesamy.{com,dev}
 *   - checkout → https://checkout.sesamy.{com,dev}
 *   - embed    → https://embed.sesamy.{com,dev}
 *
 * In `wordpress_proxy` mode, `api` and `auth` route through `home_url('/sesamy/...')`
 * for first-party cookie semantics. The `scripts` bundle is always fetched direct —
 * the bundle itself encodes the proxied API/auth bases at runtime via its
 * `wordpress-` vendor tag.
 *
 * Pass `$direct = true` to bypass the proxy and always return the upstream
 * URL. Use this for server-to-server PHP calls (the WP server doesn't need
 * first-party cookies to talk to Sesamy) and for admin-only browser flows
 * like the connect/DCR handshake, where routing through the proxy just adds
 * a hop and clutters the URL bar.
 *
 * @param string $path   Path relative to the service root, with or without leading slash.
 * @param string $type   Service type. Defaults to `api`.
 * @param bool   $direct When true, ignore proxy mode and return the upstream URL.
 * @return string Absolute URL.
 */
function sesamy_url( $path, $type = 'api', $direct = false ) {
	$tld   = 'dev' === get_sesamy_environment() ? 'dev' : 'com';
	$bases = [
		'api'      => "https://api2.sesamy.{$tld}",
		'auth'     => "https://auth2.sesamy.{$tld}",
		'scripts'  => "https://scripts.sesamy.{$tld}",
		'assets'   => "https://js.sesamy.{$tld}",
		'checkout' => "https://checkout.sesamy.{$tld}",
		'embed'    => "https://embed.sesamy.{$tld}",
	];

	$proxied_types = [ 'api', 'auth' ];
	$mode          = get_sesamy_routing_mode();

	if ( ! $direct && 'wordpress_proxy' === $mode && in_array( $type, $proxied_types, true ) ) {
		$routing    = get_option( 'sesamy_routing', [] );
		$proxy_base = rtrim( home_url( $routing['proxy_base_path'] ?? '/sesamy' ), '/' );
		return $proxy_base . '/' . $type . '/' . ltrim( (string) $path, '/' );
	}

	$base = $bases[ $type ] ?? $bases['api'];
	return $base . '/' . ltrim( (string) $path, '/' );
}

/**
 * Is config valid?
 *
 * Frontend gate — true whenever we have *any* form of publisher identifier
 * (real connection or legacy stub) and the gating settings are populated.
 * Deliberately broader than `is_sesamy_connected()`: a legacy upgrader has no
 * M2M credentials but still has the vendor id needed to load the script
 * bundle and render paywalls. The strict check belongs to the settings page,
 * not the reader-facing render path.
 *
 * @return bool
 */
function is_config_valid() {
	return null !== get_sesamy_client_id() && ! empty( get_sesamy_setting( 'default_paywall' ) ) && ! empty( get_sesamy_setting( 'enabled_content_types' ) ) && ! empty( get_sesamy_setting( 'lock_mode' ) );
}
