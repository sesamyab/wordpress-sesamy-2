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
 * Adds the <sesamy-login> web component to the site navigation.
 *
 * @package Sesamy2
 */
class Login {
	/**
	 * Whether the login component has already been rendered.
	 *
	 * @var bool
	 */
	private $rendered = false;

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
			// Block themes: append to the navigation block.
			add_filter( 'render_block_core/navigation', [ $this, 'add_login_to_navigation_block' ] );
			// Classic themes: append to the nav menu.
			add_filter( 'wp_nav_menu_items', [ $this, 'add_login_to_menu' ], 10, 2 );

			add_action( 'wp_head', [ $this, 'login_styles' ] );
		}
	}

	/**
	 * Output styles for the sesamy-login component.
	 *
	 * @return void
	 */
	public function login_styles() {
		echo '<style>.sesamy-login-menu-item{list-style:none;margin-left:auto}' .
			'.wp-block-navigation sesamy-login{margin-left:auto}</style>';
	}

	/**
	 * Append the sesamy-login component to the navigation block output.
	 *
	 * @param string $block_content The block content.
	 * @return string
	 */
	public function add_login_to_navigation_block( $block_content ) {
		if ( $this->rendered ) {
			return $block_content;
		}

		$login_item = '<li class="wp-block-navigation-item sesamy-login-menu-item"><sesamy-login></sesamy-login></li>';

		// Find the last closing </ul> inside the navigation container and append the login item before it.
		$last_ul_pos = strrpos( $block_content, '</ul></ul>' );
		if ( false !== $last_ul_pos ) {
			// Nested <ul>: insert before the inner </ul>.
			$this->rendered = true;
			return substr_replace( $block_content, $login_item . '</ul></ul>', $last_ul_pos, strlen( '</ul></ul>' ) );
		}

		// Single <ul>: insert before the closing </ul>.
		$last_ul_pos = strrpos( $block_content, '</ul>' );
		if ( false !== $last_ul_pos ) {
			$this->rendered = true;
			return substr_replace( $block_content, $login_item . '</ul>', $last_ul_pos, strlen( '</ul>' ) );
		}

		return $block_content;
	}

	/**
	 * Append the sesamy-login component to a classic navigation menu.
	 *
	 * @param string    $items The HTML list content for the menu items.
	 * @param \stdClass $args  An object containing wp_nav_menu() arguments.
	 * @return string
	 */
	public function add_login_to_menu( $items, $args ) {
		if ( 'primary' === $args->theme_location || 'main-menu' === $args->theme_location ) {
			$items .= '<li class="menu-item sesamy-login-menu-item"><sesamy-login></sesamy-login></li>';
		}

		return $items;
	}
}
