<?php
/**
 * Connect / Disconnect flow against AuthHero `connect/start` (consent-mediated DCR).
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Connect;

use function SesamyPlugin\Helpers\get_sesamy_connection;
use function SesamyPlugin\Helpers\sesamy_url;

/**
 * Connect flow.
 */
class ConnectFlow {

	/**
	 * Option key holding the connection bundle.
	 */
	public const OPTION = 'sesamy_connection';

	/**
	 * Transient key prefix for state CSRF tokens. The full key is the prefix
	 * concatenated with the state value.
	 */
	private const STATE_TRANSIENT_PREFIX = 'sesamy_connect_state_';

	/**
	 * State transient TTL (seconds).
	 */
	private const STATE_TTL = 15 * MINUTE_IN_SECONDS;

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
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_sesamy_connect_start', [ $this, 'handle_connect_start' ] );
		add_action( 'admin_post_sesamy_disconnect', [ $this, 'handle_disconnect' ] );
		add_action( 'admin_init', [ $this, 'maybe_handle_connect_complete' ] );
		add_action( 'admin_notices', [ $this, 'render_admin_notices' ] );
	}

	/**
	 * Step 1: redirect the admin to AuthHero's `connect/start` with a fresh
	 * state token bound to the current WP user.
	 *
	 * @return never
	 */
	public function handle_connect_start(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to connect Sesamy.', 'sesamy2' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'sesamy_connect_start' );

		$state = bin2hex( random_bytes( 32 ) );
		set_transient(
			self::STATE_TRANSIENT_PREFIX . $state,
			[ 'user_id' => get_current_user_id() ],
			self::STATE_TTL
		);

		$home   = home_url();
		$scheme = wp_parse_url( $home, PHP_URL_SCHEME );
		$host   = wp_parse_url( $home, PHP_URL_HOST );
		$port   = wp_parse_url( $home, PHP_URL_PORT );

		$domain = $scheme . '://' . $host;
		if ( $port ) {
			$domain .= ':' . $port;
		}

		$return_to = admin_url( 'admin.php?page=sesamy&sesamy_action=connect_complete' );

		// `add_query_arg` does not URL-encode newly-merged values (it calls
		// `build_query` with `$urlencode = false`). The `return_to` value
		// contains `?` and `&` which would bleed into the parent URL on the
		// AuthHero side, so build the query string with PHP's
		// `http_build_query` which encodes values correctly.
		$authorize_url = sesamy_url( 'connect/start', 'auth' ) . '?' . http_build_query(
			[
				'integration_type' => 'wordpress',
				'domain'           => $domain,
				'return_to'        => $return_to,
				'state'            => $state,
				'tenant_id'        => 'sesamy',
			],
			'',
			'&',
			PHP_QUERY_RFC3986
		);

		$auth_host = (string) wp_parse_url( $authorize_url, PHP_URL_HOST );
		add_filter(
			'allowed_redirect_hosts',
			static function ( $hosts ) use ( $auth_host ) {
				$hosts[] = $auth_host;
				return $hosts;
			}
		);

		wp_safe_redirect( $authorize_url );
		exit;
	}

	/**
	 * Step 2: handle the redirect back from AuthHero. Validates state,
	 * exchanges the IAT for client credentials, persists them, and redirects
	 * to a clean settings URL with a status flag.
	 *
	 * @return void
	 */
	public function maybe_handle_connect_complete() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- state param provides CSRF protection.
		if ( ! isset( $_GET['page'], $_GET['sesamy_action'] ) ) {
			return;
		}
		$page   = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		$action = sanitize_text_field( wp_unslash( $_GET['sesamy_action'] ) );
		if ( 'sesamy' !== $page || 'connect_complete' !== $action ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		if ( '' === $state ) {
			$this->finish_complete( 'missing_state' );
		}

		$transient_key = self::STATE_TRANSIENT_PREFIX . $state;
		$stored        = get_transient( $transient_key );
		delete_transient( $transient_key );

		if ( ! is_array( $stored ) || (int) ( $stored['user_id'] ?? 0 ) !== get_current_user_id() ) {
			$this->finish_complete( 'invalid_state' );
		}

		if ( isset( $_GET['authhero_error'] ) ) {
			$this->finish_complete( 'cancelled' );
		}

		$iat    = isset( $_GET['authhero_iat'] ) ? sanitize_text_field( wp_unslash( $_GET['authhero_iat'] ) ) : '';
		$tenant = isset( $_GET['authhero_tenant'] ) ? sanitize_text_field( wp_unslash( $_GET['authhero_tenant'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( '' === $iat ) {
			$this->finish_complete( 'missing_token' );
		}

		$domain      = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$client_name = sprintf( 'WordPress at %s', $domain );

		$result = AuthHeroClient::register( $iat, $client_name, $tenant );
		if ( is_wp_error( $result ) ) {
			$this->finish_complete( 'registration_failed', $result->get_error_message() );
		}

		if ( empty( $result['client_id'] ) || empty( $result['client_secret'] ) ) {
			$this->finish_complete( 'registration_incomplete' );
		}

		$connection = [
			'client_id'                 => (string) $result['client_id'],
			'client_secret'             => (string) $result['client_secret'],
			'registration_access_token' => isset( $result['registration_access_token'] ) ? (string) $result['registration_access_token'] : '',
			'registration_client_uri'   => isset( $result['registration_client_uri'] ) ? (string) $result['registration_client_uri'] : '',
			'tenant'                    => $tenant,
			'integration_type'          => 'wordpress',
			'domain'                    => $domain,
			'connected_at'              => time(),
			'connected_by'              => get_current_user_id(),
		];

		update_option( self::OPTION, $connection, false );

		$this->finish_complete( 'connected' );
	}

	/**
	 * Step 3: revoke the AuthHero registration (best effort) and wipe the
	 * local connection bundle.
	 *
	 * @return never
	 */
	public function handle_disconnect(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to disconnect Sesamy.', 'sesamy2' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'sesamy_disconnect' );

		$connection = get_sesamy_connection();
		if ( $connection && ! empty( $connection['registration_client_uri'] ) && ! empty( $connection['registration_access_token'] ) ) {
			AuthHeroClient::delete_registration(
				(string) $connection['registration_client_uri'],
				(string) $connection['registration_access_token']
			);
		}

		delete_option( self::OPTION );

		wp_safe_redirect( admin_url( 'admin.php?page=sesamy&sesamy_status=disconnected' ) );
		exit;
	}

	/**
	 * Render queued status notices on the Sesamy settings page.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display from a same-origin redirect.
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		if ( 'sesamy' !== $page ) {
			return;
		}
		if ( ! isset( $_GET['sesamy_status'] ) ) {
			return;
		}
		$status = sanitize_text_field( wp_unslash( $_GET['sesamy_status'] ) );
		$detail = isset( $_GET['sesamy_detail'] ) ? sanitize_text_field( wp_unslash( $_GET['sesamy_detail'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		[ $type, $message ] = $this->message_for_status( $status, $detail );
		if ( '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Map a status code to a (notice type, human message) pair.
	 *
	 * @param string $status Status code from the redirect query.
	 * @param string $detail Optional extra context (e.g. error message).
	 * @return array{0: string, 1: string}
	 */
	private function message_for_status( $status, $detail ) {
		switch ( $status ) {
			case 'connected':
				return [ 'success', __( 'Connected to Sesamy.', 'sesamy2' ) ];
			case 'disconnected':
				return [ 'success', __( 'Disconnected from Sesamy.', 'sesamy2' ) ];
			case 'cancelled':
				return [ 'warning', __( 'Connection cancelled.', 'sesamy2' ) ];
			case 'missing_state':
			case 'invalid_state':
				return [ 'error', __( 'Connection failed: the security token did not match. Please try again.', 'sesamy2' ) ];
			case 'missing_token':
				return [ 'error', __( 'Connection failed: AuthHero did not return a registration token.', 'sesamy2' ) ];
			case 'registration_incomplete':
				return [ 'error', __( 'Connection failed: AuthHero did not return client credentials.', 'sesamy2' ) ];
			case 'registration_failed':
				$message = __( 'Connection failed during client registration.', 'sesamy2' );
				if ( '' !== $detail ) {
					$message .= ' ' . $detail;
				}
				return [ 'error', $message ];
			default:
				return [ '', '' ];
		}
	}

	/**
	 * Redirect to a clean settings URL with a status flag and exit.
	 *
	 * @param string $status Status code.
	 * @param string $detail Optional detail string (e.g. an error message).
	 * @return never
	 */
	private function finish_complete( $status, $detail = '' ): never {
		$args = [
			'page'          => 'sesamy',
			'sesamy_status' => $status,
		];
		if ( '' !== $detail ) {
			$args['sesamy_detail'] = $detail;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
