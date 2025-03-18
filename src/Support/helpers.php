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
 * Is config valid?
 *
 * @return bool
 */
function is_config_valid() {
	return ! empty( get_sesamy_setting( 'client_id' ) ) && ! empty( get_sesamy_setting( 'default_paywall' ) ) && ! empty( get_sesamy_setting( 'enabled_content_types' ) ) && ! empty( get_sesamy_setting( 'lock_mode' ) );
}
