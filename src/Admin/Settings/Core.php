<?php
/**
 * Core Settings module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\Settings;

use function SesamyPlugin\Helpers\get_enabled_post_types;
use function SesamyPlugin\Helpers\is_local_install;
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
							'use_first_party_proxy' => [
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
				case 'use_first_party_proxy':
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
		$settings_page_view = new \SesamyPlugin\Admin\View\SettingsPage();

		// "Pre-connect" section — fields that need to be reachable before the
		// publisher has linked their Sesamy account (so the operator can pick
		// the right cluster before triggering Connect). Rendered separately
		// from the main `section_advanced` block in `SettingsPage::admin_page`.
		add_settings_section(
			'section_pre_connect',
			'',
			[ $settings_page_view, 'section_general_callback' ],
			'sesamy'
		);

		// Switches every Sesamy URL from `.com` to `.dev` (`api2.sesamy.dev`,
		// `auth2.sesamy.dev`, `scripts.sesamy.dev`, …). Must be settable before
		// Connect because Connect needs to know which cluster to target.
		// Hidden on non-local installs — production publishers should never
		// need it, and surfacing it would invite accidental flips.
		if ( is_local_install() ) {
			add_settings_field(
				'development_mode',
				__( 'Development mode', 'sesamy' ),
				[ $settings_page_view, 'settings_render_checkbox' ],
				'sesamy',
				'section_pre_connect',
				[
					'name'      => 'development_mode',
					'label_for' => __( 'Use Sesamy `.dev` cluster instead of production. Reconnect after toggling.', 'sesamy' ),
				]
			);
		}

		// Settings UI is only meaningful once the publisher has linked their
		// Sesamy account — the paywall list and lock behaviour both depend on
		// the connected client.
		if ( ! is_sesamy_connected() ) {
			return;
		}

		add_settings_section(
			'section_general',
			'',
			[ $settings_page_view, 'section_general_callback' ],
			'sesamy'
		);

		// Advanced section is rendered inside a collapsible `<details>` in
		// `SettingsPage::admin_page()`; keeping it as its own section lets
		// us reuse the standard `do_settings_fields()` rendering inside the
		// fold-out block.
		add_settings_section(
			'section_advanced',
			'',
			[ $settings_page_view, 'section_advanced_callback' ],
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

		add_settings_field(
			'default_pass',
			__( 'Default Pass', 'sesamy' ),
			[ $settings_page_view, 'settings_render_pass_select' ],
			'sesamy',
			'section_general',
			[
				'name'      => 'default_pass',
				'label_for' => 'default_pass',
			]
		);

		add_settings_field(
			'lock_mode',
			__( 'Lock Mode', 'sesamy' ),
			[ $settings_page_view, 'settings_render_selectfield' ],
			'sesamy',
			'section_advanced',
			[
				'name'    => 'lock_mode',
				'options' => [
					'capsule' => 'Server-side encryption',
					'embed'   => 'Embed',
					'encode'  => 'Encode',
				],
			]
		);

		add_settings_field(
			'use_first_party_proxy',
			__( 'First-party proxy', 'sesamy' ),
			[ $settings_page_view, 'settings_render_checkbox' ],
			'sesamy',
			'section_advanced',
			[
				'name'      => 'use_first_party_proxy',
				'label_for' => __( 'Route Sesamy auth and API traffic through this domain (recommended).', 'sesamy' ),
			]
		);
	}
}
