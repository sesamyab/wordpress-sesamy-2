<?php
/**
 * PluginCore module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin;

/**
 * PluginCore module.
 *
 * @package Sesamy2
 */
class PluginCore {

	/**
	 * Default setup routine
	 *
	 * @return void
	 */
	public function setup() {
		add_action( 'init', [ $this, 'init' ], apply_filters( 'sesamy_plugin_init_priority', 8 ) );

		do_action( 'sesamy_plugin_loaded' );
	}

	/**
	 * Initializes the plugin and fires an action other plugins can hook into.
	 *
	 * @return void
	 */
	public function init() {
		do_action( 'sesamy_plugin_init' );

		// Register class interfaces
		\SesamyPlugin\Admin\Settings\Core::register();
		\SesamyPlugin\Admin\Settings\Post::register();
		\SesamyPlugin\Admin\View\Settings::register();
		\SesamyPlugin\ContentContainer::register();
		\SesamyPlugin\Meta::register();
		\SesamyPlugin\Assets::register();
	}

	/**
	 * Activate the plugin
	 *
	 * @return void
	 */
	public function activate() {
		// First load the init scripts in case any rewrite functionality is being loaded
		$this->init();
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
}
