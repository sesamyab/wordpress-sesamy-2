<?php
/**
 * Plugin Core module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Core;

/**
 * Plugin Core module.
 *
 * @package Sesamy2
 */
class Plugin {

	/**
	 * Default setup routine
	 *
	 * @return void
	 */
	public function setup() {
		add_action( 'init', [ $this, 'initialize_plugin' ], apply_filters( 'sesamy_plugin_init_priority', 8 ) );

		do_action( 'sesamy_plugin_loaded' );
	}

	/**
	 * Initializes the plugin and fires an action other plugins can hook into.
	 *
	 * @return void
	 */
	public function initialize_plugin() {
		do_action( 'sesamy_plugin_init' );

		// Init class interfaces
		\SesamyPlugin\Admin\Controllers\SettingsController::init();
		\SesamyPlugin\Core\ContentManager::init();
		\SesamyPlugin\Models\Meta::init();
		\SesamyPlugin\Core\Assets::init();
		\SesamyPlugin\Api\Rest::init();
	}

	/**
	 * Activate the plugin
	 *
	 * @return void
	 */
	public function activate() {
		// First load the init scripts in case any rewrite functionality is being loaded
		$this->initialize_plugin();
		flush_rewrite_rules();
	}

	/**
	 * Deactivate the plugin
	 *
	 * Uninstall routines should be in uninstall.php
	 *
	 * @return void
	 */
	public function deactivate() {
		// Do nothing.
	}

	/**
	 * Initialize the Plugin module.
	 *
	 * @return self
	 */
	public static function init() {
		$instance = new self();
		$instance->setup();
		return $instance;
	}
}
