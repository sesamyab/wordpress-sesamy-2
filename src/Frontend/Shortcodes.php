<?php
/**
 * Shortcodes module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Frontend;

use function SesamyPlugin\Support\Helpers\get_sesamy_setting;
use function SesamyPlugin\Support\Helpers\is_config_valid;

/**
 * Shortcodes module for Sesamy.
 *
 * @package Sesamy2
 */
class Shortcodes {
	/**
	 * Initialize the Shortcodes module.
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
			add_shortcode( 'sesamy_paywall', [ $this, 'paywall_shortcode' ] );
			add_shortcode( 'sesamy_content', [ $this, 'content_shortcode' ] );
		}
	}

	/**
	 * Paywall shortcode.
	 *
	 * @param array $atts The shortcode attributes.
	 * @return string
	 */
	public function paywall_shortcode( $atts ) {
		$attributes = shortcode_atts(
			[
				'settings-url' => get_sesamy_setting( 'default_paywall' ),
			],
			$atts
		);

		if ( empty( $attributes['settings-url'] ) ) {
			return '';
		}

		return '<sesamy-paywall settings-url="' . esc_url( $attributes['settings-url'] ) . '" />';
	}

	/**
	 * Content shortcode.
	 *
	 * @param array  $atts The shortcode attributes.
	 * @param string $content The shortcode content.
	 * @return string
	 */
	public function content_shortcode( $atts, $content = null ) {
		if ( is_null( $content ) ) {
			return '';
		}

		$attributes = shortcode_atts(
			[
				'preview'   => '',
				'lock-mode' => get_sesamy_setting( 'lock_mode' ),
			],
			$atts
		);

		$html = '<sesamy-content-container lock-mode="' . esc_attr( $attributes['lock-mode'] ) . '">';

		if ( ! empty( $attributes['preview'] ) ) {
			$html .= '<div slot="preview">' . wp_kses_post( $attributes['preview'] ) . '</div>';
		}
		if ( 'embed' === $attributes['lock-mode'] ) {
			$html .= '<div slot="content">' . wp_kses_post( do_shortcode( $content ) ) . '</div>';
		} elseif ( 'encode' === $attributes['lock-mode'] ) {
			$html .= '<div slot="content" style="display:none;">' . base64_encode( wp_kses_post( do_shortcode( $content ) ) ) . '</div>';
		}

		$html .= '</sesamy-content-container>';

		return $html;
	}
}
