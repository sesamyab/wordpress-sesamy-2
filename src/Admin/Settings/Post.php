<?php
/**
 * Post Settings module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\Settings;

use function SesamyPlugin\Helpers\get_enabled_post_types;
use function SesamyPlugin\Helpers\is_config_valid;

/**
 * Post Settings module.
 *
 * @package Sesamy2
 */
class Post {
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
		if ( is_config_valid() ) {
			add_action( 'init', [ $this, 'register_slot_fill_meta' ] );
		}
	}

	/**
	 * Registers the `display-mode` post meta for use in the SlotFill lesson.
	 *
	 * @return void
	 */
	public function register_slot_fill_meta() {
		$enabled_post_types = get_enabled_post_types();

		if ( $enabled_post_types ) {
			foreach ( $enabled_post_types as $post_type ) {
				register_post_meta(
					$post_type,
					'_sesamy_locked',
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'boolean',
						'default'       => false,
						'auth_callback' => '__return_true',
					]
				);

				register_post_meta(
					$post_type,
					'_sesamy_access_level',
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'string',
						'auth_callback' => '__return_true',
						'default'       => 'entitlement',
					]
				);

				register_post_meta(
					$post_type,
					'_sesamy_enable_single_purchase',
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'boolean',
						'auth_callback' => '__return_true',
					]
				);

				register_post_meta(
					$post_type,
					'_sesamy_price',
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'number',
						'auth_callback' => '__return_true',
					]
				);

				register_post_meta(
					$post_type,
					'_sesamy_custom_paywall_url',
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'string',
						'auth_callback' => '__return_true',
					]
				);
			}
		}
	}
}
