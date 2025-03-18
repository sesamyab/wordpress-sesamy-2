<?php
/**
 * Settings module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin;

use TenupFramework\Module;
use TenupFramework\ModuleInterface;


/**
 * Settings module.
 *
 * @package Sesamy2
 */
class Settings implements ModuleInterface {

	use Module;

	/**
	 * Can this module be registered?
	 *
	 * @return bool
	 */
	public function can_register() {
		return true;
	}

	/**
	 * Register any hooks and filters.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', [ $this, 'register_sesamy_settings' ] );
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_sesamy_settings() {
		register_setting( 'sesamy', 'sesamy_settings' );
	}

	/**
	 * Get enabled post types.
	 *
	 * @return array
	 */
	public function enabled_post_types() {
		$options       = get_option( 'sesamy_settings' );
		$content_types = $options['enabled_content_types'] ?? [];
		return $content_types;
	}
}
