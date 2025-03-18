<?php
/**
 * Assets module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin;

use TenupFramework\Assets\GetAssetInfo;
use TenupFramework\Module;
use TenupFramework\ModuleInterface;

use function SesamyPlugin\Helpers\is_config_valid;
use function SesamyPlugin\Helpers\get_sesamy_setting;

/**
 * Assets module.
 *
 * @package Sesamy2
 */
class Assets implements ModuleInterface {

	use Module;
	use GetAssetInfo;

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
		$this->setup_asset_vars(
			dist_path: SESAMY_PLUGIN_PATH . 'dist/',
			fallback_version: SESAMY_PLUGIN_VERSION,
		);

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_styles' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'sesamy_scripts' ] );
	}

	/**
	 * Enqueue scripts for admin.
	 *
	 * @return void
	 */
	public function admin_scripts() {
		wp_enqueue_script(
			'sesamy_plugin_admin',
			SESAMY_PLUGIN_URL . 'dist/js/admin.js',
			$this->get_asset_info( 'admin', 'dependencies' ),
			$this->get_asset_info( 'admin', 'version' ),
			true
		);
	}

	/**
	 * Enqueue styles for admin.
	 *
	 * @return void
	 */
	public function admin_styles() {
		wp_enqueue_style(
			'sesamy_plugin_admin',
			SESAMY_PLUGIN_URL . 'dist/css/admin.css',
			[],
			$this->get_asset_info( 'admin', 'version' )
		);
	}

	/**
	 * Enqueue Sesamy scripts.
	 *
	 * @return void
	 */
	public function sesamy_scripts() {
		if ( ! is_config_valid() ) {
			return;
		}
		$client_id = get_sesamy_setting( 'client_id' );
		wp_enqueue_script_module(
			'sesamy_bundle',
			'https://scripts.sesamy.dev/s/' . $client_id . '/bundle',
			[],
			SESAMY_PLUGIN_VERSION
		);
	}
}
