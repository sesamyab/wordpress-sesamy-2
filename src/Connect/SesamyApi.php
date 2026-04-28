<?php
/**
 * Sesamy management API client. Uses the connected client's
 * `client_credentials` grant to call api.sesamy.com on behalf of the publisher.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Connect;

use function SesamyPlugin\Helpers\get_sesamy_connection;
use function SesamyPlugin\Helpers\sesamy_url;

/**
 * Sesamy management API client.
 */
class SesamyApi {

	/**
	 * Default request timeout in seconds.
	 */
	private const TIMEOUT = 10;

	/**
	 * Audience for management-API access tokens.
	 */
	private const AUDIENCE = 'urn:sesamy';

	/**
	 * Transient key holding the cached access token.
	 */
	private const TOKEN_TRANSIENT = 'sesamy_access_token';

	/**
	 * Safety margin (seconds) subtracted from a token's `expires_in` so the
	 * cache invalidates before AuthHero would actually reject it.
	 */
	private const TOKEN_TTL_MARGIN = 60;

	/**
	 * Get a management-API access token for the connected client. Cached in a
	 * transient until shortly before the issuer's reported expiry.
	 *
	 * @return string|\WP_Error
	 */
	public static function get_access_token() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$connection = get_sesamy_connection();
		if ( ! is_array( $connection ) || empty( $connection['client_id'] ) || empty( $connection['client_secret'] ) ) {
			return new \WP_Error( 'sesamy_not_connected', 'Sesamy is not connected.' );
		}

		$body = wp_json_encode(
			[
				'grant_type'    => 'client_credentials',
				'client_id'     => (string) $connection['client_id'],
				'client_secret' => (string) $connection['client_secret'],
				'audience'      => self::AUDIENCE,
			]
		);
		if ( false === $body ) {
			return new \WP_Error( 'sesamy_token_encode_failed', 'Failed to encode token request.' );
		}

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		];
		if ( ! empty( $connection['tenant'] ) ) {
			$headers['tenant-id'] = (string) $connection['tenant'];
		}

		$response = wp_remote_post(
			sesamy_url( 'oauth/token', 'auth' ),
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

		$code    = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $decoded ) ) {
			$message = is_array( $decoded ) ? ( $decoded['error_description'] ?? $decoded['error'] ?? '' ) : '';
			if ( '' === $message ) {
				$message = sprintf( 'Token request failed (HTTP %d).', (int) $code );
			}
			return new \WP_Error( 'sesamy_token_failed', $message, [ 'status' => $code ] );
		}

		$token = isset( $decoded['access_token'] ) ? (string) $decoded['access_token'] : '';
		if ( '' === $token ) {
			return new \WP_Error( 'sesamy_token_missing', 'Token endpoint did not return an access_token.' );
		}

		$expires_in = isset( $decoded['expires_in'] ) ? (int) $decoded['expires_in'] : 0;
		$ttl        = max( 60, $expires_in - self::TOKEN_TTL_MARGIN );
		set_transient( self::TOKEN_TRANSIENT, $token, $ttl );

		return $token;
	}

	/**
	 * Fetch the publisher's paywalls from the management API.
	 *
	 * @return array<int, array<string, mixed>>|\WP_Error List of paywall objects.
	 */
	public static function get_paywalls() {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_get(
			sesamy_url( 'management/paywalls', 'api' ),
			[
				'timeout'   => self::TIMEOUT,
				'sslverify' => true,
				'headers'   => [
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === (int) $code || 403 === (int) $code ) {
			delete_transient( self::TOKEN_TRANSIENT );
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $decoded ) ? ( $decoded['error_description'] ?? $decoded['error'] ?? '' ) : '';
			if ( '' === $message ) {
				$message = sprintf( 'Paywall list request failed (HTTP %d).', (int) $code );
			}
			return new \WP_Error( 'sesamy_paywalls_failed', $message, [ 'status' => $code ] );
		}

		// The endpoint may return a bare array or `{ data: [...] }` / `{ paywalls: [...] }`.
		if ( is_array( $decoded ) && isset( $decoded[0] ) ) {
			return $decoded;
		}
		if ( is_array( $decoded ) && isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) {
			return $decoded['data'];
		}
		if ( is_array( $decoded ) && isset( $decoded['paywalls'] ) && is_array( $decoded['paywalls'] ) ) {
			return $decoded['paywalls'];
		}

		return [];
	}
}
