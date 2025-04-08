<?php
/**
 * Content renderer module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Frontend\Renderers;

use function SesamyPlugin\Support\Helpers\get_sesamy_setting;

/**
 * Content renderer for frontend display.
 *
 * @package Sesamy2
 */
class ContentRenderer {
	/**
	 * Initialize the ContentRenderer module.
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
		add_filter( 'sesamy_render_content', [ $this, 'render_content' ], 10, 3 );
	}

	/**
	 * Render content with proper Sesamy container elements.
	 *
	 * @param string $content The content to render.
	 * @param string $preview The preview content (optional).
	 * @param array  $options Additional render options.
	 * @return string
	 */
	public function render_content( $content, $preview = '', $options = [] ) {
		$lock_mode    = isset( $options['lock_mode'] ) ? $options['lock_mode'] : get_sesamy_setting( 'lock_mode' );
		$item_src     = isset( $options['item_src'] ) ? $options['item_src'] : '';
		$publisher_id = isset( $options['publisher_id'] ) ? $options['publisher_id'] : '';
		$is_locked    = isset( $options['is_locked'] ) ? $options['is_locked'] : false;

		$html = '';

		if ( ! empty( $item_src ) && ! empty( $publisher_id ) ) {
			$html .= '<sesamy-article item-src="' . esc_url( $item_src ) . '" publisher-content-id="' . esc_attr( $publisher_id ) . '">';
		}

		$html .= '<sesamy-content-container lock-mode="' . esc_attr( $lock_mode ) . '">';

		if ( ! empty( $preview ) ) {
			$html .= '<div slot="preview">' . $preview . '</div>';
		}

		if ( 'embed' === $lock_mode ) {
			$html .= '<div slot="content">' . $content . '</div>';
		} elseif ( 'encode' === $lock_mode ) {
			$html .= '<div slot="content" style="display:none;">' . base64_encode( $content ) . '</div>';
		}

		$html .= '</sesamy-content-container>';

		if ( $is_locked && ! empty( $options['paywall_url'] ) ) {
			$html .= '<sesamy-paywall settings-url="' . esc_url( $options['paywall_url'] ) . '" />';
		}

		if ( ! empty( $item_src ) && ! empty( $publisher_id ) ) {
			$html .= '</sesamy-article>';
		}

		return $html;
	}
}
