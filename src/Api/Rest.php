<?php
/**
 * Rest module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Api;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

use function SesamyPlugin\Helpers\get_sesamy_environment;
use function SesamyPlugin\Helpers\is_post_locked;

/**
 * Rest module.
 *
 * @package Sesamy2
 */
class Rest {
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
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	/**
	 * Registers a custom REST API route for Sesamy posts.
	 *
	 * @return void
	 */
	public function register_route() {
		register_rest_route(
			'sesamy/v1',
			'/posts/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'sesamy_post_endpoint' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'se' => [
						'validate_callback' => [ $this, 'validate_numeric_param' ],
					],
					'ss' => [],
				],
			]
		);
	}

	/**
	 * Endpoint for validating request and returning the content.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request The REST API request object.
	 * @return \WP_REST_Response|\WP_Error The response or error object.
	 */
	public function sesamy_post_endpoint( $request ) {
		$post = get_post( $request['id'] );

		// Check that post actually exists.
		if ( null === $post ) {
			return new \WP_Error( 404, __( 'Post not found.', 'sesamy' ) );
		}

		$is_locked = is_post_locked( $post->ID );

		if ( $is_locked ) {
			$auth_header = (string) $request->get_header( 'authorization' );
			if ( '' === $auth_header || 0 !== stripos( $auth_header, 'Bearer ' ) ) {
				return new \WP_Error( 401, 'unauthorized' );
			}
			$token = trim( substr( $auth_header, 7 ) );
			if ( '' === $token ) {
				return new \WP_Error( 401, 'unauthorized' );
			}

			$jwks = $this->get_sesamy_jwks();
			if ( is_wp_error( $jwks ) ) {
				return $jwks;
			}

			try {
				$decoded_token = JWT::decode( $token, JWK::parseKeySet( $jwks ) );
			} catch ( \Throwable $e ) {
				error_log( '[sesamy] REST token decode failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new \WP_Error( 401, 'unauthorized' );
			}

			if ( ! isset( $decoded_token->permissions ) ||
			! is_array( $decoded_token->permissions ) ||
			! in_array( 'vault:entitlement:manage', $decoded_token->permissions, true ) ) {
				return new \WP_Error( 403, 'unauthorized' );
			}
		}

		return new \WP_REST_Response(
			array( 'content' => apply_filters( 'the_content', $post->post_content ) )
		);
	}

	/**
	 * Validates if the given parameter is numeric.
	 *
	 * @param mixed $param The parameter to validate.
	 * @return bool True if the parameter is numeric, false otherwise.
	 */
	public function validate_numeric_param( $param ) {
		return is_numeric( $param );
	}

	/**
	 * Fetch the Sesamy JWKS used to verify entitlement-management tokens.
	 *
	 * @return array<string, mixed>|\WP_Error Decoded JWKS, or WP_Error on transport / status / parse failure.
	 */
	public function get_sesamy_jwks() {
		$tld = 'dev' === get_sesamy_environment() ? 'dev' : 'com';
		$req = wp_remote_get( "https://auth2.sesamy.{$tld}/.well-known/jwks.json" );
		if ( is_wp_error( $req ) ) {
			return $req;
		}
		$code = (int) wp_remote_retrieve_response_code( $req );
		if ( 200 !== $code ) {
			return new \WP_Error( 'sesamy_jwks_http_error', sprintf( 'JWKS fetch failed (HTTP %d).', $code ) );
		}
		$raw = (string) wp_remote_retrieve_body( $req );
		if ( '' === $raw ) {
			return new \WP_Error( 'sesamy_jwks_empty', 'JWKS response was empty.' );
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return new \WP_Error( 'sesamy_jwks_invalid_json', 'JWKS response was not valid JSON.' );
		}
		return $decoded;
	}
}
