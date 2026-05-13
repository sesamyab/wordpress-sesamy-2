<?php
/**
 * Login module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Frontend;

use function SesamyPlugin\Helpers\is_config_valid;

/**
 * Login module.
 *
 * Registers the `sesamy/login` block and the `[sesamy-login]` shortcode.
 *
 * @package Sesamy2
 */
class Login {
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
			add_action( 'init', [ $this, 'register_block' ] );
			add_shortcode( 'sesamy-login', [ $this, 'render_shortcode' ] );
		}
	}

	/**
	 * Register the Sesamy Login block.
	 *
	 * @return void
	 */
	public function register_block() {
		$asset_file = SESAMY_PLUGIN_DIST_PATH . 'js/login-block.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : [
			'dependencies' => [],
			'version'      => SESAMY_PLUGIN_VERSION,
		];

		wp_register_script(
			'sesamy-login-block',
			SESAMY_PLUGIN_URL . 'dist/js/login-block.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		register_block_type(
			'sesamy/login',
			[
				'api_version'     => '3',
				'editor_script'   => 'sesamy-login-block',
				// `showSubmenuIcon` is provided by core/navigation, so its
				// presence in $block->context tells us we're being rendered
				// inside one. We don't use the value — just the presence.
				'uses_context'    => [ 'showSubmenuIcon' ],
				'render_callback' => [ $this, 'render_login_block' ],
			]
		);
	}

	/**
	 * Server-side render callback for the sesamy/login block.
	 *
	 * Emits the <sesamy-login> web component. When placed inside a Navigation
	 * block we wrap it in an <li> so it sits alongside the other nav items;
	 * elsewhere we render a plain wrapper.
	 *
	 * @param array     $attributes Block attributes.
	 * @param string    $content    Inner block content (unused).
	 * @param \WP_Block $block      Block instance.
	 * @return string
	 */
	public function render_login_block( $attributes, $content, $block ) {
		if ( ! is_config_valid() ) {
			return '';
		}

		$in_navigation = is_array( $block->context ) && array_key_exists( 'showSubmenuIcon', $block->context );

		$button_text = isset( $attributes['buttonText'] ) ? trim( (string) $attributes['buttonText'] ) : '';
		$slot        = '' !== $button_text
			? sprintf( '<span slot="button-text">%s</span>', esc_html( $button_text ) )
			: '';

		if ( $in_navigation ) {
			$wrapper_attributes = get_block_wrapper_attributes(
				[ 'class' => 'wp-block-navigation-item sesamy-login-menu-item' ]
			);
			return sprintf( '<li %s><sesamy-login>%s</sesamy-login></li>', $wrapper_attributes, $slot );
		}

		$wrapper_attributes = get_block_wrapper_attributes();
		return sprintf( '<div %s><sesamy-login>%s</sesamy-login></div>', $wrapper_attributes, $slot );
	}

	/**
	 * Render callback for the `[sesamy-login]` shortcode.
	 *
	 * Emits the <sesamy-login> web component. Accepts an optional
	 * `button_text` attribute to override the default button label.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		if ( ! is_config_valid() ) {
			return '';
		}

		$atts = shortcode_atts(
			[ 'button_text' => '' ],
			is_array( $atts ) ? $atts : [],
			'sesamy-login'
		);

		$button_text = trim( (string) $atts['button_text'] );
		$slot        = '' !== $button_text
			? sprintf( '<span slot="button-text">%s</span>', esc_html( $button_text ) )
			: '';

		return sprintf( '<sesamy-login>%s</sesamy-login>', $slot );
	}
}
