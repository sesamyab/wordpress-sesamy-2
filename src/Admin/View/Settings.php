<?php
/**
 * Settings View module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\View;

use function SesamyPlugin\Support\Helpers\get_releases;
use function SesamyPlugin\Support\Helpers\get_sesamy_setting;
use function SesamyPlugin\Support\Helpers\is_update_available;

/**
 * Settings View module.
 *
 * @package Sesamy2
 */
class Settings {
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
		add_action( 'admin_menu', [ $this, 'add_sesamy_settings_page' ] );
		add_action( 'admin_notices', [ $this, 'sesamy_settings_notices' ] );
	}

	/**
	 * Add Sesamy admin page.
	 *
	 * @return void
	 */
	public function add_sesamy_settings_page() {
		add_menu_page(
			'Sesamy Settings',
			'Sesamy <span class="update-plugins sesamy-update-badge">1</span>',
			'manage_options',
			'sesamy',
			[ $this, 'admin_page' ],
			SESAMY_PLUGIN_URL . 'dist/images/sesamy.svg',
			100
		);
	}

	/**
	 * Display admin notices on the Sesamy settings page.
	 *
	 * @return void
	 */
	public function sesamy_settings_notices() {
		$screen = get_current_screen();

		// Only show on sesamy settings page.
		if ( ! $screen || 'toplevel_page_sesamy' !== $screen->id ) {
			return;
		}

		if ( is_update_available() ) {
			$releases       = get_releases();
			$latest_version = is_array( $releases ) && ! empty( $releases ) ? $releases[0] : null;
			if ( ! $latest_version ) {
				return;
			}
			$latest_tag  = str_replace( 'v', '', $latest_version['tag_name'] );
			$release_url = $latest_version['html_url'];
			$docs_url    = 'https://docs.sesamy.com/products/7KJN7PiwkXdWXks753aJt6/downloading-latest-version-and-installning/4zbFtvfEGEvW6bsvDZRVF2';
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<strong>Update Available!</strong> A new version of Sesamy is available. You have version 1.9.0 installed,
					<a href="<?php echo esc_url( $release_url ); ?>" target="_blank">
						<strong>update to <?php echo esc_html( $latest_tag ); ?></strong>
					</a><br />
					Visit <a href="<?php echo esc_url( $docs_url ); ?>" target="_blank">our documentation</a> for instructions on how to update.
				</p>
			</div>
			<?php
		}
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
				settings_errors( 'sesamy_settings' );
				settings_fields( 'sesamy' );
				do_settings_sections( 'sesamy' );
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
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
		$field_name    = $args['name'];
		$current_value = get_sesamy_setting( $field_name );

		echo '<input type="text" id="' . esc_attr( $field_name ) . '" name="sesamy_settings[' . esc_attr( $field_name ) . ']" value="' . esc_attr( $current_value ) . '" class="regular-text" />';
	}

	/**
	 * Number field render
	 *
	 * @param array{name: string} $args Arguments of text fields.
	 *
	 * @return void
	 */
	public function settings_render_numberfield( $args ) {
		$field_name    = $args['name'];
		$current_value = get_sesamy_setting( $field_name );

		echo '<input type="number" id="' . esc_attr( $field_name ) . '" name="sesamy_settings[' . esc_attr( $field_name ) . ']" value="' . esc_attr( $current_value ) . '" class="regular-text" />';
	}

	/**
	 * Select field render
	 *
	 * @param array{name: string, options: array<string, string>} $args Arguments of select fields.
	 *
	 * @return void
	 */
	public function settings_render_selectfield( $args ) {
		$field_name    = $args['name'];
		$current_value = get_sesamy_setting( $field_name );

		echo '<select id="' . esc_attr( $field_name ) . '" name="sesamy_settings[' . esc_attr( $field_name ) . ']" class="regular-text">';
		foreach ( $args['options'] as $value => $label ) {
			$selected = selected( $current_value, $value, false );
			echo '<option value="' . esc_attr( $value ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Checkbox field render.
	 *
	 * @param array{name: string, label_for: string} $args Arguments of checkbox fields.
	 *
	 * @return void
	 */
	public function settings_render_checkbox( $args ) {
		$field_name    = $args['name'];
		$field_label   = $args['label_for'];
		$current_value = get_sesamy_setting( $field_name );

		echo '<label><input type="checkbox" name="sesamy_settings[' . esc_attr( $args['name'] ) . ']" value="1" ' . checked( $current_value, true, false ) . '>' . esc_html( $field_label ) . '</label>';
	}

	/**
	 * Checkbox list render
	 *
	 * @param array{name: string, options: array<string, string>} $args Arguments of checkbox list fields.
	 *
	 * @return void
	 */
	public function settings_render_checkbox_list( $args ) {
		$field_name     = $args['name'];
		$current_values = get_sesamy_setting( $field_name ) ?? [];

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
		$field_name     = $args['name'];
		$current_values = get_sesamy_setting( $field_name ) ?? [];
		$post_types     = get_post_types( [ 'public' => true ] );

		// Exclude the 'attachment' post type
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}

		echo '<fieldset>';
		foreach ( $post_types as $post_type ) {
			$obj = get_post_type_object( $post_type );
			if ( isset( $obj->labels->singular_name ) ) {
				$singular_name = $obj->labels->singular_name;
			} else {
				$singular_name = $post_type;
			}
			$checked = checked( in_array( $post_type, $current_values, true ), true, false );
			echo '<label><input type="checkbox" name="sesamy_settings[' . esc_attr( $args['name'] ) . '][]" value="' . esc_attr( $post_type ) . '" ' . esc_attr( $checked ) . '>' . esc_html( $singular_name ) . '</label><br>';
		}
		echo '</fieldset>';
	}
}
