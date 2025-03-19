<?php
/**
 * ContentContainer module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin;

use function SesamyPlugin\Helpers\get_enabled_post_types;
use function SesamyPlugin\Helpers\get_sesamy_setting;
use function SesamyPlugin\Helpers\is_config_valid;

/**
 * ContentContainer module.
 *
 * @package Sesamy2
 */
class ContentContainer {

	/**
	 * Register any hooks and filters.
	 *
	 * @return void
	 */
	public static function register() {
		if ( is_config_valid() ) {
			add_filter( 'the_content', [ static::class, 'apply_content_filter' ] );
			add_filter( 'sesamy_content', [ static::class, 'process_content' ], 999, 2 );
		}
	}

	/**
	 * Add content container.
	 *
	 * @param string $content The content.
	 * @return string
	 */
	public static function apply_content_filter( $content ) {
		// Using the <!-- more --> will break core if excerpt is empty as this will cause an infite loop.
		// See: https://github.com/WordPress/gutenberg/issues/5572#issuecomment-407756810.
		if ( doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}

		// Check if we're in a singular main query for any of the enabled post types.
		if ( is_singular( get_enabled_post_types() ) && is_main_query() ) {
			global $post;
			return apply_filters( 'sesamy_content', $post, $content );
		}

		return $content;
	}

	/**
	 * Process content.
	 *
	 * @param \WP_Post $post The post object.
	 * @param string   $content The content.
	 * @return string
	 */
	public static function process_content( $post, $content ) {
		$is_locked = get_post_meta( $post->ID, '_sesamy_locked', true );
		$lock_mode = get_sesamy_setting( 'lock_mode' );
		$preview   = apply_filters( 'sesamy_paywall_preview', static::extract_preview( $post ) );
		$paywall   = apply_filters( 'sesamy_paywall', static::render_paywall() );

		$html  = '<sesamy-article item-src="' . esc_url( get_permalink( $post->ID ) ) . '" publisher-content-id="' . esc_attr( $post->ID ) . '">';
		$html .= '<sesamy-content-container lock-mode="' . esc_attr( $lock_mode ) . '">';
		$html .= '<div slot="preview">' . $preview . '</div>';
		if ( 'embed' === $lock_mode ) {
			$html .= '<div slot="content">' . $content . '</div>';
		} elseif ( 'encode' === $lock_mode ) {
			$html .= '<div slot="content">' . base64_encode( $content ) . '</div>';
		}
		$html .= '</sesamy-content-container>';
		if ( $is_locked ) {
			$html .= $paywall;
		}
		$html .= '</sesamy-article>';

		return $html;
	}

	/**
	 * Default paywall.
	 *
	 * @return string
	 */
	public static function render_paywall() {
		$custom_settings_url  = get_post_meta( get_the_ID(), '_sesamy_custom_paywall_url', true );
		$default_settings_url = get_sesamy_setting( 'default_paywall' );
		$settings_url         = ! empty( $custom_settings_url ) ? $custom_settings_url : $default_settings_url;
		if ( empty( $settings_url ) ) {
			return '';
		}
		return '<sesamy-paywall settings-url="' . esc_url( $settings_url ) . '" />';
	}

	/**
	 * Extract preview from post with logic to take more-tag into account
	 *
	 * @param \WP_Post $post The post object.
	 * @return string
	 */
	public static function extract_preview( $post ) {
		// Caution: WordPress has two blocks, the original "more" and the "read-more". We support the "more" as that is intended for cutting previews.
		// Retrieve content before <!-- more --> if defined, otherwise use get_the_excerpt as default.
		$extended = get_extended( $post->post_content );

		if ( ! empty( $extended['main'] ) && ! empty( $extended['extended'] ) ) {
			return $extended['main'];
		} else {
			return get_the_excerpt();
		}
	}
}
