<?php
/**
 * Sesamy management API client. Uses the connected client's
 * `client_credentials` grant to call api.sesamy.com on behalf of the publisher.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Connect;

use function SesamyPlugin\Helpers\get_sesamy_connection;
use function SesamyPlugin\Helpers\get_sesamy_environment;
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
	 * Transient key prefix holding the cached access token. The full key is
	 * derived per connection by `token_transient_key()` so cached tokens
	 * invalidate automatically across disconnect/reconnect cycles.
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
		$connection = get_sesamy_connection();
		if ( ! is_array( $connection ) || empty( $connection['client_id'] ) || empty( $connection['client_secret'] ) ) {
			return new \WP_Error( 'sesamy_not_connected', 'Sesamy is not connected.' );
		}

		$transient_key = self::token_transient_key( $connection );

		$cached = get_transient( $transient_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		// RFC 6749 §3.2 mandates form-urlencoded at the token endpoint.
		// AuthHero rejects the JSON variant with a generic 403, which surfaces
		// as "Client not found" because tenant routing falls through. Sending
		// `tenant-id` alongside `client_id` would also break: AuthHero
		// resolves the client in the named tenant first and replies the same
		// way when it isn't there. Audience is omitted — the management API
		// accepts the unscoped token issued without it (matches the curl
		// recipe in the AuthHero docs).
		// The token endpoint lives on its own subdomain (`token.sesamy.{tld}`),
		// distinct from the OIDC/AuthHero host (`auth2.sesamy.{tld}`) used for
		// `/oidc/register` during connect.
		$tld       = 'dev' === get_sesamy_environment() ? 'dev' : 'com';
		$token_url = "https://token.sesamy.{$tld}/oauth/token";
		$headers   = [
			'Content-Type' => 'application/x-www-form-urlencoded',
			'Accept'       => 'application/json',
		];
		$body      = [
			'grant_type'    => 'client_credentials',
			'client_id'     => (string) $connection['client_id'],
			'client_secret' => (string) $connection['client_secret'],
		];

		$response = wp_remote_post(
			$token_url,
			[
				'timeout'   => self::TIMEOUT,
				'sslverify' => true,
				'headers'   => $headers,
				'body'      => $body,
			]
		);

		// Default to a redacted curl. Aggregated logs may pick up these
		// WP_Error messages, so we never want the client_secret in there.
		// Operators on a local box who need byte-for-byte verification can
		// define `SESAMY_REVEAL_CLIENT_SECRET` to opt in explicitly — relying
		// on WP_DEBUG isn't safe since it's enabled on staging too.
		$reveal_secret = defined( 'SESAMY_REVEAL_CLIENT_SECRET' ) && SESAMY_REVEAL_CLIENT_SECRET;
		$curl          = self::build_curl( $token_url, $headers, $body, $reveal_secret );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				$response->get_error_code(),
				$response->get_error_message() . "\n\nEquivalent curl:\n" . $curl,
				array_merge( (array) $response->get_error_data(), [ 'curl' => $curl ] )
			);
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 || ! is_array( $decoded ) ) {
			$parsed  = is_array( $decoded ) ? ( $decoded['error_description'] ?? $decoded['error'] ?? '' ) : '';
			$message = '' !== $parsed
				? $parsed
				: sprintf( 'Token request failed (HTTP %d).', (int) $code );

			// Always append the raw body when we couldn't extract a structured
			// error — it's the only signal the operator has to figure out why
			// AuthHero rejected the request. Not sensitive: it's the error
			// response, not the token.
			if ( '' === $parsed && '' !== $raw ) {
				$message .= "\n\n" . substr( $raw, 0, 2000 );
			}

			$message .= "\n\nEquivalent curl:\n" . $curl;

			return new \WP_Error(
				'sesamy_token_failed',
				$message,
				[
					'status' => $code,
					'body'   => $raw,
					'curl'   => $curl,
				]
			);
		}

		$token = isset( $decoded['access_token'] ) ? (string) $decoded['access_token'] : '';
		if ( '' === $token ) {
			return new \WP_Error( 'sesamy_token_missing', 'Token endpoint did not return an access_token.' );
		}

		$expires_in = isset( $decoded['expires_in'] ) ? (int) $decoded['expires_in'] : 0;
		$ttl        = max( 60, $expires_in - self::TOKEN_TTL_MARGIN );
		set_transient( $transient_key, $token, $ttl );

		return $token;
	}

	/**
	 * Build a connection-scoped transient key for the cached access token so
	 * a disconnect/reconnect cycle (different client_id or client_secret)
	 * never serves the previous client's token.
	 *
	 * @param array<string, mixed> $connection Connection bundle from `get_sesamy_connection()`.
	 */
	private static function token_transient_key( array $connection ): string {
		$identity = ( $connection['client_id'] ?? '' ) . '|' . ( $connection['client_secret'] ?? '' );
		return self::TOKEN_TRANSIENT . '_' . substr( hash( 'sha256', $identity ), 0, 32 );
	}

	/**
	 * Drop the cached access token for the currently-connected client. Called
	 * by callers that received a 401/403 from the management API so the next
	 * call mints a fresh token.
	 */
	public static function invalidate_cached_token(): void {
		$connection = get_sesamy_connection();
		if ( is_array( $connection ) ) {
			delete_transient( self::token_transient_key( $connection ) );
		}
	}

	/**
	 * Build a copy-paste-runnable curl command equivalent to a token request.
	 *
	 * Used to surface the exact request shape on failure so the operator can
	 * diff it against a known-good invocation. Each form field is rendered as
	 * a separate `--data-urlencode` line (matching the AuthHero docs example)
	 * so the secret stays readable rather than getting URL-encoded into one
	 * blob.
	 *
	 * @param string                $url            Token endpoint URL.
	 * @param array<string, string> $headers        Request headers.
	 * @param array<string, string> $body           Form-encoded body fields.
	 * @param bool                  $reveal_secret  When false, redact `client_secret` so the curl is safe for log aggregation.
	 * @return string
	 */
	private static function build_curl( $url, $headers, $body, $reveal_secret = false ) {
		$lines = [ "curl --location '" . $url . "' \\" ];
		foreach ( $headers as $name => $value ) {
			$lines[] = "  --header '" . $name . ': ' . $value . "' \\";
		}
		$last = array_key_last( $body );
		foreach ( $body as $name => $value ) {
			$rendered = ( ! $reveal_secret && 'client_secret' === $name ) ? '<redacted>' : $value;
			$line     = "  --data-urlencode '" . $name . '=' . $rendered . "'";
			if ( $name !== $last ) {
				$line .= ' \\';
			}
			$lines[] = $line;
		}
		return implode( "\n", $lines );
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
			// Server-to-server management call — bypass the proxy.
			sesamy_url( 'management/paywalls', 'api', true ),
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
			self::invalidate_cached_token();
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

	/**
	 * Fetch the publisher's passes from the management API.
	 *
	 * `/management/products` returns every product (passes, bundles,
	 * licenses, …) with a `productType` discriminator. We narrow to
	 * entries with `productType === 'pass'` for the pass picker on the
	 * settings page. The product `sku` is used as the stable identifier
	 * (stored in `default_pass`); `title` is the display label.
	 *
	 * @return array<int, array<string, mixed>>|\WP_Error List of pass objects.
	 */
	public static function get_passes() {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_get(
			// Server-to-server management call — bypass the proxy.
			sesamy_url( 'management/products', 'api', true ),
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
			self::invalidate_cached_token();
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $decoded ) ? ( $decoded['error_description'] ?? $decoded['error'] ?? '' ) : '';
			if ( '' === $message ) {
				$message = sprintf( 'Product list request failed (HTTP %d).', (int) $code );
			}
			return new \WP_Error( 'sesamy_products_failed', $message, [ 'status' => $code ] );
		}

		$products = [];
		if ( is_array( $decoded ) && isset( $decoded[0] ) ) {
			$products = $decoded;
		} elseif ( is_array( $decoded ) && isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) {
			$products = $decoded['data'];
		} elseif ( is_array( $decoded ) && isset( $decoded['products'] ) && is_array( $decoded['products'] ) ) {
			$products = $decoded['products'];
		}

		$passes = [];
		foreach ( $products as $product ) {
			if ( is_array( $product ) && isset( $product['productType'] ) && 'pass' === (string) $product['productType'] ) {
				$passes[] = $product;
			}
		}
		return $passes;
	}
}
