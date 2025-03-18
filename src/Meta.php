<?php
/**
 * Meta module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin;

use TenupFramework\Module;
use TenupFramework\ModuleInterface;


/**
 * Meta module.
 *
 * @package Sesamy2
 */
class Meta implements ModuleInterface {

	use Module;

	/**
	 * Can this module be registered?
	 *
	 * @return bool
	 */
	public function can_register() {
		return true;
	}

	/**
	 * Register any hooks and filters.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_head', [ $this, 'add_meta_tags' ] );
	}

	/**
	 * Add meta tags.
	 *
	 * @return void
	 */
	public function add_meta_tags() {
		if ( is_singular() ) {
			global $post;

			$options = get_option( 'sesamy_settings' );

			if ( ! empty( $options['client_id'] ) ) {
				echo '<meta name="sesamy:client-id" content="' . esc_attr( $options['client_id'] ) . '">';
			}
			if ( ! empty( $options['default_pass'] ) ) {
				echo '<meta name="sesamy:pass" content="' . esc_attr( $options['default_pass'] ) . '">';
			}
			echo '<meta name="sesamy:publisher-content-id" content="' . esc_attr( $post->ID ) . '">';
		}
	}
}
