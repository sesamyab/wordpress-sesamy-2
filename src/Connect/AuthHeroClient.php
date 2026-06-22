<?php
/**
 * Thin HTTP wrapper around AuthHero's `/oidc/register` (RFC 7591) and
 * RFC 7592 client management endpoint.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Connect;

use function SesamyPlugin\Helpers\sesamy_url;

/**
 * AuthHero RFC 7591 / 7592 client.
 */
class AuthHeroClient {

	/**
	 * Default request timeout in seconds.
	 */
	private const TIMEOUT = 10;

	/**
	 * Redeem an Initial Access Token at `/oidc/register` and receive
	 * `client_id` + `client_secret` (and registration management credentials
	 * if the AuthHero deployment implements RFC 7592).
	 *
	 * On control-plane flows (the user picked a child tenant during consent),
	 * `connect/start` returns `authhero_tenant` alongside the IAT. AuthHero's
	 * `/oidc/register` resolves the tenant from a `tenant-id` request header,
	 * so we forward it when present.
	 *
	 * @param string $iat         Initial Access Token from `connect/start`.
	 * @param string $client_name Human-readable name shown in AuthHero admin.
	 * @param string $tenant      Optional tenant id from `authhero_tenant`.
	 * @return array<string, mixed>|\WP_Error Decoded response or error.
	 */
	public static function register( $iat, $client_name, $tenant = '' ) {
		$body = wp_json_encode(
			[
				'client_id'   => 'dcr',
				'client_name' => $client_name,
				'grant_types' => [ 'client_credentials' ],
				'audience'    => 'urn:sesamy',
				// `domains:read` / `domains:write` are required for the
				// publisher-registration flow at `/management/domains/*`. New
				// installs get them on first connect; existing installs need
				// to disconnect/reconnect to re-run DCR with the expanded
				// scope set (the access token cached today won't carry the
				// new claims either way).
				'scope'       => 'paywalls:read products:read domains:read domains:write',
			]
		);
		if ( false === $body ) {
			return new \WP_Error( 'sesamy_register_encode_failed', 'Failed to encode registration request.' );
		}

		$headers = [
			'Authorization' => 'Bearer ' . $iat,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		];
		if ( '' !== $tenant ) {
			$headers['tenant-id'] = $tenant;
		}

		$response = wp_remote_post(
			// Server-to-server: bypass the proxy and hit AuthHero directly.
			sesamy_url( 'oidc/register', 'auth', true ),
			[
				'timeout'   => self::TIMEOUT,
				'sslverify' => true,
				'headers'   => $headers,
				'body'      => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			$message = is_array( $body ) ? ( $body['error_description'] ?? $body['error'] ?? '' ) : '';
			if ( '' === $message ) {
				$message = sprintf( 'Registration failed (HTTP %d).', (int) $code );
			}
			return new \WP_Error(
				'sesamy_register_failed',
				$message,
				[ 'status' => $code ]
			);
		}

		return $body;
	}

	/**
	 * Revoke a registered client via RFC 7592 DELETE on the registration
	 * client URI returned at registration time. Best-effort: the caller
	 * should wipe local credentials regardless of the result.
	 *
	 * @param string $registration_client_uri  Per-client management URL.
	 * @param string $registration_access_token Bearer token returned at registration.
	 * @return true|\WP_Error
	 */
	public static function delete_registration( $registration_client_uri, $registration_access_token ) {
		// Validate the URL before forwarding the bearer token. AuthHero
		// returned it during DCR, but the value is stored locally and could
		// have been tampered with; refuse anything that isn't an HTTPS URL on
		// the expected Sesamy auth host so the access token can't be
		// exfiltrated to an attacker-controlled origin.
		if ( ! is_string( $registration_client_uri ) || '' === $registration_client_uri ) {
			return new \WP_Error( 'sesamy_unregister_invalid_uri', 'Missing registration_client_uri.' );
		}
		$parts = wp_parse_url( $registration_client_uri );
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) ) {
			return new \WP_Error( 'sesamy_unregister_invalid_uri', 'registration_client_uri must be HTTPS.' );
		}
		$host = strtolower( (string) $parts['host'] );
		if ( ! preg_match( '/(^|\.)sesamy\.(com|dev)$/', $host ) ) {
			return new \WP_Error( 'sesamy_unregister_invalid_uri', 'registration_client_uri host is not in the Sesamy allowlist.' );
		}

		$response = wp_remote_request(
			$registration_client_uri,
			[
				'method'    => 'DELETE',
				'timeout'   => self::TIMEOUT,
				'sslverify' => true,
				'headers'   => [
					'Authorization' => 'Bearer ' . $registration_access_token,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new \WP_Error(
			'sesamy_unregister_failed',
			sprintf( 'Unregister failed (HTTP %d).', (int) $code ),
			[ 'status' => $code ]
		);
	}
}
