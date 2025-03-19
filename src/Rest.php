<?php
/**
 * Rest API module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin;

use TenupFramework\Module;
use TenupFramework\ModuleInterface;
use JOSE_JWK;
use JOSE_JWT;

use function SesamyPlugin\Helpers\is_config_valid;


/**
 * Rest API module.
 *
 * @package Sesamy2
 */
class Rest implements ModuleInterface {

	use Module;

	/**
	 * Can this module be registered?
	 *
	 * @return bool
	 */
	public function can_register() {
		return is_config_valid();
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
				'callback'            => [ $this, 'sesamy_post_ep' ],
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
	 * Endpoint for validating request and returning the content
	 *
	 * @param string $request Request method.
	 */
	public function sesamy_post_ep( $request ) {
		$post = get_post( $request['id'] );

		// Check that post actually exists.
		if ( null === $post ) {
			return new \WP_Error( 404, __( 'Post not found.', 'sesamy' ) );
		}

		// Get JWT token from the authorization header.
		$jwt = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';

		// If the post is locked, verify the JWT token. If not, just return the content.
		$is_locked = get_post_meta( $post->ID, '_sesamy_locked', true );
		$result    = $is_locked && preg_match( '/^\s*Bearer/i', $jwt ) ? $this->verify_jwt( $jwt ) : true;

		if ( is_wp_error( $result ) ) {
			return $result;
		} elseif ( is_bool( $result ) && true === $result ) {
			return new \WP_REST_Response( array( 'data' => apply_filters( 'the_content', $post->post_content ) ) );
		} else {
			return new \WP_Error( 400, __( 'The link is incorrect or no longer valid.', 'sesamy' ) );
		}
	}

	/**
	 * Verifies the provided JWT token using the Sesamy public key.
	 *
	 * @param string $jwt The JWT token to verify.
	 * @return bool|\WP_Error True if the token is valid, false otherwise, or a WP_Error on failure.
	 */
	public function verify_jwt( $jwt ) {
		// Get the public key from sesamy vault.
		$jwks = $this->get_sesamy_jwks();

		// Decode the JWKS.
		$jwks = json_decode( $jwks, true );

		// Strip Bearer from token.
		$jwt = str_replace( 'Bearer ', '', $jwt );

		// Parse JWKS to create a JWKSet object.
		$jwk_set = JOSE_JWK::decode( $jwks );

		// Create a JWS object from the JWT.
		$jws = JOSE_JWT::decode( $jwt );

		try {
			// Verify the signature.
			$verified = $jws->verify( $jwk_set );

			if ( $verified ) {
				return true;
			} else {
				return false;
			}
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Retrieves the JSON Web Key Set (JWKS) from the Sesamy assets URL.
	 *
	 * @return string The JWKS as a JSON string.
	 */
	public function get_sesamy_jwks() {
		$req = wp_remote_get( $this->get_sesamy_assets_url() . '/vault-jwks.json' );
		return wp_remote_retrieve_body( $req );
	}

	/**
	 * Retrieves the Sesamy assets URL.
	 *
	 * @return string The URL of the Sesamy assets.
	 */
	public function get_sesamy_assets_url() {
		// return ( defined( 'SESAMY_DEV_API' ) && true === SESAMY_DEV_API ) ? 'https://assets.sesamy.dev' : 'https://assets.sesamy.com';
		return 'https://assets.sesamy.dev';
	}
}
