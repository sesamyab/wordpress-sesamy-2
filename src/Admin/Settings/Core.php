<?php
/**
 * Core Settings module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\Settings;

/**
 * Core Settings module.
 *
 * @package Sesamy2
 */
class Core {
	/**
	 * Initialize the Assets module.
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
							'client_id'             => [
								'type' => 'string',
							],
							'client_secret'         => [
								'type' => 'string',
							],
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
							'render_settings'       => [
								'type' => 'array',
							],
						],
					],
				],
				'sanitize_callback' => [ $this, 'sanitize_sesamy_settings' ],
			]
		);
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
		$admin_settings_view = new \SesamyPlugin\Admin\View\Settings();

		add_settings_section(
			'section_general',
			'',
			[ $admin_settings_view, 'section_general_callback' ],
			'sesamy'
		);

		add_settings_field(
			'client_id',
			__( 'Client ID', 'sesamy' ),
			[ $admin_settings_view, 'settings_render_textfield' ],
			'sesamy',
			'section_general',
			[
				'name'      => 'client_id',
				'label_for' => 'client_id',
			]
		);

		// TODO: Add client secret when needed
		// add_settings_field(
		// 'client_secret',
		// __( 'Client Secret', 'sesamy' ),
		// [ $admin_settings_view, 'settings_render_textfield' ],
		// 'sesamy',
		// 'section_general',
		// [
		// 'name'      => 'client_secret',
		// 'label_for' => 'client_secret',
		// ]
		// );

		add_settings_field(
			'default_currency',
			__( 'Default Currency', 'sesamy' ),
			[ $admin_settings_view, 'settings_render_selectfield' ],
			'sesamy',
			'section_general',
			[
				'name'      => 'default_currency',
				'label_for' => 'default_currency',
				'options'   => [
					''    => 'Select',
					'SEK' => 'SEK',
					'EUR' => 'EUR',
					'NOK' => 'NOK',
				],
			]
		);

		add_settings_field(
			'default_price',
			__( 'Default Article Price', 'sesamy' ),
			[ $admin_settings_view, 'settings_render_textfield' ],
			'sesamy',
			'section_general',
			[
				'name'        => 'default_price',
				'label_for'   => 'default_price',
				'description' => __( 'The default single purchase price for an article.', 'sesamy' ),
			]
		);

		add_settings_field(
			'default_paywall',
			__( 'Default Paywall', 'sesamy' ),
			[ $admin_settings_view, 'settings_render_textfield' ],
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
			[ $admin_settings_view, 'settings_render_textfield' ],
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
			[ $admin_settings_view, 'settings_render_selectfield' ],
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
			'enabled_content_types',
			__( 'Content Types', 'sesamy' ),
			[ $admin_settings_view, 'settings_render_posttype_list' ],
			'sesamy',
			'section_general',
			[
				'name' => 'enabled_content_types',
			]
		);

		add_settings_field(
			'development_mode',
			__( 'Development Mode', 'sesamy' ),
			[ $admin_settings_view, 'settings_render_checkbox_list' ],
			'sesamy',
			'section_general',
			[
				'name'    => 'development_mode',
				'options' => [
					'enabled' => 'Enable',
				],
			]
		);

		// TODO: Add render settings
		// add_settings_field(
		// 'render_settings',
		// __( 'Render', 'sesamy' ),
		// [ $admin_settings_view, 'settings_render_checkbox_list' ],
		// 'sesamy',
		// 'section_general',
		// [
		// 'name'    => 'render_settings',
		// 'options' => [
		// 'meta'    => 'Metadata',
		// 'paywall' => 'Paywall',
		// 'js'      => 'JavaScript',
		// ],
		// ]
		// );
	}
}
