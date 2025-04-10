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

		// Init class interfaces
		\SesamyPlugin\Admin\Settings\Core::init();
		\SesamyPlugin\Admin\Settings\Post::init();
		\SesamyPlugin\Admin\View\ReleaseNotice::init();
		\SesamyPlugin\Admin\View\SettingsPage::init();
		\SesamyPlugin\Api\Rest::init();
		\SesamyPlugin\Core\Assets::init();
		\SesamyPlugin\Frontend\ContentContainer::init();
		\SesamyPlugin\Frontend\Meta::init();
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
