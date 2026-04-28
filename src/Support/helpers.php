<?php
/**
 * Helpers module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Helpers;

/**
 * Get Sesamy setting.
 *
 * @param string $key The key of the setting to retrieve.
 * @return mixed The value of the setting, or null if not found.
 */
function get_sesamy_setting( $key ) {
	$options = get_option( 'sesamy_settings' );
	return $options[ $key ] ?? null;
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
 * Get the stored Sesamy connection bundle written at the end of the connect flow.
 *
 * @return array<string, mixed>|null
 */
function get_sesamy_connection() {
	$connection = get_option( 'sesamy_connection' );
	return is_array( $connection ) ? $connection : null;
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
 * Whether the site has completed the Sesamy connect flow.
 *
 * @return bool
 */
function is_sesamy_connected() {
	return get_sesamy_client_id() !== null;
}

/**
 * Build a URL pointing at a Sesamy service.
 *
 * `$type` selects the destination service. Hosts switch from the `.com`
 * production cluster to the `.dev` staging cluster when the
 * `development_mode` setting is enabled:
 *   - api      → https://api2.sesamy.{com,dev}
 *   - auth     → https://auth2.sesamy.{com,dev}
 *   - assets   → https://js.sesamy.{com,dev}
 *   - checkout → https://checkout.sesamy.{com,dev}
 *   - embed    → https://embed.sesamy.{com,dev}
 *
 * Stage 0 only routes via `direct` mode. Proxy modes land in Stage 2.5.
 *
 * @param string $path Path relative to the service root, with or without leading slash.
 * @param string $type Service type. Defaults to `api`.
 * @return string Absolute URL.
 */
function sesamy_url( $path, $type = 'api' ) {
	$tld   = get_sesamy_setting( 'development_mode' ) ? 'dev' : 'com';
	$bases = [
		'api'      => "https://api2.sesamy.{$tld}",
		'auth'     => "https://auth2.sesamy.{$tld}",
		'assets'   => "https://js.sesamy.{$tld}",
		'checkout' => "https://checkout.sesamy.{$tld}",
		'embed'    => "https://embed.sesamy.{$tld}",
	];

	$routing = get_option( 'sesamy_routing', [] );
	$mode    = $routing['mode'] ?? 'direct';

	if ( 'direct' !== $mode ) {
		$proxy_base = rtrim( home_url( $routing['proxy_base_path'] ?? '/sesamy' ), '/' );
		return $proxy_base . '/' . $type . '/' . ltrim( (string) $path, '/' );
	}

	$base = $bases[ $type ] ?? $bases['api'];
	return $base . '/' . ltrim( (string) $path, '/' );
}

/**
 * Is config valid?
 *
 * Checks both that the publisher has connected via the Stage 0 flow and that
 * the legacy content-gating settings are populated.
 *
 * @return bool
 */
function is_config_valid() {
	return is_sesamy_connected() && ! empty( get_sesamy_setting( 'default_paywall' ) ) && ! empty( get_sesamy_setting( 'enabled_content_types' ) ) && ! empty( get_sesamy_setting( 'lock_mode' ) );
}
