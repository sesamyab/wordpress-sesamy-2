<?php
/**
 * Main plugin initialization.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin;

use SesamyPlugin\Core\Bootstrap;

/**
 * Main plugin class that bootstraps all functionality.
 */
class Plugin {
	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public static function init() {
		// Initialize core components with proper loading order
		Bootstrap::init();
	}
}
