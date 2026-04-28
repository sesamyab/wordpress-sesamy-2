<?php
/**
 * Settings Page module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\View;

use SesamyPlugin\Connect\SesamyApi;

use function SesamyPlugin\Helpers\get_sesamy_connection;
use function SesamyPlugin\Helpers\get_sesamy_setting;
use function SesamyPlugin\Helpers\is_sesamy_connected;

/**
 * Settings Page module.
 *
 * @package Sesamy2
 */
class SettingsPage {
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
		add_action( 'admin_menu', [ $this, 'add_sesamy_settings_page' ] );
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
	 * Admin page.
	 *
	 * @return void
	 */
	public function admin_page() {
		?>
		<div class="wrap" id="sesamy-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php $this->render_connection_status(); ?>
			<?php if ( is_sesamy_connected() ) : ?>
				<form action="options.php" method="post">
					<?php
					settings_errors( 'sesamy_settings' );
					settings_fields( 'sesamy' );
					do_settings_sections( 'sesamy' );
					submit_button( 'Save Settings' );
					?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Connection panel (Connect button or connected state +
	 * Disconnect button).
	 *
	 * @return void
	 */
	public function render_connection_status() {
		$connection = get_sesamy_connection();
		?>
		<h2><?php esc_html_e( 'Connection', 'sesamy2' ); ?></h2>
		<?php if ( is_sesamy_connected() && is_array( $connection ) ) : ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'sesamy2' ); ?></th>
						<td><strong><?php esc_html_e( 'Connected', 'sesamy2' ); ?></strong></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Client ID', 'sesamy2' ); ?></th>
						<td><code><?php echo esc_html( (string) ( $connection['client_id'] ?? '' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Domain', 'sesamy2' ); ?></th>
						<td><?php echo esc_html( (string) ( $connection['domain'] ?? '' ) ); ?></td>
					</tr>
					<?php if ( ! empty( $connection['tenant'] ) ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Tenant', 'sesamy2' ); ?></th>
							<td><code><?php echo esc_html( (string) $connection['tenant'] ); ?></code></td>
						</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Connected', 'sesamy2' ); ?></th>
						<td>
							<?php
							$connected_at = isset( $connection['connected_at'] ) ? (int) $connection['connected_at'] : 0;
							$connected_by = isset( $connection['connected_by'] ) ? (int) $connection['connected_by'] : 0;
							$user         = $connected_by ? get_userdata( $connected_by ) : false;
							$user_name    = $user ? $user->display_name : sprintf( '#%d', $connected_by );
							echo esc_html(
								sprintf(
									/* translators: 1: localized date+time, 2: WP user display name. */
									__( '%1$s by %2$s', 'sesamy2' ),
									$connected_at ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $connected_at ) : '—',
									$user_name
								)
							);
							?>
						</td>
					</tr>
				</tbody>
			</table>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-bottom: 2em;">
				<input type="hidden" name="action" value="sesamy_disconnect">
				<?php wp_nonce_field( 'sesamy_disconnect' ); ?>
				<?php submit_button( __( 'Disconnect', 'sesamy2' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'Connect this site to your Sesamy account to enable content gating and reader entitlements.', 'sesamy2' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-bottom: 2em;">
				<input type="hidden" name="action" value="sesamy_connect_start">
				<?php wp_nonce_field( 'sesamy_connect_start' ); ?>
				<?php submit_button( __( 'Connect to Sesamy', 'sesamy2' ), 'primary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
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
	 * Paywall select render. Pulls the publisher's paywalls from the management
	 * API at render time and falls back to a disabled control + error message
	 * if the API is unreachable.
	 *
	 * @param array{name: string} $args Arguments of the paywall select field.
	 *
	 * @return void
	 */
	public function settings_render_paywall_select( $args ) {
		$field_name    = $args['name'];
		$current_value = (string) ( get_sesamy_setting( $field_name ) ?? '' );

		$paywalls = SesamyApi::get_paywalls();

		if ( is_wp_error( $paywalls ) ) {
			echo '<input type="hidden" name="sesamy_settings[' . esc_attr( $field_name ) . ']" value="' . esc_attr( $current_value ) . '">';
			echo '<p class="description" style="color:#b32d2e;">' . esc_html(
				sprintf(
					/* translators: %s: error message from the management API. */
					__( 'Could not load paywalls: %s', 'sesamy2' ),
					$paywalls->get_error_message()
				)
			) . '</p>';
			return;
		}

		echo '<select id="' . esc_attr( $field_name ) . '" name="sesamy_settings[' . esc_attr( $field_name ) . ']" class="regular-text">';
		echo '<option value="">' . esc_html__( '— Select a paywall —', 'sesamy2' ) . '</option>';

		$found_current = false;
		foreach ( $paywalls as $paywall ) {
			if ( ! is_array( $paywall ) ) {
				continue;
			}
			$id    = isset( $paywall['id'] ) ? (string) $paywall['id'] : '';
			$label = isset( $paywall['name'] ) ? (string) $paywall['name'] : $id;
			if ( '' === $id ) {
				continue;
			}
			$selected = selected( $current_value, $id, false );
			if ( $current_value === $id ) {
				$found_current = true;
			}
			echo '<option value="' . esc_attr( $id ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $label ) . '</option>';
		}

		// Preserve a previously-saved value that is no longer in the list (e.g.
		// a deleted paywall) so saving the form does not silently clear it.
		if ( '' !== $current_value && ! $found_current ) {
			echo '<option value="' . esc_attr( $current_value ) . '" selected>' . esc_html(
				sprintf(
					/* translators: %s: paywall id no longer returned by the API. */
					__( '%s (not found)', 'sesamy2' ),
					$current_value
				)
			) . '</option>';
		}

		echo '</select>';

		if ( empty( $paywalls ) ) {
			echo '<p class="description">' . esc_html__( 'No paywalls found in your Sesamy account.', 'sesamy2' ) . '</p>';
		}
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
