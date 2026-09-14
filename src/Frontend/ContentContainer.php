<?php
/**
 * ContentContainer module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Frontend;

use SesamyPlugin\Capsule\Service as CapsuleService;

use function SesamyPlugin\Helpers\get_enabled_post_types;
use function SesamyPlugin\Helpers\get_sesamy_environment;
use function SesamyPlugin\Helpers\get_sesamy_setting;
use function SesamyPlugin\Helpers\get_sesamy_vendor_id;
use function SesamyPlugin\Helpers\is_config_valid;
use function SesamyPlugin\Helpers\is_post_locked;

/**
 * ContentContainer module.
 *
 * @package Sesamy2
 */
class ContentContainer {
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
		if ( is_config_valid() ) {
			add_filter( 'the_content', [ $this, 'apply_content_filter' ] );
		}
	}

	/**
	 * Add content container.
	 *
	 * @param string $content The content.
	 * @return string
	 */
	public function apply_content_filter( $content ) {
		// Using the <!-- more --> will break core if excerpt is empty as this will cause an infite loop.
		// See: https://github.com/WordPress/gutenberg/issues/5572#issuecomment-407756810.
		if ( doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}

		// Check if we're in a singular main query for any of the enabled post types.
		if ( is_singular( get_enabled_post_types() ) && is_main_query() ) {
			global $post;

			$html = $this->process_content( $post, $content );

			/**
			 * Filters the rendered Sesamy article container before `the_content`
			 * returns it.
			 *
			 * Runs after the plugin has wrapped the post content in
			 * `<article class="sesamy-article">`, applied the lock mode, and
			 * appended the paywall. Whatever the callback returns is what
			 * `the_content` outputs, so return a string.
			 *
			 * @since 1.10.0
			 *
			 * @param string   $html    The rendered article container markup.
			 * @param \WP_Post $post    The post being rendered.
			 * @param string   $content The post content as received from `the_content`.
			 */
			return apply_filters( 'sesamy_article_html', $html, $post, $content );
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
	public function process_content( $post, $content ) {
		$is_locked = is_post_locked( $post->ID );
		// If not locked, always use 'embed' lock mode
		if ( ! $is_locked ) {
			$lock_mode = 'embed';
		} else {
			$lock_mode = get_sesamy_setting( 'lock_mode' );
		}
		$preview = apply_filters( 'sesamy_paywall_preview', static::extract_preview( $post ) );
		$paywall = apply_filters( 'sesamy_paywall', static::render_paywall() );

		$item_src = get_permalink( $post->ID ) ? (string) get_permalink( $post->ID ) : '';

		$html = '<article class="sesamy-article" item-src="' . esc_url( $item_src ) . '" publisher-content-id="' . esc_attr( (string) $post->ID ) . '">';

		if ( 'capsule' === $lock_mode ) {
			$capsule = CapsuleService::render( $post, $content );
			if ( null !== $capsule ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- TEMP debug HTML comment, hardcoded in CapsuleService::build_debug_marker.
				$html .= CapsuleService::$debug_marker;
				$html .= '<div data-dca-content-name="' . esc_attr( $capsule['contentName'] ) . '">' . $preview . '</div>';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- manifestScript is publisher-built, JSON_HEX_TAG-escaped <script>.
				$html .= $capsule['manifestScript'];
			} else {
				$html .= $preview;
			}
		} else {
			$html .= '<sesamy-content-container lock-mode="' . esc_attr( $lock_mode ) . '">';
			$html .= '<div slot="preview">' . $preview . '</div>';
			if ( 'embed' === $lock_mode ) {
				$html .= '<div slot="content">' . $content . '</div>';
			} elseif ( 'encode' === $lock_mode ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				$html .= '<div slot="content" style="display:none;">' . base64_encode( $content ) . '</div>';
			}
			$html .= '</sesamy-content-container>';
		}

		if ( $is_locked ) {
			$html .= $paywall;
		}
		$html .= '</article>';

		return $html;
	}

	/**
	 * Default paywall.
	 *
	 * @return string
	 */
	public function render_paywall() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}

		$custom_settings_url         = get_post_meta( $post_id, '_sesamy_custom_paywall_url', true );
		$locked_content_redirect_url = get_post_meta( $post_id, '_sesamy_locked_content_redirect_url', true );
		$default_settings_url        = get_sesamy_setting( 'default_paywall' );
		$settings_url                = ! empty( $custom_settings_url ) ? $custom_settings_url : $default_settings_url;

		if ( empty( $settings_url ) || ! empty( $locked_content_redirect_url ) ) {
			return '';
		}

		// `default_paywall` stores the paywall id from the management API
		// (e.g. `IqCrKb7HkxL4fAJU42Zzr`), not a URL. The `<sesamy-paywall>`
		// component fetches `settings-url` directly, so resolve the id to a
		// full URL on the public paywall settings host. Per-post overrides
		// stored in `_sesamy_custom_paywall_url` are full URLs already and
		// pass through unchanged.
		// TODO: this temporary URL lives on `api.sesamy.{tld}` rather than
		// the proxied `api2` cluster — fold it into the proxy/`sesamy_url`
		// scheme once the paywall service moves.
		if ( ! preg_match( '#^https?://#i', (string) $settings_url ) ) {
			$vendor_id = (string) get_sesamy_vendor_id();
			if ( '' === $vendor_id ) {
				return '';
			}
			$tld          = 'dev' === get_sesamy_environment() ? 'dev' : 'com';
			$settings_url = sprintf(
				'https://api.sesamy.%s/paywall/paywalls/%s/%s.json',
				$tld,
				rawurlencode( $vendor_id ),
				rawurlencode( (string) $settings_url )
			);
		}

		// Must be an explicit open/close pair. HTML has no self-closing syntax
		// for non-void elements, so `<sesamy-paywall ... />` leaves the element
		// open and the parser nests everything that follows it — including any
		// theme markup after the article — as its children, which the component
		// then replaces when it upgrades and renders its own slot content.
		return '<sesamy-paywall settings-url="' . esc_url( $settings_url ) . '"></sesamy-paywall>';
	}

	/**
	 * Extract preview from post with logic to take more-tag into account
	 *
	 * @param \WP_Post $post The post object.
	 * @return string
	 */
	public function extract_preview( $post ) {
		// Caution: WordPress has two blocks, the original "more" and the "read-more". We support the "more" as that is intended for cutting previews.
		// Retrieve content before <!-- more --> if defined, otherwise use get_the_excerpt as default.
		$extended = get_extended( $post->post_content );

		if ( ! empty( $extended['main'] ) && ! empty( $extended['extended'] ) ) {
			return $extended['main'];
		} else {
			return '<p>' . get_the_excerpt() . '</p>';
		}
	}
}
