<?php
/**
 * Meta module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Frontend;

use function SesamyPlugin\Helpers\get_enabled_post_types;
use function SesamyPlugin\Helpers\get_sesamy_vendor_id;
use function SesamyPlugin\Helpers\is_config_valid;

/**
 * Meta module.
 *
 * @package Sesamy2
 */
class Meta {
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
		add_action( 'wp_head', [ $this, 'plugin_comment' ] );
		if ( is_config_valid() ) {
			add_action( 'wp_head', [ $this, 'add_meta_tags' ] );
		}
	}

	/**
	 * Outputs a comment in the HTML source indicating the plugin version.
	 *
	 * @return void
	 */
	public function plugin_comment() {
		echo '<!-- Sesamy WordPress plugin v' . esc_html( SESAMY_PLUGIN_VERSION ) . ' -->';
	}

	/**
	 * Add meta tags.
	 *
	 * @return void
	 */
	public function add_meta_tags() {
		global $post;
		$options   = get_option( 'sesamy_settings' );
		$vendor_id = get_sesamy_vendor_id();

		if ( $vendor_id ) {
			echo '<meta name="sesamy:vendor-id" content="' . esc_attr( $vendor_id ) . '">';
		}
		if ( ! empty( $options['default_pass'] ) ) {
			echo '<meta name="sesamy:pass" content="' . esc_attr( $options['default_pass'] ) . '">';
		}

		$currency = $options['default_currency'] ?? '';
		if ( ! empty( $currency ) ) {
			echo '<meta name="sesamy:currency" content="' . esc_attr( $currency ) . '">';
		}

		if ( is_singular() ) {
			$is_locked    = (bool) ( get_post_meta( $post->ID, '_sesamy_locked', true ) ?? false );
			$access_level = $is_locked ? get_post_meta( $post->ID, '_sesamy_access_level', true ) : 'public';
			if ( ! empty( $access_level ) ) {
				echo '<meta name="sesamy:accessLevel" content="' . esc_attr( $access_level ) . '">';
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

			$locked_content_redirect_url = get_post_meta( $post->ID, '_sesamy_locked_content_redirect_url', true );
			if ( ! empty( $locked_content_redirect_url ) ) {
				echo '<meta name="sesamy:locked-content-redirect-url" content="' . esc_attr( $locked_content_redirect_url ) . '">';
			}
		}
	}
}
