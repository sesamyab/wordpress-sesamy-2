<?php
/**
 * Assets module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin;

use function SesamyPlugin\Helpers\get_enabled_post_types;
use function SesamyPlugin\Helpers\is_config_valid;
use function SesamyPlugin\Helpers\get_sesamy_setting;

/**
 * Assets module.
 *
 * @package Sesamy2
 */
class Assets {

	/**
	 * Register any hooks and filters.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_enqueue_scripts', [ static::class, 'admin_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ static::class, 'admin_styles' ] );
		add_action( 'enqueue_block_editor_assets', [ static::class, 'block_editor_scripts' ] );

		add_action( 'wp_enqueue_scripts', [ static::class, 'sesamy_scripts' ] );
	}

	/**
	 * Enqueue scripts for admin.
	 *
	 * @return void
	 */
	public static function admin_scripts() {
		wp_enqueue_script(
			'sesamy_plugin_admin',
			SESAMY_PLUGIN_URL . 'dist/js/admin.js',
			[],
			SESAMY_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Enqueue styles for admin.
	 *
	 * @return void
	 */
	public static function admin_styles() {
		wp_enqueue_style(
			'sesamy_plugin_admin',
			SESAMY_PLUGIN_URL . 'dist/css/admin.css',
			[],
			SESAMY_PLUGIN_VERSION
		);
	}

	/**
	 * Enqueue Sesamy scripts.
	 *
	 * @return void
	 */
	public static function sesamy_scripts() {
		if ( ! is_config_valid() ) {
			return;
		}
		$client_id = get_sesamy_setting( 'client_id' );

		$dev_mode            = get_sesamy_setting( 'development_mode' );
		$sesamy_scripts_host = $dev_mode ? 'https://scripts.sesamy.dev' : 'https://scripts.sesamy.com';

		wp_enqueue_script_module(
			'sesamy_bundle',
			$sesamy_scripts_host . '/s/' . $client_id . '/bundle',
			[],
			SESAMY_PLUGIN_VERSION
		);
	}

	/**
	 * Enqueue scripts for the block editor.
	 *
	 * @return void
	 */
	public static function block_editor_scripts() {
		global $post;
		$enabled_post_types = get_enabled_post_types();
		if ( isset( $post ) && is_config_valid() && in_array( $post->post_type, $enabled_post_types, true ) ) {
			wp_enqueue_script(
				'sesamy_plugin_post_settings',
				SESAMY_PLUGIN_URL . 'dist/js/post-settings.js',
				[],
				SESAMY_PLUGIN_VERSION,
				true
			);
		}
	}
}
