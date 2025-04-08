<?php
/**
 * Bootstrap module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Core;

use SesamyPlugin\Api\Client;
use SesamyPlugin\Api\Rest;
use SesamyPlugin\Frontend\Renderers\ContentRenderer;
use SesamyPlugin\Frontend\Shortcodes;
use SesamyPlugin\Models\Meta;
use SesamyPlugin\Admin\Controllers\SettingsController;

/**
 * Bootstrap module for proper loading order and dependencies.
 *
 * @package Sesamy2
 */
class Bootstrap {
	/**
	 * Initialize the Bootstrap module.
	 *
	 * @return self
	 */
	public static function init() {
		$instance = new self();
		$instance->register();
		return $instance;
	}

	/**
	 * Register components in the proper order.
	 *
	 * @return void
	 */
	public function register() {
		// Load helpers
		$this->load_helpers();

		// Initialize core components
		Assets::init();
		ContentManager::init();

		// Initialize models
		Meta::init();

		// Initialize API components
		Client::init();
		Rest::init();

		// Initialize frontend components
		ContentRenderer::init();
		Shortcodes::init();

		// Initialize admin components if in admin area
		if ( is_admin() ) {
			$this->load_admin_components();
		}
	}

	/**
	 * Load helper functions.
	 *
	 * @return void
	 */
	private function load_helpers() {
		require_once SESAMY_PLUGIN_PATH . 'src/Support/helpers.php';
	}

	/**
	 * Load admin components.
	 *
	 * @return void
	 */
	private function load_admin_components() {
		SettingsController::init();
	}
}
