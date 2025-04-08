<?php
/**
 * Sesamy API Client.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Api;

use function SesamyPlugin\Support\Helpers\get_sesamy_setting;

/**
 * API Client for Sesamy.
 *
 * @package Sesamy2
 */
class Client {
	/**
	 * Base API URL
	 *
	 * @var string
	 */
	private $api_base;

	/**
	 * Client ID from settings
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$dev_mode        = get_sesamy_setting( 'development_mode' );
		$this->api_base  = $dev_mode ? 'https://api.sesamy.dev' : 'https://api.sesamy.com';
		$this->client_id = get_sesamy_setting( 'client_id' );
	}

	/**
	 * Initialize the API Client.
	 *
	 * @return self
	 */
	public static function init() {
		return new self();
	}

	/**
	 * Make a GET request to the Sesamy API.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $params Query parameters.
	 * @return array|null Response data or null on error.
	 */
	public function get( $endpoint, $params = [] ) {
		return $this->request( 'GET', $endpoint, $params );
	}

	/**
	 * Make a POST request to the Sesamy API.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data Post data.
	 * @return array|null Response data or null on error.
	 */
	public function post( $endpoint, $data = [] ) {
		return $this->request( 'POST', $endpoint, [], $data );
	}

	/**
	 * Make an API request to Sesamy.
	 *
	 * @param string $method HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $params Query parameters.
	 * @param array  $data Post data.
	 * @return array|null Response data or null on error.
	 */
	private function request( $method, $endpoint, $params = [], $data = null ) {
		$url = $this->api_base . '/' . ltrim( $endpoint, '/' );

		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$args = [
			'method'      => $method,
			'timeout'     => 30,
			'redirection' => 5,
			'httpversion' => '1.1',
			'headers'     => [
				'X-Client-ID' => $this->client_id,
				'Accept'      => 'application/json',
			],
		];

		if ( 'POST' === $method && null !== $data ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = false !== wp_json_encode( $data ) ? wp_json_encode( $data ) : '';
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return $data;
	}

	/**
	 * Get JWK token for validation.
	 *
	 * @return array|null The decoded JWK keys or null on error.
	 */
	public function get_jwks() {
		$dev_mode = get_sesamy_setting( 'development_mode' );
		$jwks_url = $dev_mode ? 'https://token.sesamy.dev/.well-known/jwks.json' : 'https://token.sesamy.com/.well-known/jwks.json';
		$response = wp_remote_get( $jwks_url );
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $data;
	}
}
