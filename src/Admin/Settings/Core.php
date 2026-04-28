<?php
/**
 * Core Settings module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\Settings;

use function SesamyPlugin\Helpers\get_enabled_post_types;
use function SesamyPlugin\Helpers\is_sesamy_connected;

/**
 * Core Settings module.
 *
 * @package Sesamy2
 */
class Core {
	/**
	 * Initialize the module.
	 *
	 * @return self
	 */
	public static function init() {
		$instance = new self();
		$instance->register();
		return $instance;
	}

	/**
	 * Register any hooks and filters.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', [ $this, 'register_sesamy_settings' ] );
		add_action( 'init', [ $this, 'check_post_types_support' ] );
		add_action( 'admin_init', [ $this, 'add_sesamy_setting_fields' ] );
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_sesamy_settings() {
		register_setting(
			'sesamy',
			'sesamy_settings',
			[
				'type'              => 'object',
				'show_in_rest'      => [
					'name'   => 'sesamy_settings',
					'schema' => [
						'type'       => 'object',
						'properties' => [
							'default_currency'      => [
								'type' => 'string',
							],
							'default_price'         => [
								'type' => 'string',
							],
							'default_paywall'       => [
								'type' => 'string',
							],
							'default_pass'          => [
								'type' => 'string',
							],
							'lock_mode'             => [
								'type' => 'string',
							],
							'enabled_content_types' => [
								'type' => 'array',
							],
							'development_mode'      => [
								'type' => 'boolean',
							],
						],
					],
				],
				'sanitize_callback' => [ $this, 'sanitize_sesamy_settings' ],
			]
		);
	}

	/**
	 * Ensure enabled post types support custom fields.
	 *
	 * @return void
	 */
	public function check_post_types_support() {
		$enabled_post_types = get_enabled_post_types();
		if ( $enabled_post_types ) {
			foreach ( $enabled_post_types as $post_type ) {
				$supports = get_all_post_type_supports( $post_type );
				if ( ! isset( $supports['custom-fields'] ) ) {
					add_post_type_support( $post_type, 'custom-fields' );
				}
			}
		}
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input The input settings.
	 * @return array The sanitized settings.
	 */
	public function sanitize_sesamy_settings( $input ) {
		$sanitized_input = [];

		foreach ( $input as $key => $value ) {
			switch ( $key ) {
				case 'default_price':
					$float = floatval( str_replace( ',', '.', $value ) );
					if ( $float > 0 ) {
						$sanitized_input[ $key ] = sanitize_text_field( (string) $float );
					} else {
						add_settings_error( 'sesamy_settings', 'default_price', __( 'Default price must be a positive number.', 'sesamy' ) );
					}
					break;
				case 'enabled_content_types':
					$sanitized_input[ $key ] = array_map( 'sanitize_text_field', (array) $value );
					break;
				case 'development_mode':
					$sanitized_input[ $key ] = rest_sanitize_boolean( $value );
					break;
				default:
					$sanitized_input[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized_input;
	}


	/**
	 * Add settings fields.
	 *
	 * @return void
	 */
	public function add_sesamy_setting_fields() {
		// Settings UI is only meaningful once the publisher has linked their
		// Sesamy account — the paywall list and lock behaviour both depend on
		// the connected client.
		if ( ! is_sesamy_connected() ) {
			return;
		}

		$settings_page_view = new \SesamyPlugin\Admin\View\SettingsPage();

		add_settings_section(
			'section_general',
			'',
			[ $settings_page_view, 'section_general_callback' ],
			'sesamy'
		);

		add_settings_field(
			'enabled_content_types',
			__( 'Content Types', 'sesamy' ),
			[ $settings_page_view, 'settings_render_posttype_list' ],
			'sesamy',
			'section_general',
			[
				'name' => 'enabled_content_types',
			]
		);

		add_settings_field(
			'lock_mode',
			__( 'Lock Mode', 'sesamy' ),
			[ $settings_page_view, 'settings_render_selectfield' ],
			'sesamy',
			'section_general',
			[
				'name'    => 'lock_mode',
				'options' => [
					'encode' => 'Encode',
					'embed'  => 'Embed',
					// 'proxy'  => 'Proxy', TODO: Add proxy support
				],
			]
		);

		add_settings_field(
			'default_paywall',
			__( 'Default Paywall', 'sesamy' ),
			[ $settings_page_view, 'settings_render_paywall_select' ],
			'sesamy',
			'section_general',
			[
				'name'      => 'default_paywall',
				'label_for' => 'default_paywall',
			]
		);
	}
}
