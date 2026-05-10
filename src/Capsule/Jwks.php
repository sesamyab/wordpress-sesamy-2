<?php
/**
 * Publisher JWKS endpoint.
 *
 * Serves the publisher's signing-key JWKS document at
 * `home_url('/.well-known/dca-publishers.json')` so JWKS-configured issuers
 * can verify the resourceJWT signature on Capsule manifests.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Capsule;

/**
 * Publisher JWKS endpoint.
 */
class Jwks {

	/**
	 * Cache lifetime for the JWKS response (1 hour).
	 */
	private const CACHE_MAX_AGE = 3600;

	/**
	 * Initialize the module.
	 */
	public static function init(): self {
		$instance = new self();
		$instance->register();
		return $instance;
	}

	/**
	 * Register hooks and rewrites.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve' ], 0 );
	}

	/**
	 * Register `/.well-known/dca-publishers.json`.
	 */
	public function register_rewrite(): void {
		add_rewrite_tag( '%sesamy_jwks%', '1' );
		add_rewrite_rule(
			'^\.well-known/dca-publishers\.json$',
			'index.php?sesamy_jwks=1',
			'top'
		);
	}

	/**
	 * Add `sesamy_jwks` to the list of recognised query vars.
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string>
	 */
	public function register_query_var( $vars ) {
		$vars[] = 'sesamy_jwks';
		return $vars;
	}

	/**
	 * Emit the JWKS document if the rewrite matched.
	 *
	 * @return void
	 */
	public function maybe_serve() {
		if ( ! get_query_var( 'sesamy_jwks' ) ) {
			return;
		}

		try {
			$document = Service::jwks_document();
		} catch ( \Throwable $e ) {
			status_header( 500 );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode(
				[
					'error'             => 'sesamy_jwks_unavailable',
					'error_description' => $e->getMessage(),
				]
			);
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: application/jwk-set+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=' . self::CACHE_MAX_AGE );
		echo wp_json_encode( $document );
		exit;
	}
}
