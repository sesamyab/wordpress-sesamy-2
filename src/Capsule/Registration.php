<?php
/**
 * Publisher-domain registration with the api-proxy trusted-domains allowlist.
 *
 * The hosted Sesamy issuer at `api2.sesamy.{com,dev}/capsule/vendors/{vendor}/unlock`
 * verifies each `resourceJWT` against the vendor's registered domains before
 * unwrapping wrapKeys. Without a registration the unlock fails closed and
 * the client throws "DCA: could not unwrap contentKey". This class speaks
 * the management endpoints that own that allowlist:
 *
 *   PUT    /management/domains/{domain}    body: {signingKeyPem|jwksUri}
 *   DELETE /management/domains/{domain}
 *   GET    /management/domains
 *   GET    /management/domains/{domain}
 *
 * Vendor scope is inferred from the management token's `vendor_id` claim —
 * there's no path param for vendor and a token can only manage domains
 * under its own vendor. Requires the `domains:read` and `domains:write`
 * scopes on the access token; both are claimed at DCR time
 * (`AuthHeroClient::register`).
 *
 * Mode selection: registers via JWKS URI when `home_url` is publicly
 * reachable (so the api-proxy can fetch and cache our keys, with native
 * rotation overlap), and via pinned `signingKeyPem` for local installs
 * (`localhost`, `*.test`, `*.local`, `127.0.0.1`) where the JWKS URL is not
 * reachable from the api-proxy network.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Capsule;

use SesamyPlugin\Connect\SesamyApi;

use function SesamyPlugin\Helpers\is_local_install;
use function SesamyPlugin\Helpers\is_sesamy_connected;
use function SesamyPlugin\Helpers\publisher_domain;
use function SesamyPlugin\Helpers\sesamy_url;

/**
 * Publisher registration client.
 */
class Registration {

	/**
	 * Default request timeout in seconds.
	 */
	private const TIMEOUT = 15;

	/**
	 * Option key holding the last canonical domain we auto-registered. Lets
	 * us DELETE the old entry when `home_url` changes — without it we'd
	 * accumulate stale registrations on every domain change.
	 */
	private const AUTO_DOMAIN_OPTION = 'sesamy_capsule_auto_registered_domain';

	/**
	 * Initialize the module.
	 *
	 * @return self
	 */
	public static function init() {
		$instance = new self();
		$instance->register_hooks();
		return $instance;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_post_sesamy_register_publisher', [ $this, 'handle_register_publisher' ] );
		add_action( 'admin_post_sesamy_unregister_publisher', [ $this, 'handle_unregister_publisher' ] );
		add_action( 'admin_post_sesamy_register_publisher_self', [ $this, 'handle_register_self' ] );

		// `home_url` changes domain → re-register. `update_option_*` actions
		// fire only when the value actually changes, so no extra guard here.
		add_action( 'update_option_home', [ $this, 'on_home_url_changed' ], 10, 2 );
		add_action( 'update_option_siteurl', [ $this, 'on_home_url_changed' ], 10, 2 );

		// Connect-flow completion seeds `sesamy_connection`. Register the
		// canonical domain as soon as we have credentials. Adding (not
		// updating) catches first connect; updating handles reconnect after
		// disconnect.
		add_action( 'add_option_sesamy_connection', [ $this, 'on_connection_added' ], 10, 2 );
		add_action( 'update_option_sesamy_connection', [ $this, 'on_connection_updated' ], 10, 2 );

		// On disconnect, best-effort drop our auto-registered entry so the
		// vendor's allowlist doesn't keep a stale key tied to credentials
		// that no longer exist.
		add_action( 'delete_option_sesamy_connection', [ $this, 'on_disconnect' ] );
	}

	// ---------------------------------------------------------------------
	// HTTP client — raw endpoints. Each returns an associative array (decoded
	// JSON) on success, or a WP_Error on failure.
	// ---------------------------------------------------------------------

	/**
	 * `PUT /management/capsule/publishers/{domain}`.
	 *
	 * @param string                                          $domain Domain to register (must match `resourceJWT.iss`).
	 * @param array{signingKeyPem?: string, jwksUri?: string} $body   One of `signingKeyPem` or `jwksUri`.
	 * @return array<int|string, mixed>|\WP_Error
	 */
	public static function put_publisher( $domain, $body ) {
		return self::request( 'PUT', $domain, $body );
	}

	/**
	 * `DELETE /management/capsule/publishers/{domain}`.
	 *
	 * @param string $domain Domain to unregister.
	 * @return array<int|string, mixed>|\WP_Error
	 */
	public static function delete_publisher( $domain ) {
		return self::request( 'DELETE', $domain );
	}

	/**
	 * `GET /management/capsule/publishers/{domain}`.
	 *
	 * @param string $domain Domain to look up.
	 * @return array<int|string, mixed>|\WP_Error
	 */
	public static function get_publisher( $domain ) {
		return self::request( 'GET', $domain );
	}

	/**
	 * `GET /management/capsule/publishers`.
	 *
	 * @return list<array<string, mixed>>|\WP_Error
	 */
	public static function list_publishers() {
		$result = self::request( 'GET', '' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Be tolerant of envelope shape: `[...]` / `{publishers: [...]}` /
		// `{data: [...]}`. Mirrors how `SesamyApi::get_paywalls` defends.
		if ( array_key_exists( 0, $result ) ) {
			return array_values( $result );
		}
		if ( isset( $result['publishers'] ) && is_array( $result['publishers'] ) ) {
			return array_values( $result['publishers'] );
		}
		if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
			return array_values( $result['data'] );
		}
		return [];
	}

	// ---------------------------------------------------------------------
	// High-level operations
	// ---------------------------------------------------------------------

	/**
	 * Register the canonical home_url domain with whatever mode best fits the
	 * environment. Returns the api-proxy response on success.
	 *
	 * @return array<int|string, mixed>|\WP_Error
	 */
	public static function register_self() {
		$domain = publisher_domain();
		$result = self::register_domain( $domain );
		if ( ! is_wp_error( $result ) ) {
			update_option( self::AUTO_DOMAIN_OPTION, $domain, false );
		}
		return $result;
	}

	/**
	 * Register a specific domain. Mode is auto-selected: JWKS URI for the
	 * canonical domain on a publicly-reachable install, pinned PEM otherwise
	 * (including any non-canonical domain — we only host JWKS at our own
	 * home_url, so other domains would need their PEM pinned).
	 *
	 * @param string      $domain Domain to register.
	 * @param string|null $mode   Force `pem` or `jwks_uri`. When null, auto.
	 * @return array<int|string, mixed>|\WP_Error
	 */
	public static function register_domain( $domain, $mode = null ) {
		$domain = self::normalize_domain( $domain );
		if ( '' === $domain ) {
			return new \WP_Error( 'sesamy_invalid_domain', __( 'Invalid domain.', 'sesamy2' ) );
		}

		if ( null === $mode ) {
			$mode = self::pick_mode( $domain );
		}

		if ( 'jwks_uri' === $mode ) {
			$body = [ 'jwksUri' => self::self_jwks_uri() ];
		} elseif ( 'pem' === $mode ) {
			$pem = self::publisher_signing_pem();
			if ( '' === $pem ) {
				return new \WP_Error(
					'sesamy_no_publisher_key',
					__( 'No publisher signing key has been generated yet — render a capsule-locked post first.', 'sesamy2' )
				);
			}
			$body = [ 'signingKeyPem' => $pem ];
		} else {
			return new \WP_Error( 'sesamy_invalid_mode', sprintf( 'Unknown mode "%s".', $mode ) );
		}

		return self::put_publisher( $domain, $body );
	}

	/**
	 * Unregister a domain. Idempotent — a missing entry returns success.
	 *
	 * @param string $domain Domain to unregister.
	 * @return array<int|string, mixed>|\WP_Error
	 */
	public static function unregister_domain( $domain ) {
		$domain = self::normalize_domain( $domain );
		if ( '' === $domain ) {
			return new \WP_Error( 'sesamy_invalid_domain', __( 'Invalid domain.', 'sesamy2' ) );
		}
		$result = self::delete_publisher( $domain );

		// If we just deleted the domain we had recorded as auto-registered,
		// clear the marker so on_home_url_changed doesn't try to re-DELETE.
		if ( get_option( self::AUTO_DOMAIN_OPTION ) === $domain ) {
			delete_option( self::AUTO_DOMAIN_OPTION );
		}
		return $result;
	}

	// ---------------------------------------------------------------------
	// Hook callbacks
	// ---------------------------------------------------------------------

	/**
	 * On `update_option_{home,siteurl}`. Re-register if the host actually
	 * changed (changing scheme or path doesn't affect `iss`). Best-effort —
	 * a network failure here shouldn't block the option update, so swallow
	 * the WP_Error.
	 *
	 * @param mixed $old       Old option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function on_home_url_changed( $old, $new_value ) {
		if ( ! is_sesamy_connected() ) {
			return;
		}
		$old_host = self::normalize_domain( (string) wp_parse_url( (string) $old, PHP_URL_HOST ) );
		$new_host = self::normalize_domain( (string) wp_parse_url( (string) $new_value, PHP_URL_HOST ) );
		if ( $old_host === $new_host ) {
			return;
		}
		if ( '' !== $old_host ) {
			self::delete_publisher( $old_host );
		}
		if ( '' !== $new_host ) {
			self::register_domain( $new_host );
			update_option( self::AUTO_DOMAIN_OPTION, $new_host, false );
		}
	}

	/**
	 * On first connect. The connection bundle has just been written;
	 * `SesamyApi::get_access_token()` will mint a fresh token off it.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New option value.
	 * @return void
	 */
	public function on_connection_added( $option, $value ) {
		unset( $option, $value );
		self::register_self();
	}

	/**
	 * On reconnect (disconnect → connect with potentially new credentials).
	 *
	 * @param mixed $old       Old option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function on_connection_updated( $old, $new_value ) {
		unset( $old, $new_value );
		self::register_self();
	}

	/**
	 * On disconnect. Drop our auto-registered entry — the credentials we'd
	 * use to call DELETE are about to be wiped, but we still have them at
	 * the moment this fires (it's `delete_option_*`, before the row is
	 * actually removed isn't true — but `get_access_token()` reads cached
	 * transient first, which survives until expiry). Best-effort.
	 *
	 * @return void
	 */
	public function on_disconnect() {
		$domain = (string) get_option( self::AUTO_DOMAIN_OPTION, '' );
		if ( '' !== $domain ) {
			self::delete_publisher( $domain );
		}
		delete_option( self::AUTO_DOMAIN_OPTION );
	}

	// ---------------------------------------------------------------------
	// admin-post handlers
	// ---------------------------------------------------------------------

	/**
	 * Form handler: PUT a domain entered in the settings UI.
	 *
	 * @return never
	 */
	public function handle_register_publisher(): never {
		$this->guard( 'sesamy_register_publisher' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() above ran check_admin_referer.
		$domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- same.
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';
		$mode = in_array( $mode, [ 'pem', 'jwks_uri' ], true ) ? $mode : null;

		$result = self::register_domain( $domain, $mode );
		$this->finish_with( $result, 'registered', $domain );
	}

	/**
	 * Form handler: DELETE a domain.
	 *
	 * @return never
	 */
	public function handle_unregister_publisher(): never {
		$this->guard( 'sesamy_unregister_publisher' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() above ran check_admin_referer.
		$domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';

		$result = self::unregister_domain( $domain );
		$this->finish_with( $result, 'unregistered', $domain );
	}

	/**
	 * Form handler: re-register the canonical home_url domain (manual
	 * trigger from the settings UI).
	 *
	 * @return never
	 */
	public function handle_register_self(): never {
		$this->guard( 'sesamy_register_publisher_self' );

		$result = self::register_self();
		$this->finish_with( $result, 'registered', publisher_domain() );
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * Build the absolute URL for a publisher endpoint. Always direct (server-
	 * to-server management traffic doesn't need first-party cookies).
	 *
	 * @param string $domain Optional domain; pass `''` for the list endpoint.
	 * @return string
	 */
	private static function endpoint_url( $domain ) {
		$path = 'management/domains';
		if ( '' !== $domain ) {
			$path .= '/' . rawurlencode( $domain );
		}
		return sesamy_url( $path, 'api', true );
	}

	/**
	 * Issue a request to the management API.
	 *
	 * @param string                    $method HTTP method.
	 * @param string                    $domain Domain segment, or `''` for the list.
	 * @param array<string, mixed>|null $body   Request body for PUT.
	 * @return array<int|string, mixed>|\WP_Error Decoded JSON — assoc for single-publisher and PUT/DELETE responses, list for the index endpoint.
	 */
	private static function request( $method, $domain, $body = null ) {
		$token = SesamyApi::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = [
			'method'    => $method,
			'timeout'   => self::TIMEOUT,
			'sslverify' => true,
			'headers'   => [
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			],
		];
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = (string) wp_json_encode( $body );
		}

		$url      = self::endpoint_url( $domain );
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			if ( is_local_install() ) {
				$response->add( 'sesamy_request_debug', self::format_request_debug( $method, $url, $args ) );
			}
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$json = '' !== $raw ? json_decode( $raw, true ) : null;

		// Mirror SesamyApi: an auth failure invalidates the cached token so
		// the next call mints a fresh one (often after a credential rotate).
		if ( 401 === $code || 403 === $code ) {
			delete_transient( 'sesamy_access_token' );
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $json ) ? ( $json['error_description'] ?? $json['error'] ?? $json['message'] ?? '' ) : '';
			if ( '' === $message ) {
				$message = sprintf( 'Capsule registration request failed (HTTP %d).', $code );
				if ( '' !== $raw ) {
					$message .= "\n\n" . substr( $raw, 0, 1000 );
				}
			}
			if ( is_local_install() ) {
				$message .= "\n\n" . self::format_request_debug( $method, $url, $args );
			}
			return new \WP_Error(
				'sesamy_capsule_registration_failed',
				$message,
				[
					'status' => $code,
					'body'   => $raw,
				]
			);
		}

		return is_array( $json ) ? $json : [];
	}

	/**
	 * Render a request summary for dev-mode error messages: method, URL,
	 * headers (with bearer redacted), and body (with PEM payload truncated).
	 * Gated by is_local_install() at the call site so this never leaks key
	 * material into a production admin notice.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $url    Absolute endpoint URL.
	 * @param array<string, mixed> $args   The wp_remote_request args, post-build.
	 * @return string
	 */
	private static function format_request_debug( $method, $url, array $args ) {
		$headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : [];
		if ( isset( $headers['Authorization'] ) ) {
			$headers['Authorization'] = 'Bearer <redacted>';
		}

		$body = isset( $args['body'] ) ? (string) $args['body'] : '';
		if ( '' !== $body ) {
			$decoded = json_decode( $body, true );
			if ( is_array( $decoded ) && isset( $decoded['signingKeyPem'] ) ) {
				$pem                      = (string) $decoded['signingKeyPem'];
				$decoded['signingKeyPem'] = sprintf( '<%d-byte PEM, sha256=%s>', strlen( $pem ), substr( hash( 'sha256', $pem ), 0, 12 ) );
				$body                     = (string) wp_json_encode( $decoded );
			} elseif ( strlen( $body ) > 500 ) {
				$body = substr( $body, 0, 500 ) . '…';
			}
		}

		$lines = [ sprintf( '%s %s', $method, $url ) ];
		foreach ( $headers as $name => $value ) {
			$lines[] = sprintf( '%s: %s', $name, $value );
		}
		if ( '' !== $body ) {
			$lines[] = '';
			$lines[] = $body;
		}
		return "Request:\n" . implode( "\n", $lines );
	}

	/**
	 * Decide registration mode for a domain. Public, reachable HTTPS domains
	 * use the JWKS URI we already serve at `/.well-known/dca-publishers.json`
	 * (native rotation overlap, no PEM management). Localhost / `.local` /
	 * `.test` / IP installs can't be reached from the api-proxy, so pin the
	 * PEM directly.
	 *
	 * Non-canonical domains (admin-added) default to PEM too — we only host
	 * the JWKS at our own home_url, so a different domain wouldn't have a
	 * matching JWKS endpoint to point at.
	 *
	 * @param string $domain Normalised domain.
	 * @return string `pem` or `jwks_uri`.
	 */
	private static function pick_mode( $domain ) {
		if ( publisher_domain() !== $domain ) {
			return 'pem';
		}
		if ( is_local_install() ) {
			return 'pem';
		}
		if ( ! str_starts_with( (string) home_url(), 'https://' ) ) {
			// api-proxy validation rejects non-https jwksUri; pin PEM instead
			// so the publisher still works on plain-http public dev hosts.
			return 'pem';
		}
		return 'jwks_uri';
	}

	/**
	 * Build the JWKS URI we serve from `Capsule\Jwks::register_rewrites`.
	 *
	 * @return string
	 */
	private static function self_jwks_uri() {
		return home_url( '/.well-known/dca-publishers.json' );
	}

	/**
	 * Read the publisher's PUBLIC signing key PEM, generating the bundle on
	 * demand if it doesn't exist yet (otherwise auto-register on first
	 * connect would fail until the operator visits a capsule-locked page).
	 * Returns `''` only if generation itself fails.
	 *
	 * @return string
	 */
	private static function publisher_signing_pem() {
		try {
			$keys = Service::publisher_keys();
		} catch ( \Throwable $e ) {
			return '';
		}
		return (string) $keys['signing_public_key_pem'];
	}

	/**
	 * Normalise a host: lowercase, strip trailing dot. Returns `''` for
	 * obviously invalid input.
	 *
	 * @param string $domain Raw host.
	 * @return string
	 */
	private static function normalize_domain( $domain ) {
		$domain = strtolower( trim( (string) $domain ) );
		$domain = rtrim( $domain, '.' );
		// Reject anything that doesn't look hostname-shaped — keeps obviously
		// bad input out of the URL path before we hit the api-proxy.
		if ( 1 !== preg_match( '/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $domain ) ) {
			return '';
		}
		return $domain;
	}

	/**
	 * Capability + nonce + connection guard for admin-post handlers. Aborts
	 * on failure.
	 *
	 * @param string $nonce_action Nonce action key.
	 * @return void
	 */
	private function guard( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Sesamy publishers.', 'sesamy2' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( $nonce_action );
		if ( ! is_sesamy_connected() ) {
			wp_die( esc_html__( 'Sesamy is not connected.', 'sesamy2' ), '', [ 'response' => 400 ] );
		}
	}

	/**
	 * Redirect back to the settings page with a `sesamy_publisher_status`
	 * flag the SettingsPage renderer turns into an admin notice.
	 *
	 * @param array<int|string, mixed>|\WP_Error $result Outcome of the operation.
	 * @param string                             $ok     Status string on success (`registered` / `unregistered`).
	 * @param string                             $domain Domain the action targeted.
	 * @return never
	 */
	private function finish_with( $result, $ok, $domain ): never {
		$args = [
			'page'                    => 'sesamy',
			'sesamy_publisher_domain' => $domain,
		];
		if ( is_wp_error( $result ) ) {
			$args['sesamy_publisher_status']  = 'error';
			$args['sesamy_publisher_message'] = $result->get_error_message();
		} else {
			$args['sesamy_publisher_status'] = $ok;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) . '#sesamy-publisher-registration' );
		exit;
	}
}
