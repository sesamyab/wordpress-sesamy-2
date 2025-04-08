<?php
/**
 * Helpers module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Support\Helpers;

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
 * Is config valid?
 *
 * @return bool
 */
function is_config_valid() {
	return ! empty( get_sesamy_setting( 'client_id' ) ) && ! empty( get_sesamy_setting( 'default_paywall' ) ) && ! empty( get_sesamy_setting( 'enabled_content_types' ) ) && ! empty( get_sesamy_setting( 'lock_mode' ) );
}

/**
 * Compare two semantic version strings.
 *
 * @param string $a The first semantic version string.
 * @param string $b The second semantic version string.
 * @return string Returns 'major', 'minor', 'patch', 'same', or 'older' based on the comparison.
 */
function diff_sem_ver( $a, $b ) {
	[$maj_a, $min_a, $patch_a] = array_map( 'intval', explode( '.', $a ) );
	[$maj_b, $min_b, $patch_b] = array_map( 'intval', explode( '.', $b ) );

	if ( $maj_b > $maj_a ) {
		return 'major';
	}
	if ( $maj_b < $maj_a ) {
		return 'older';
	}

	if ( $min_b > $min_a ) {
		return 'minor';
	}
	if ( $min_b < $min_a ) {
		return 'older';
	}

	if ( $patch_b > $patch_a ) {
		return 'patch';
	}
	if ( $patch_b < $patch_a ) {
		return 'older';
	}

	return 'same';
}

/**
 * Fetches the list of releases from the GitHub repository.
 *
 * This function retrieves the release data from the GitHub API and caches it for 3 hours.
 *
 * @return array|false An array of releases if successful, or false on failure.
 */
function get_releases() {
	$transient_key = 'sesamy_releases';
	$cache         = get_transient( $transient_key );
	if ( $cache ) {
		return $cache;
	}

	$response = wp_remote_get( 'https://api.github.com/repos/sesamyab/wordpress-sesamy-2/releases' );
	if ( is_wp_error( $response ) ) {
		return false;
	}

	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) {
		return false;
	}

	$releases = json_decode( $body, true );
	if ( empty( $releases ) || ! is_array( $releases ) ) {
		return false;
	}

	// Cache the result for 3 hours
	set_transient( $transient_key, $releases, 3 * HOUR_IN_SECONDS );

	return $releases;
}

/**
 * Check if an update is available for the plugin.
 *
 * This function fetches the latest release information from the GitHub repository
 * and compares it with the current plugin version to determine if an update is available.
 *
 * @return bool True if an update is available, false otherwise.
 */
function is_update_available() {
	$releases = get_releases();
	if ( empty( $releases ) || ! is_array( $releases ) ) {
		return false;
	}

	$latest_release = str_replace( 'v', '', $releases[0]['name'] );
	$diff           = diff_sem_ver( SESAMY_PLUGIN_VERSION, $latest_release );

	return in_array( $diff, [ 'major', 'minor', 'patch' ], true );
}
