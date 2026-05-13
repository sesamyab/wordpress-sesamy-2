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
 * Registers the `sesamy/login` block, the `[sesamy-login]` shortcode, and a
 * classic-menus meta box for adding the login as a menu item.
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
			add_action( 'load-nav-menus.php', [ $this, 'register_menu_meta_box' ] );
			add_filter( 'walker_nav_menu_start_el', [ $this, 'filter_classic_menu_item' ], 10, 2 );
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
	 * Register the "Sesamy" meta box on the classic-menus admin screen.
	 *
	 * Adds a left-column picker on Appearance → Menus so users on classic
	 * menus can drop a Sesamy Login item into a menu like any other item.
	 *
	 * @return void
	 */
	public function register_menu_meta_box() {
		add_meta_box(
			'sesamy-login-menu-item',
			__( 'Sesamy', 'sesamy2' ),
			[ $this, 'render_menu_meta_box' ],
			'nav-menus',
			'side',
			'default'
		);
	}

	/**
	 * Render the contents of the classic-menus meta box.
	 *
	 * Form field names follow the conventions WordPress uses for the
	 * built-in Pages/Posts/Custom Links pickers so the standard
	 * "Add to Menu" handler picks up our item with no extra wiring.
	 * The item is stored as a custom-link menu item carrying a
	 * `sesamy-login-menu-item` CSS class — that class is the marker
	 * `filter_classic_menu_item()` looks for on render.
	 *
	 * @return void
	 */
	public function render_menu_meta_box() {
		$label         = __( 'Sesamy Login', 'sesamy2' );
		$error_message = __( 'Please select an option.', 'sesamy2' );
		?>
		<div id="sesamy-login-menu-item-meta-box" class="posttypediv">
			<div class="tabs-panel tabs-panel-active">
				<ul class="categorychecklist form-no-clear">
					<li>
						<label class="menu-item-title">
							<input type="checkbox" class="menu-item-checkbox" name="menu-item[-1][menu-item-object-id]" value="-1" />
							<?php echo esc_html( $label ); ?>
						</label>
						<input type="hidden" class="menu-item-type" name="menu-item[-1][menu-item-type]" value="custom" />
						<input type="hidden" class="menu-item-title" name="menu-item[-1][menu-item-title]" value="<?php echo esc_attr( $label ); ?>" />
						<input type="hidden" class="menu-item-url" name="menu-item[-1][menu-item-url]" value="#sesamy-login" />
						<input type="hidden" name="menu-item[-1][menu-item-classes]" value="sesamy-login-menu-item" />
					</li>
				</ul>
			</div>
			<p class="button-controls wp-clearfix">
				<span class="add-to-menu">
					<input type="submit" class="button submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu', 'sesamy2' ); ?>" name="add-sesamy-login-menu-item" id="submit-sesamy-login-menu-item-meta-box" />
					<span class="spinner"></span>
				</span>
			</p>
		</div>
		<script>
		( function( $ ) {
			$( function() {
				var $box     = $( '#sesamy-login-menu-item-meta-box' );
				var $submit  = $( '#submit-sesamy-login-menu-item-meta-box' );
				var message  = <?php echo wp_json_encode( $error_message ); ?>;
				$submit.on( 'click', function( e ) {
					if ( $box.find( '.menu-item-checkbox' ).is( ':checked' ) ) {
						$box.find( '.sesamy-login-error' ).remove();
						return;
					}
					e.preventDefault();
					e.stopImmediatePropagation();
					if ( ! $box.find( '.sesamy-login-error' ).length ) {
						$( '<p class="sesamy-login-error notice notice-error notice-alt" tabindex="-1" role="alert"></p>' )
							.text( message )
							.insertBefore( $submit.closest( 'p' ) )
							.trigger( 'focus' );
					}
				} );
			} );
		} )( jQuery );
		</script>
		<?php
	}

	/**
	 * Swap a classic-menu Sesamy Login item for the <sesamy-login> component.
	 *
	 * The item is identified by the `sesamy-login-menu-item` CSS class set
	 * by the meta box (or the sentinel URL, as a fallback). The menu item
	 * title becomes the button label slot so users can rename it from the
	 * standard menu admin without extra UI.
	 *
	 * @param string   $item_output Default item output.
	 * @param \WP_Post $item        Current menu item.
	 * @return string
	 */
	public function filter_classic_menu_item( $item_output, $item ) {
		if ( ! is_object( $item ) ) {
			return $item_output;
		}

		$classes = isset( $item->classes ) && is_array( $item->classes ) ? $item->classes : [];
		$url     = isset( $item->url ) ? (string) $item->url : '';
		if ( ! in_array( 'sesamy-login-menu-item', $classes, true ) && '#sesamy-login' !== $url ) {
			return $item_output;
		}

		$title = isset( $item->title ) ? trim( (string) $item->title ) : '';
		$slot  = '' !== $title
			? sprintf( '<span slot="button-text">%s</span>', esc_html( $title ) )
			: '';

		return sprintf( '<sesamy-login>%s</sesamy-login>', $slot );
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
