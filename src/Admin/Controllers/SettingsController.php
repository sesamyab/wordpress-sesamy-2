<?php
/**
 * Settings Controller.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\Controllers;

use SesamyPlugin\Admin\Settings\Core as SettingsCore;
use SesamyPlugin\Admin\Settings\Post as SettingsPost;
use SesamyPlugin\Admin\View\Settings as SettingsView;

/**
 * Settings Controller handles initialization of all settings components.
 *
 * @package Sesamy2
 */
class SettingsController {
	/**
	 * Initialize the Settings controller.
	 *
	 * @return self
	 */
	public static function init() {
		$instance = new self();
		$instance->register();
		return $instance;
	}

	/**
	 * Register controller components.
	 *
	 * @return void
	 */
	public function register() {
		// Initialize core settings
		SettingsCore::init();

		// Initialize post settings
		SettingsPost::init();

		// Initialize settings view
		SettingsView::init();
	}
}
