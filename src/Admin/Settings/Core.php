<?php
/**
 * Core Settings module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\Settings;

use TenupFramework\Module;
use TenupFramework\ModuleInterface;

use function SesamyPlugin\Helpers\is_config_valid;

/**
 * Core Settings module.
 *
 * @package Sesamy2
 */
class Core implements ModuleInterface {

	use Module;

	/**
	 * Can this module be registered?
	 *
	 * @return bool
	 */
	public function can_register() {
		return is_config_valid();
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
				'type'         => 'object',
				'show_in_rest' => [
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
								'type' => 'number',
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
			]
		);
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

		add_settings_field(
			'client_secret',
			__( 'Client Secret', 'sesamy' ),
			[ $admin_settings_view, 'settings_render_textfield' ],
			'sesamy',
			'section_general',
			[
				'name'      => 'client_secret',
				'label_for' => 'client_secret',
			]
		);

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
			[ $admin_settings_view, 'settings_render_numberfield' ],
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
					''           => 'Select',
					'embed'      => 'Embed',
					'encode'     => 'Encode',
					'signed-url' => 'Signed URL',
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
			'render_settings',
			__( 'Render', 'sesamy' ),
			[ $admin_settings_view, 'settings_render_checkbox_list' ],
			'sesamy',
			'section_general',
			[
				'name'    => 'render_settings',
				'options' => [
					'meta'    => 'Metadata',
					'paywall' => 'Paywall',
					'js'      => 'JavaScript',
				],
			]
		);
	}
}
