<?php
/**
 * Publisher JWKS endpoint.
 *
 * Serves the publisher's signing-key JWKS document at
 * `home_url('/.well-known/dca-publishers.json')` so JWKS-configured issuers
 * can verify the resourceJWT signature on Capsule manifests.
 *
 * The plugin's own registration pins a PEM instead (see `Capsule\Registration`),
 * so nothing in the default unlock path depends on this endpoint. It stays
 * because `jwks_uri` remains a supported registration mode — for consumers
 * that ask for it and for `register_domain( $domain, 'jwks_uri' )`.
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
	 * How long a cache may keep serving a stale copy while it refreshes in
	 * the background (1 day). The document only changes when the publisher
	 * signing key does, which today is never.
	 */
	private const STALE_WHILE_REVALIDATE = 86400;

	/**
	 * How long a cache may keep serving a stale copy when the origin is
	 * failing (1 week). This is the one that matters: it's what keeps a
	 * consumer verifying through an origin outage, WAF block, or rate limit.
	 */
	private const STALE_IF_ERROR = 604800;

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

		// `parse_request` fires the moment the URL has been resolved to query
		// vars — before the main query runs and long before `template_redirect`,
		// so the theme, the session, and every other plugin's front-end output
		// never load for this request. The document is the same bytes for every
		// caller, so there is nothing later in the request worth waiting for.
		add_action( 'parse_request', [ $this, 'maybe_serve' ] );
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
	 * Reads `$wp->query_vars` rather than `get_query_var()` because the main
	 * query hasn't been built yet at `parse_request` time — that's the point.
	 *
	 * @param \WP|null $wp Current WordPress environment instance, post-parse.
	 * @return void
	 */
	public function maybe_serve( $wp = null ) {
		// Defensive default: core always passes the WP instance here, but the
		// action is public and nothing stops someone firing it bare.
		$query_vars = $wp instanceof \WP ? $wp->query_vars : [];
		if ( empty( $query_vars['sesamy_jwks'] ) ) {
			return;
		}

		try {
			$document = Service::jwks_document();
		} catch ( \Throwable $e ) {
			error_log( '[sesamy] JWKS endpoint failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			status_header( 500 );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode(
				[
					'error' => 'sesamy_jwks_unavailable',
				]
			);
			exit;
		}

		$body = (string) wp_json_encode( $document );
		$etag = '"' . md5( $body ) . '"';

		$this->send_cache_headers( $etag );

		if ( $this->etag_matches( $etag ) ) {
			status_header( 304 );
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: application/jwk-set+json; charset=utf-8' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON produced by wp_json_encode.
		exit;
	}

	/**
	 * Send the caching headers, clearing anything WordPress already staged
	 * that would defeat them.
	 *
	 * A logged-in request (or any plugin that called `nocache_headers()`
	 * early) leaves `Pragma: no-cache`, `Expires: -1`, and a session cookie on
	 * the response. All three suppress caching of a document that is byte-for-
	 * byte identical for every caller, and a `Set-Cookie` in particular makes
	 * most shared caches refuse to store the response at all.
	 *
	 * @param string $etag Quoted entity tag for the current document.
	 * @return void
	 */
	private function send_cache_headers( $etag ) {
		header_remove( 'Pragma' );
		header_remove( 'Expires' );
		header_remove( 'Set-Cookie' );

		header(
			sprintf(
				'Cache-Control: public, max-age=%d, stale-while-revalidate=%d, stale-if-error=%d',
				self::CACHE_MAX_AGE,
				self::STALE_WHILE_REVALIDATE,
				self::STALE_IF_ERROR
			)
		);
		header( 'ETag: ' . $etag );

		// The response varies by transfer encoding and nothing else — not by
		// cookie, auth, or language. Replace whatever Vary the stack built up.
		header( 'Vary: Accept-Encoding' );
	}

	/**
	 * Whether the request's `If-None-Match` covers the current document.
	 *
	 * Weak comparison per RFC 9110 §8.8.3.2: `W/"x"` and `"x"` both match
	 * `"x"`, which is what we want for a cache validator.
	 *
	 * @param string $etag Quoted entity tag for the current document.
	 * @return bool
	 */
	private function etag_matches( $etag ) {
		$header = isset( $_SERVER['HTTP_IF_NONE_MATCH'] )
			? trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) )
			: '';
		if ( '' === $header ) {
			return false;
		}
		if ( '*' === $header ) {
			return true;
		}

		foreach ( explode( ',', $header ) as $candidate ) {
			$candidate = trim( $candidate );
			if ( str_starts_with( $candidate, 'W/' ) ) {
				$candidate = substr( $candidate, 2 );
			}
			if ( $candidate === $etag ) {
				return true;
			}
		}
		return false;
	}
}
