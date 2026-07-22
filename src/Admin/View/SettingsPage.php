<?php
/**
 * Settings Page module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\View;

use SesamyPlugin\Capsule\Registration;
use SesamyPlugin\Connect\SesamyApi;

use function SesamyPlugin\Helpers\get_enabled_post_types;
use function SesamyPlugin\Helpers\get_locked_term_rules;
use function SesamyPlugin\Helpers\get_sesamy_connection;
use function SesamyPlugin\Helpers\get_sesamy_setting;
use function SesamyPlugin\Helpers\is_sesamy_connected;
use function SesamyPlugin\Helpers\publisher_domain;

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
		add_action( 'admin_post_sesamy_get_api_token', [ $this, 'handle_get_api_token' ] );
	}

	/**
	 * Mint (or reuse the cached) management-API access token and stash it
	 * in a per-user transient for one-shot display. The token never goes
	 * through a query string and gets wiped from the transient after the
	 * settings page reads it.
	 *
	 * @return never
	 */
	public function handle_get_api_token(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sesamy' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'sesamy_get_api_token' );

		$user_id = get_current_user_id();
		$token   = SesamyApi::get_access_token();
		if ( is_wp_error( $token ) ) {
			set_transient( 'sesamy_token_error_' . $user_id, (string) $token->get_error_message(), MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'sesamy_token', 'error', admin_url( 'admin.php?page=sesamy' ) ) );
			exit;
		}
		set_transient( 'sesamy_token_show_' . $user_id, (string) $token, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'sesamy_token', 'ready', admin_url( 'admin.php?page=sesamy' ) ) . '#sesamy-api-token' );
		exit;
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display flag from a same-origin redirect after the nonce-checked admin-post handler.
		$token_flag = isset( $_GET['sesamy_token'] ) ? sanitize_text_field( wp_unslash( $_GET['sesamy_token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$user_id     = get_current_user_id();
		$token_value = '';
		$token_error = '';
		if ( 'ready' === $token_flag ) {
			$token_value = (string) get_transient( 'sesamy_token_show_' . $user_id );
			delete_transient( 'sesamy_token_show_' . $user_id );
		} elseif ( 'error' === $token_flag ) {
			$token_error = (string) get_transient( 'sesamy_token_error_' . $user_id );
			delete_transient( 'sesamy_token_error_' . $user_id );
		}
		// Pre-connect fields (currently just `development_mode` on local
		// installs) — surfaced as a separate small form so the operator can
		// pick the right cluster *before* hitting Connect. Hidden once
		// connected: post-connect editing of these belongs in Advanced.
		global $wp_settings_fields;
		$has_pre_connect = ! is_sesamy_connected() && ! empty( $wp_settings_fields['sesamy']['section_pre_connect'] );
		?>
		<div class="wrap" id="sesamy-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php $this->render_connection_status(); ?>
			<?php if ( is_sesamy_connected() ) : ?>
				<?php $this->render_publisher_registration(); ?>
			<?php endif; ?>
			<?php if ( $has_pre_connect ) : ?>
				<form action="options.php" method="post" style="margin-bottom: 2em;">
					<?php settings_errors( 'sesamy_settings' ); ?>
					<?php settings_fields( 'sesamy' ); ?>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'sesamy', 'section_pre_connect' ); ?>
					</table>
					<?php submit_button( __( 'Save', 'sesamy' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
			<?php if ( is_sesamy_connected() ) : ?>
				<form action="options.php" method="post">
					<?php settings_errors( 'sesamy_settings' ); ?>
					<?php settings_fields( 'sesamy' ); ?>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'sesamy', 'section_general' ); ?>
					</table>
					<details class="sesamy-advanced" style="margin: 1.5em 0;" <?php echo ( '' !== $token_value || '' !== $token_error ) ? 'open' : ''; ?>>
						<summary style="cursor: pointer; padding: 0.5em 0; font-weight: 600;"><?php esc_html_e( 'Advanced', 'sesamy' ); ?></summary>
						<table class="form-table" role="presentation">
							<?php do_settings_fields( 'sesamy', 'section_advanced' ); ?>
						</table>
						<hr style="margin: 1.5em 0;">
						<h3 id="sesamy-api-token" style="margin-top: 0;"><?php esc_html_e( 'API token', 'sesamy' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Mint a short-lived access token to call the Sesamy management API directly (curl, Postman, etc.). The token has the same scope as the connected client and is invalidated when the connection is removed.', 'sesamy' ); ?></p>
						<p>
							<?php
							// `form="sesamy-token-form"` associates this button with the
							// satellite form below — HTML doesn't allow nested forms,
							// and we can't reuse the main settings form (different action).
							?>
							<button type="submit" class="button" form="sesamy-token-form"><?php esc_html_e( 'Get API token', 'sesamy' ); ?></button>
						</p>
						<?php if ( '' !== $token_value ) : ?>
							<p class="description"><?php esc_html_e( 'Copy the token now — it expires shortly. Send it as a Bearer header.', 'sesamy' ); ?></p>
							<textarea readonly rows="4" style="width:100%; max-width:60em; font-family: monospace; word-break: break-all;" onclick="this.select();"><?php echo esc_textarea( $token_value ); ?></textarea>
						<?php elseif ( '' !== $token_error ) : ?>
							<div class="notice notice-error inline" style="margin-top: 0.5em;"><pre style="white-space: pre-wrap; word-break: break-word; margin: 0; font-family: inherit;"><?php echo esc_html( $token_error ); ?></pre></div>
						<?php endif; ?>
					</details>
					<?php submit_button( 'Save Settings' ); ?>
				</form>
				<form id="sesamy-token-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:none;">
					<input type="hidden" name="action" value="sesamy_get_api_token">
					<?php wp_nonce_field( 'sesamy_get_api_token' ); ?>
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
	 * Render the Publisher registration panel: list of domains the api-proxy
	 * has on file for this vendor's `trustedPublisherKeys`, plus controls to
	 * add/remove/re-register. Lives in the main flow (not Advanced) because
	 * a missing/stale registration is the leading cause of capsule decrypt
	 * failures and we want it to be the first thing an operator sees.
	 *
	 * @return void
	 */
	public function render_publisher_registration() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display flags from same-origin redirects after nonce-checked admin-post handlers.
		$status  = isset( $_GET['sesamy_publisher_status'] ) ? sanitize_text_field( wp_unslash( $_GET['sesamy_publisher_status'] ) ) : '';
		$domain  = isset( $_GET['sesamy_publisher_domain'] ) ? sanitize_text_field( wp_unslash( $_GET['sesamy_publisher_domain'] ) ) : '';
		$message = isset( $_GET['sesamy_publisher_message'] ) ? sanitize_text_field( wp_unslash( $_GET['sesamy_publisher_message'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$canonical = publisher_domain();
		$listing   = Registration::list_publishers();
		$list_err  = is_wp_error( $listing ) ? $listing->get_error_message() : '';
		$entries   = is_array( $listing ) ? $listing : [];
		?>
		<h2 id="sesamy-publisher-registration"><?php esc_html_e( 'Publisher registration', 'sesamy2' ); ?></h2>
		<p class="description" style="max-width: 60em;">
			<?php esc_html_e( 'Sesamy verifies each capsule unlock against the publishers registered for this vendor. The canonical site domain is registered automatically when you connect — add additional domains here for staging environments, multisite hostnames, or build-time encryption from a separate workstation.', 'sesamy2' ); ?>
		</p>

		<?php if ( 'registered' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( /* translators: %s: domain. */ __( 'Registered %s with the api-proxy.', 'sesamy2' ), $domain ) ); ?></p></div>
		<?php elseif ( 'unregistered' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( /* translators: %s: domain. */ __( 'Removed %s from the api-proxy.', 'sesamy2' ), $domain ) ); ?></p></div>
		<?php elseif ( 'error' === $status ) : ?>
			<div class="notice notice-error">
				<p><?php echo esc_html( sprintf( /* translators: %s: domain. */ __( 'Registration failed for %s.', 'sesamy2' ), $domain ) ); ?></p>
				<?php if ( '' !== $message ) : ?>
					<pre style="white-space: pre-wrap; word-break: break-word; margin: 0; font-family: inherit;"><?php echo esc_html( $message ); ?></pre>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $list_err ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'Could not fetch the registered publishers list:', 'sesamy2' ); ?></p>
				<pre style="white-space: pre-wrap; word-break: break-word; margin: 0; font-family: inherit;"><?php echo esc_html( $list_err ); ?></pre>
			</div>
		<?php endif; ?>

		<table class="widefat striped" style="max-width: 60em; margin-bottom: 1em;">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Domain', 'sesamy2' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Mode', 'sesamy2' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Updated', 'sesamy2' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Probe', 'sesamy2' ); ?></th>
					<th scope="col" style="width: 12em;"><?php esc_html_e( 'Actions', 'sesamy2' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $entries ) && '' === $list_err ) : ?>
					<tr><td colspan="5"><em><?php esc_html_e( 'No publishers registered yet.', 'sesamy2' ); ?></em></td></tr>
				<?php endif; ?>
				<?php
				foreach ( $entries as $entry ) :
					if ( ! is_array( $entry ) ) {
						continue;
					}
					$row_domain = isset( $entry['domain'] ) ? (string) $entry['domain'] : '';
					if ( '' === $row_domain ) {
						continue;
					}
					$mode_label = ! empty( $entry['jwksUri'] ) ? 'JWKS' : ( ! empty( $entry['signingKeyPem'] ) || ! empty( $entry['hasSigningKey'] ) ? 'PEM' : '—' );
					$updated_at = isset( $entry['updatedAt'] ) ? (string) $entry['updatedAt'] : '';
					$probe      = is_array( $entry['probe'] ?? null ) ? $entry['probe'] : null;
					$probe_ok   = $probe ? (bool) ( $probe['ok'] ?? false ) : null;
					$probe_msg  = $probe ? (string) ( $probe['message'] ?? '' ) : '';
					$is_self    = $row_domain === $canonical;
					?>
					<tr>
						<td>
							<code><?php echo esc_html( $row_domain ); ?></code>
							<?php if ( $is_self ) : ?>
								<span class="description" style="margin-left: 0.5em;"><?php esc_html_e( '(this site)', 'sesamy2' ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $entry['jwksUri'] ) ) : ?>
								<br><span class="description"><?php echo esc_html( (string) $entry['jwksUri'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $mode_label ); ?></td>
						<td>
							<?php
							if ( '' === $updated_at ) {
								echo '—';
							} else {
								// Accept either ISO-8601 or epoch seconds. wp_date() gives
								// us the site's date/time format with the right tz.
								$ts        = is_numeric( $updated_at ) ? (int) $updated_at : (int) strtotime( $updated_at );
								$formatted = $ts ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) : '';
								echo esc_html( false !== $formatted && '' !== $formatted ? $formatted : $updated_at );
							}
							?>
						</td>
						<td>
							<?php if ( null === $probe_ok ) : ?>
								<span class="description">—</span>
							<?php elseif ( $probe_ok ) : ?>
								<span style="color:#1a7f37;">✓</span>
							<?php else : ?>
								<span style="color:#b32d2e;" title="<?php echo esc_attr( $probe_msg ); ?>">⚠</span>
								<?php if ( '' !== $probe_msg ) : ?>
									<br><span class="description"><?php echo esc_html( $probe_msg ); ?></span>
								<?php endif; ?>
							<?php endif; ?>
						</td>
						<td>
							<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline;">
								<input type="hidden" name="action" value="sesamy_unregister_publisher">
								<input type="hidden" name="domain" value="<?php echo esc_attr( $row_domain ); ?>">
								<?php wp_nonce_field( 'sesamy_unregister_publisher' ); ?>
								<button type="submit" class="button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Remove this publisher?', 'sesamy2' ) ); ?>');"><?php esc_html_e( 'Remove', 'sesamy2' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3 style="margin-top: 1.5em;"><?php esc_html_e( 'Add a domain', 'sesamy2' ); ?></h3>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-bottom: 1em;">
			<input type="hidden" name="action" value="sesamy_register_publisher">
			<?php wp_nonce_field( 'sesamy_register_publisher' ); ?>
			<p>
				<label for="sesamy-publisher-domain"><?php esc_html_e( 'Domain', 'sesamy2' ); ?></label>
				<input type="text" id="sesamy-publisher-domain" name="domain" class="regular-text" placeholder="staging.example.com" required>
				<select name="mode">
					<option value=""><?php esc_html_e( 'Auto', 'sesamy2' ); ?></option>
					<option value="pem"><?php esc_html_e( 'Pinned PEM', 'sesamy2' ); ?></option>
					<option value="jwks_uri"><?php esc_html_e( 'JWKS URI', 'sesamy2' ); ?></option>
				</select>
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Register', 'sesamy2' ); ?></button>
			</p>
			<p class="description" style="max-width: 60em;">
				<?php esc_html_e( 'Auto picks JWKS URI for publicly-reachable HTTPS hosts and pinned PEM otherwise (localhost / .local / .test). Manually-added domains default to pinned PEM since the plugin only serves a JWKS document at this site’s own home URL.', 'sesamy2' ); ?>
			</p>
		</form>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-bottom: 2em;">
			<input type="hidden" name="action" value="sesamy_register_publisher_self">
			<?php wp_nonce_field( 'sesamy_register_publisher_self' ); ?>
			<button type="submit" class="button">
			<?php
				echo esc_html( sprintf( /* translators: %s: canonical domain. */ __( 'Re-register %s', 'sesamy2' ), $canonical ) );
			?>
			</button>
			<span class="description" style="margin-left: 0.5em;"><?php esc_html_e( 'Forces a fresh PUT for this site’s domain. Useful after rotating the publisher signing key.', 'sesamy2' ); ?></span>
		</form>
		<?php
	}

	/**
	 * General Section callback.
	 *
	 * @return void
	 */
	public function section_general_callback() {}

	/**
	 * Advanced Section callback. Body is rendered inside a `<details>`
	 * fold-out by `admin_page()`, so nothing to emit here.
	 *
	 * @return void
	 */
	public function section_advanced_callback() {}

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
	 * Pass select render. Pulls the publisher's passes from the management
	 * API at render time; falls back to a disabled control + error message
	 * if the API is unreachable. Mirrors `settings_render_paywall_select`.
	 *
	 * @param array{name: string} $args Arguments of the pass select field.
	 * @return void
	 */
	public function settings_render_pass_select( $args ) {
		$field_name    = $args['name'];
		$current_value = (string) ( get_sesamy_setting( $field_name ) ?? '' );

		$passes = SesamyApi::get_passes();

		if ( is_wp_error( $passes ) ) {
			echo '<input type="hidden" name="sesamy_settings[' . esc_attr( $field_name ) . ']" value="' . esc_attr( $current_value ) . '">';
			echo '<p class="description" style="color:#b32d2e;">' . esc_html(
				sprintf(
					/* translators: %s: error message from the management API. */
					__( 'Could not load passes: %s', 'sesamy2' ),
					$passes->get_error_message()
				)
			) . '</p>';
			return;
		}

		echo '<select id="' . esc_attr( $field_name ) . '" name="sesamy_settings[' . esc_attr( $field_name ) . ']" class="regular-text">';
		echo '<option value="">' . esc_html__( '— Select a pass —', 'sesamy2' ) . '</option>';

		$found_current = false;
		foreach ( $passes as $pass ) {
			if ( ! is_array( $pass ) ) {
				continue;
			}
			$sku   = isset( $pass['sku'] ) ? (string) $pass['sku'] : '';
			$label = isset( $pass['title'] ) ? (string) $pass['title'] : $sku;
			if ( '' === $sku ) {
				continue;
			}
			$selected = selected( $current_value, $sku, false );
			if ( $current_value === $sku ) {
				$found_current = true;
			}
			echo '<option value="' . esc_attr( $sku ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $label ) . '</option>';
		}

		// Preserve a previously-saved value that no longer appears in the list
		// (e.g. a deleted pass) so saving the form does not silently clear it.
		if ( '' !== $current_value && ! $found_current ) {
			echo '<option value="' . esc_attr( $current_value ) . '" selected>' . esc_html(
				sprintf(
					/* translators: %s: pass sku no longer returned by the API. */
					__( '%s (not found)', 'sesamy2' ),
					$current_value
				)
			) . '</option>';
		}

		echo '</select>';

		if ( empty( $passes ) ) {
			echo '<p class="description">' . esc_html__( 'No passes found in your Sesamy account.', 'sesamy2' ) . '</p>';
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

		// Hidden `0` precedes the checkbox so unchecked submissions store an
		// explicit false. Without it the key would simply be absent from POST,
		// the defaults map would re-fill it on next read, and "off" would never
		// stick for fields that default to true.
		echo '<label>'
			. '<input type="hidden" name="sesamy_settings[' . esc_attr( $field_name ) . ']" value="0">'
			. '<input type="checkbox" name="sesamy_settings[' . esc_attr( $field_name ) . ']" value="1" ' . checked( $current_value, true, false ) . '>'
			. esc_html( $field_label )
			. '</label>';
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
	 * "Automatic locking" term list render. One checkbox group per public
	 * taxonomy of the enabled content types. Values are encoded as
	 * `taxonomy:term_id` and decoded to `{taxonomy, term_id}` objects by
	 * `Core::sanitize_sesamy_settings()`.
	 *
	 * @param array{name: string} $args Arguments of the locked terms field.
	 *
	 * @return void
	 */
	public function settings_render_locked_terms( $args ) {
		$field_name = $args['name'];
		$selected   = array_map(
			static function ( $rule ) {
				return $rule['taxonomy'] . ':' . $rule['term_id'];
			},
			get_locked_term_rules()
		);

		$taxonomies = [];
		foreach ( get_enabled_post_types() as $post_type ) {
			foreach ( get_object_taxonomies( (string) $post_type, 'objects' ) as $taxonomy ) {
				// Require REST exposure so the Gutenberg panel (which resolves
				// rules via the taxonomies REST API) sees the same rule set the
				// backend enforces — a public-but-non-REST taxonomy would lock
				// posts the editor can't explain.
				if ( ! empty( $taxonomy->public ) && ! empty( $taxonomy->show_in_rest ) ) {
					$taxonomies[ $taxonomy->name ] = $taxonomy;
				}
			}
		}

		$rendered_any = false;
		echo '<fieldset>';
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy->name,
					'hide_empty' => false,
				]
			);
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$rendered_any = true;
			echo '<strong>' . esc_html( $taxonomy->labels->name ) . '</strong><br>';
			foreach ( $terms as $term ) {
				$value   = $taxonomy->name . ':' . $term->term_id;
				$checked = checked( in_array( $value, $selected, true ), true, false );
				echo '<label><input type="checkbox" name="sesamy_settings[' . esc_attr( $field_name ) . '][]" value="' . esc_attr( $value ) . '" ' . esc_attr( $checked ) . '>' . esc_html( $term->name ) . '</label><br>';
			}
		}
		echo '</fieldset>';

		if ( ! $rendered_any ) {
			echo '<p class="description">' . esc_html__( 'No categories or tags found for the enabled content types.', 'sesamy2' ) . '</p>';
			return;
		}
		echo '<p class="description">' . esc_html__( 'Articles with any of these terms are always locked, in addition to articles locked individually. On sites with full-page caching, flush the cache after changing this.', 'sesamy2' ) . '</p>';
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
