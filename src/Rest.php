<?php
/**
 * Rest module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

/**
 * Rest module.
 *
 * @package Sesamy2
 */
class Rest {
	/**
	 * Initialize the Assets module.
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

		$is_locked = get_post_meta( $post->ID, '_sesamy_locked', true );

		if ( $is_locked ) {
			$token         = 'eyJraWQiOiIwc3NQVnB1Y0wtMU9JRG9CYUQ4ZXkiLCJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJkZWZhdWx0Iiwic2NvcGUiOiJzdGF0czp2aWV3czpyZWFkIHN0YXRzOnJlcG9ydHM6bWFuYWdlIGRpc2NvdW50czpjaGVja291dDpyZWFkIGRpc2NvdW50czpjaGVja291dDp3cml0ZSBkaXNjb3VudHM6b25ldGltZTpyZWFkIGRpc2NvdW50czpvbmV0aW1lOndyaXRlIGV4dGVybmFsLW9yaWdpbnM6ZXh0ZXJuYWwtcHVyY2hhc2VzOnJlYWQgZXh0ZXJuYWwtb3JpZ2luczpleHRlcm5hbC1wdXJjaGFzZXM6d3JpdGUgd2FsbGV0OnVzZXI6cmVhZCB3YWxsZXQ6dXNlcjp3cml0ZSBwcm9maWxlOnVzZXItdmVuZG9yczp3cml0ZSBwcm9maWxlOnVzZXItdmVuZG9yczpyZWFkIHZhdWx0OmltcGVyc29uYXRlIHZhdWx0OmVudGl0bGVtZW50Om1hbmFnZSB2YXVsdDplbnRpdGxlbWVudC1zaGFyZTptYW5hZ2UgcGF5bWVudHM6Y29udHJhY3Q6YWRtaW4gcHJvZmlsZTp1c2VyOnJlYWQgZGlzY291bnRzOnN0YXRzOnJlYWQgZnVsZmlsbG1lbnQ6cmVhZCBmdWxmaWxsbWVudDp3cml0ZSBmdWxmaWxsbWVudDpzeW5jIHBvZGNhc3RzOnJlYWQgcG9kY2FzdHM6d3JpdGUgcGF5d2FsbDpyZWFkIHBheXdhbGw6d3JpdGUgcHJvZmlsZTp2ZW5kb3ItYXNzZXRzOndyaXRlIHByb2ZpbGU6dmVuZG9yczpyZWFkIHByb2ZpbGU6dmVuZG9yOm1hbmFnZS1zZWxmIHBheW1lbnRzOmJhbmtnaXJvOnVwbG9hZCIsInBlcm1pc3Npb25zIjpbInN0YXRzOnZpZXdzOnJlYWQiLCJzdGF0czpyZXBvcnRzOm1hbmFnZSIsImRpc2NvdW50czpjaGVja291dDpyZWFkIiwiZGlzY291bnRzOmNoZWNrb3V0OndyaXRlIiwiZGlzY291bnRzOm9uZXRpbWU6cmVhZCIsImRpc2NvdW50czpvbmV0aW1lOndyaXRlIiwiZXh0ZXJuYWwtb3JpZ2luczpleHRlcm5hbC1wdXJjaGFzZXM6cmVhZCIsImV4dGVybmFsLW9yaWdpbnM6ZXh0ZXJuYWwtcHVyY2hhc2VzOndyaXRlIiwid2FsbGV0OnVzZXI6cmVhZCIsIndhbGxldDp1c2VyOndyaXRlIiwicHJvZmlsZTp1c2VyLXZlbmRvcnM6d3JpdGUiLCJwcm9maWxlOnVzZXItdmVuZG9yczpyZWFkIiwidmF1bHQ6aW1wZXJzb25hdGUiLCJ2YXVsdDplbnRpdGxlbWVudDptYW5hZ2UiLCJ2YXVsdDplbnRpdGxlbWVudC1zaGFyZTptYW5hZ2UiLCJwYXltZW50czpjb250cmFjdDphZG1pbiIsInByb2ZpbGU6dXNlcjpyZWFkIiwiZGlzY291bnRzOnN0YXRzOnJlYWQiLCJmdWxmaWxsbWVudDpyZWFkIiwiZnVsZmlsbG1lbnQ6d3JpdGUiLCJmdWxmaWxsbWVudDpzeW5jIiwicG9kY2FzdHM6cmVhZCIsInBvZGNhc3RzOndyaXRlIiwicGF5d2FsbDpyZWFkIiwicGF5d2FsbDp3cml0ZSIsInByb2ZpbGU6dmVuZG9yLWFzc2V0czp3cml0ZSIsInByb2ZpbGU6dmVuZG9yczpyZWFkIiwicHJvZmlsZTp2ZW5kb3I6bWFuYWdlLXNlbGYiLCJwYXltZW50czpiYW5rZ2lybzp1cGxvYWQiXSwic3ViIjoiZ29vZ2xlLW9hdXRoMnwxMTgyMjU2MTg3NTA2NTE1ODY2OTkiLCJraWQiOiIwc3NQVnB1Y0wtMU9JRG9CYUQ4ZXkiLCJpc3MiOiJodHRwczovL3Rva2VuLnNlc2FteS5kZXYvIiwiYXpwIjoiYnJlYWtpdCIsInZlbmRvcl9pZCI6ImJyZWFraXQiLCJpYXQiOjE3NDI3NjAyNjYsImV4cCI6MTc0Mjg0NjY2Nn0.lEDyXPBc70RjXj_i6zDjiPcApvnIKZYTdfRGrx0na5T7iqQAISGDxedMwPMQMTlZQGxtlDtopuw_0FezyegGQyFxP-wmQzAAuh-dPonqyr8XEVOqHwVu6YljJnzNQNuB4temAqWZj3FhvsBx9xIhVYcsBzBwA2lvw7jiv8_gAD8LkLjQ5SZV9JSwtRn7u8XZY3eGIrCTNfX6bHHbMF5538ewY4vH3aWq0uAxWM8Wno2wWpN59nNbeOuAd93TjpCWSlhbsfk34V6PZ1YSzsQ81aoJOW-4P_ahIJFLsOelcSZ7lqEzbWO0XmwXMYdr-frGvinJntFx8Iw2HL5fdLemrxIXPmZ6sZdlOi56BlztReYzYCfaQSlbfV05YgsZcGnWgUIcq2PBuL8trIxeAR1aR2Aj-uuhm7YTYwrf-BsxyHucr53zdRwYCQLFGSORk8Vp4CFwtxJNOeBrTu9uB25jDUSozmzL8MSLFbiMx1DPxcwp0TSe98Q9mgAOjwa_6r7K1xBJhQMZJMdDxixNDiHT0ZWdLWwiyaVWdTRl4BH0uLoybVWcfVvYlNGmdlY0mpghH2vuUfn3yECEYaeosGWWJGG4Oyh9D505nIzEFEFq5aDRCJzH-kByt3kyKagvEbWWPGGgn2R1aGKSz8evqyr3LEkE3soodE-KDwv4WnoiPRQ';
			$jwks          = $this->get_sesamy_jwks();
			$jwks          = json_decode( $jwks, true );
			$decoded_token = JWT::decode( $token, JWK::parseKeySet( $jwks ) );

			// Check if the token has the required permission
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
	 * GET JWK Token by API.
	 *
	 * @return string
	 */
	public function get_sesamy_jwks() {
		$req = wp_remote_get( 'https://token.sesamy.dev/.well-known/jwks.json' );
		return wp_remote_retrieve_body( $req );
	}
}
