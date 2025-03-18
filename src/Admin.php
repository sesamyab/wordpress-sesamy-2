<?php
/**
 * Admin module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin;

use TenupFramework\Module;
use TenupFramework\ModuleInterface;


/**
 * Admin module.
 *
 * @package Sesamy2
 */
class Admin implements ModuleInterface {

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
		add_action( 'init', [ $this, 'register_sesamy_settings' ] );
		add_action( 'admin_menu', [ $this, 'add_sesamy_settings_page' ] );
		add_action( 'admin_init', [ $this, 'add_sesamy_setting_fields' ] );
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_sesamy_settings() {
		register_setting( 'sesamy', 'sesamy_settings' );
	}

	/**
	 * Add Sesamy admin page.
	 *
	 * @return void
	 */
	public function add_sesamy_settings_page() {
		add_menu_page(
			'Sesamy Settings',
			'Sesamy',
			'manage_options',
			'sesamy',
			[ $this, 'admin_page' ],
			plugins_url( 'dist/images/sesamy.svg', __DIR__ ),
			100
		);
	}

	/**
	 * Admin page.
	 *
	 * @return void
	 */
	public function admin_page() {
		?>
		<div class="wrap" id="sesamy-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'sesamy' );
				do_settings_sections( 'sesamy' );
				submit_button( 'Save Settings' );
				echo wp_json_encode( get_option( 'sesamy_settings' ) );
				// echo '<br><br>';
				// echo wp_json_encode( get_post_types( [ 'public' => true ], 'objects' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add settings fields.
	 *
	 * @return void
	 */
	public function add_sesamy_setting_fields() {
		add_settings_section(
			'section_general',
			'',
			[ $this, 'section_general_callback' ],
			'sesamy'
		);

		add_settings_field(
			'client_id',
			__( 'Client ID', 'sesamy' ),
			[ $this, 'settings_render_textfield' ],
			'sesamy',
			'section_general',
			[
				'name'      => 'client_id',
				'label_for' => 'client_id',
			]
		);

		add_settings_field(
			'client_secret',
			__( 'Client Secret', 'sesamy' ),
			[ $this, 'settings_render_textfield' ],
			'sesamy',
			'section_general',
			[
				'name'      => 'client_secret',
				'label_for' => 'client_secret',
			]
		);

		add_settings_field(
			'default_currency',
			__( 'Default Currency', 'sesamy' ),
			[ $this, 'settings_render_selectfield' ],
			'sesamy',
			'section_general',
			[
				'name'      => 'default_currency',
				'label_for' => 'default_currency',
				'options'   => [
					''    => 'Select',
					'SEK' => 'SEK',
					'EUR' => 'EUR',
					'NOK' => 'NOK',
				],
			]
		);

		add_settings_field(
			'default_paywall',
			__( 'Default Paywall', 'sesamy' ),
			[ $this, 'settings_render_textfield' ],
			'sesamy',
			'section_general',
			[
				'name'      => 'default_paywall',
				'label_for' => 'default_paywall',
			]
		);

		add_settings_field(
			'default_pass',
			__( 'Default Pass', 'sesamy' ),
			[ $this, 'settings_render_textfield' ],
			'sesamy',
			'section_general',
			[
				'name'      => 'default_pass',
				'label_for' => 'default_pass',
			]
		);

		add_settings_field(
			'lock_mode',
			__( 'Lock Mode', 'sesamy' ),
			[ $this, 'settings_render_selectfield' ],
			'sesamy',
			'section_general',
			[
				'name'    => 'lock_mode',
				'options' => [
					''           => 'Select',
					'embed'      => 'Embed',
					'encode'     => 'Encode',
					'signed-url' => 'Signed URL',
				],
			]
		);

		add_settings_field(
			'enabled_content_types',
			__( 'Content Types', 'sesamy' ),
			[ $this, 'settings_render_posttype_list' ],
			'sesamy',
			'section_general',
			[
				'name' => 'enabled_content_types',
			]
		);

		add_settings_field(
			'render_settings',
			__( 'Render', 'sesamy' ),
			[ $this, 'settings_render_checkbox_list' ],
			'sesamy',
			'section_general',
			[
				'name'    => 'render_settings',
				'options' => [
					'meta'    => 'Metadata',
					'paywall' => 'Paywall',
					'js'      => 'JavaScript',
				],
			]
		);
	}

	/**
	 * General Section callback.
	 *
	 * @return void
	 */
	public function section_general_callback() {}

	/**
	 * Text field render
	 *
	 * @param array{name: string} $args Arguments of text fields.
	 *
	 * @return void
	 */
	public function settings_render_textfield( $args ) {
		/**
		 * Options array from the Sesamy settings.
		 *
		 * @var array<string> $options
		 */
		$options = get_option( 'sesamy_settings' );

		$field_name    = $args['name'];
		$current_value = isset( $options[ $field_name ] ) ? $options[ $field_name ] : '';

		echo '<input type="text" id="' . esc_attr( $field_name ) . '" name="sesamy_settings[' . esc_attr( $field_name ) . ']" value="' . esc_attr( $current_value ) . '" class="regular-text" />';
	}

	/**
	 * Select field render
	 *
	 * @param array{name: string, options: array<string, string>} $args Arguments of select fields.
	 *
	 * @return void
	 */
	public function settings_render_selectfield( $args ) {
		/**
		 * Options array from the Sesamy settings.
		 *
		 * @var array<string> $options
		 */
		$options = get_option( 'sesamy_settings' );

		$field_name    = $args['name'];
		$current_value = isset( $options[ $field_name ] ) ? $options[ $field_name ] : '';

		echo '<select id="' . esc_attr( $field_name ) . '" name="sesamy_settings[' . esc_attr( $field_name ) . ']" class="regular-text">';
		if ( isset( $args['options'] ) && is_array( $args['options'] ) ) {
			foreach ( $args['options'] as $value => $label ) {
				$selected = selected( $current_value, $value, false );
				echo '<option value="' . esc_attr( $value ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $label ) . '</option>';
			}
		}
		echo '</select>';
	}

	/**
	 * Checkbox field render
	 *
	 * @param array{name: string, options: array<string, string>} $args Arguments of checkbox fields.
	 *
	 * @return void
	 */
	public function settings_render_checkbox_list( $args ) {
		/**
		 * Options array from the Sesamy settings.
		 *
		 * @var array<string> $options
		 */
		$options = get_option( 'sesamy_settings' );

		$field_name     = $args['name'];
		$current_values = isset( $options[ $field_name ] ) ? $options[ $field_name ] : [];

		echo '<fieldset>';
		foreach ( $args['options'] as $value => $label ) {
			$checked = checked( in_array( $value, $current_values, true ), true, false );
			echo '<label><input type="checkbox" name="sesamy_settings[' . esc_attr( $args['name'] ) . '][]" value="' . esc_attr( $value ) . '" ' . esc_attr( $checked ) . '>' . esc_html( $label ) . '</label><br>';
		}
		echo '</fieldset>';
	}

	/**
	 * Post type checkbox field render
	 *
	 * @param array{name: string, options: array<string, string>} $args Arguments of Post type checkbox fields.
	 *
	 * @return void
	 */
	public function settings_render_posttype_list( $args ) {
		/**
		 * Options array from the Sesamy settings.
		 *
		 * @var array<string> $options
		 */
		$options = get_option( 'sesamy_settings' );

		$field_name     = $args['name'];
		$current_values = isset( $options[ $field_name ] ) ? $options[ $field_name ] : [];
		$post_types     = get_post_types( [ 'public' => true ] );

		// Exclude the 'attachment' post type
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}

		echo '<fieldset>';
		foreach ( $post_types as $post_type ) {
			$obj           = get_post_type_object( $post_type );
			$singular_name = $obj->labels->singular_name;
			$checked       = checked( in_array( $post_type, $current_values, true ), true, false );
			echo '<label><input type="checkbox" name="sesamy_settings[' . esc_attr( $args['name'] ) . '][]" value="' . esc_attr( $post_type ) . '" ' . esc_attr( $checked ) . '>' . esc_html( $singular_name ) . '</label><br>';
		}
		echo '</fieldset>';
	}
}
