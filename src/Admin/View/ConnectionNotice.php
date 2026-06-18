<?php
/**
 * Connection Notice module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\View;

use function SesamyPlugin\Helpers\get_sesamy_connection;
use function SesamyPlugin\Helpers\is_sesamy_connected;

/**
 * Surfaces an admin banner when the plugin is installed but no real
 * `sesamy_connection` exists. Two variants:
 *
 *  - Fresh install (no `sesamy_connection`, no legacy `sesamy_settings.client_id`):
 *    informational "Connect to Sesamy" prompt, dismissible per-user.
 *  - Legacy upgrade (no real `sesamy_connection`, but `get_sesamy_connection()`
 *    returns a `legacy_upgrade` stub): "Reconnect required" — un-dismissible
 *    because the frontend still renders paywalls, so it's easy to miss that
 *    server-side capabilities (Capsule, publisher registration, management
 *    API) are degraded until the publisher runs Connect.
 *
 * Scoped to Dashboard, Plugins, and the Sesamy settings page to keep the
 * banner visible where the operator is most likely to act on it without
 * leaking onto unrelated screens.
 *
 * @package Sesamy2
 */
class ConnectionNotice {

	/**
	 * Per-user meta key recording dismissal of the fresh-install variant.
	 * Stored as `'1'` once dismissed; absence means "still show". The legacy
	 * variant is intentionally not dismissible and ignores this key.
	 */
	private const DISMISSED_META = 'sesamy_connect_notice_dismissed';

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
		add_action( 'admin_notices', [ $this, 'render_notice' ] );
		add_action( 'wp_ajax_sesamy_dismiss_connect_notice', [ $this, 'handle_dismiss' ] );
	}

	/**
	 * Render the appropriate notice for the current admin screen + connection
	 * state, or nothing if neither applies.
	 *
	 * @return void
	 */
	public function render_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		// Limit visibility to screens where the operator is most likely to
		// notice and act: WP home (Dashboard), where they manage plugins
		// (Plugins), and the Sesamy page itself. Avoids nagging across every
		// post edit / media / settings screen.
		$allowed_screens = [ 'dashboard', 'plugins', 'toplevel_page_sesamy' ];
		if ( ! in_array( $screen->id, $allowed_screens, true ) ) {
			return;
		}

		// Real connection — nothing to nag about.
		if ( is_sesamy_connected() ) {
			return;
		}

		// `get_sesamy_connection()` returns a non-null array for the legacy
		// stub (synthesized from `sesamy_settings.client_id`). Null means the
		// install has no Sesamy state at all — a fresh install.
		$is_legacy   = is_array( get_sesamy_connection() );
		$connect_url = admin_url( 'admin.php?page=sesamy' );

		if ( $is_legacy ) {
			$this->render_legacy_notice( $connect_url );
			return;
		}

		$this->render_fresh_notice( $connect_url );
	}

	/**
	 * Render the legacy-upgrade variant. Not dismissible: the frontend looks
	 * fine, so a dismiss button would invite the publisher to ignore the
	 * degraded server-side state indefinitely.
	 *
	 * @param string $connect_url Settings page URL.
	 * @return void
	 */
	private function render_legacy_notice( $connect_url ) {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Sesamy: reconnect required.', 'sesamy2' ); ?></strong>
				<?php esc_html_e( 'Your existing paywalls still render, but Capsule unlock, publisher registration, and the management API need a fresh connection after upgrading.', 'sesamy2' ); ?>
				<a href="<?php echo esc_url( $connect_url ); ?>" class="button button-primary" style="margin-left: 0.5em;"><?php esc_html_e( 'Reconnect to Sesamy', 'sesamy2' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the fresh-install variant. Per-user dismissible; once dismissed,
	 * suppressed for that user until the meta is cleared (no plugin code
	 * clears it — by design, a user who dismisses owns that decision).
	 *
	 * @param string $connect_url Settings page URL.
	 * @return void
	 */
	private function render_fresh_notice( $connect_url ) {
		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, self::DISMISSED_META, true ) ) {
			return;
		}

		$nonce = wp_create_nonce( 'sesamy_dismiss_connect_notice' );
		?>
		<div class="notice notice-info is-dismissible" data-sesamy-dismiss-nonce="<?php echo esc_attr( $nonce ); ?>">
			<p>
				<strong><?php esc_html_e( 'Welcome to Sesamy.', 'sesamy2' ); ?></strong>
				<?php esc_html_e( 'Connect this site to your Sesamy account to start gating content with paywalls.', 'sesamy2' ); ?>
				<a href="<?php echo esc_url( $connect_url ); ?>" class="button button-primary" style="margin-left: 0.5em;"><?php esc_html_e( 'Connect to Sesamy', 'sesamy2' ); ?></a>
			</p>
			<script>
				// WP core (`wp-admin/js/common.js`) injects the `.notice-dismiss`
				// button asynchronously after this markup parses, so we can't
				// attach to it directly here. Delegate from the notice instead
				// so the listener exists before core wires the button up.
				( function () {
					var notice = document.currentScript.parentElement;
					notice.addEventListener( 'click', function ( event ) {
						if ( ! event.target.classList.contains( 'notice-dismiss' ) ) {
							return;
						}
						var body = new FormData();
						body.append( 'action', 'sesamy_dismiss_connect_notice' );
						body.append( '_wpnonce', notice.dataset.sesamyDismissNonce );
						fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } );
					} );
				} )();
			</script>
		</div>
		<?php
	}

	/**
	 * Persist the per-user dismissal. Fired by the inline dismiss script in
	 * the fresh-install variant only.
	 *
	 * @return void
	 */
	public function handle_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'sesamy_dismiss_connect_notice' );
		update_user_meta( get_current_user_id(), self::DISMISSED_META, '1' );
		wp_send_json_success();
	}
}
