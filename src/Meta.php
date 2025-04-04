<?php
/**
 * Meta module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin;

use function SesamyPlugin\Helpers\get_enabled_post_types;
use function SesamyPlugin\Helpers\is_config_valid;

/**
 * Meta module.
 *
 * @package Sesamy2
 */
class Meta {
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
			add_action( 'wp_head', [ $this, 'add_meta_tags' ] );
		}
	}

	/**
	 * Add meta tags.
	 *
	 * @return void
	 */
	public function add_meta_tags() {
		if ( is_singular() ) {
			global $post;
			$enabled_post_types = get_enabled_post_types();
			if ( in_array( $post->post_type, $enabled_post_types, true ) ) {
				$options = get_option( 'sesamy_settings' );
				if ( ! empty( $options['client_id'] ) ) {
					echo '<meta name="sesamy:client-id" content="' . esc_attr( $options['client_id'] ) . '">';
				}
				if ( ! empty( $options['default_pass'] ) ) {
					echo '<meta name="sesamy:pass" content="' . esc_attr( $options['default_pass'] ) . '">';
				}

				$is_locked    = (bool) ( get_post_meta( $post->ID, '_sesamy_locked', true ) ?? false );
				$access_level = $is_locked ? get_post_meta( $post->ID, '_sesamy_access_level', true ) : 'public';
				echo '<meta name="sesamy:accessLevel" content="' . esc_attr( $access_level ) . '">';

				$currency = $options['default_currency'] ?? '';
				if ( ! empty( $currency ) ) {
					echo '<meta name="sesamy:currency" content="' . esc_attr( $currency ) . '">';
				}

				$single_purchase = (bool) ( get_post_meta( $post->ID, '_sesamy_enable_single_purchase', true ) ?? false );
				if ( $single_purchase ) {
					$default_price = $options['default_price'] ?? '';
					$meta_price    = get_post_meta( $post->ID, '_sesamy_price', true );
					$price         = ! empty( $meta_price ) ? $meta_price : $default_price;
					if ( ! empty( $price ) ) {
						echo '<meta name="sesamy:price" content="' . esc_attr( $price ) . '">';
					}
				}
			}
		}
	}
}
