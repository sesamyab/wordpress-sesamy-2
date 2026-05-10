<?php
/**
 * Same-domain proxy for Sesamy auth and API traffic.
 *
 * Maps requests at `home_url('/sesamy/{api|auth}/...')` to the matching
 * upstream Sesamy host so reader cookies bind to the publisher's domain
 * (first-party). Stage 1 only proxies `api` and `auth`; static script and
 * asset bundles continue to load directly from `scripts.sesamy.{com,dev}`.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Proxy;

use function SesamyPlugin\Helpers\get_sesamy_environment;
use function SesamyPlugin\Helpers\get_sesamy_routing_mode;

/**
 * Proxy router.
 */
class Router {

	/**
	 * Upstream HTTP timeout in seconds.
	 */
	private const TIMEOUT = 15;

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
	 * Register hooks and rewrite rules.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', [ $this, 'register_rewrites' ] );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'maybe_handle_proxy' ], 0 );
		add_action( 'template_redirect', [ $this, 'maybe_redirect_unprefixed_auth' ], 0 );
	}

	/**
	 * Register the `/sesamy/{api|auth}/*` rewrite rule.
	 *
	 * Both API and BFF auth live under the single `/sesamy/` namespace so
	 * we don't reserve any top-level publisher paths. The bundle's
	 * cookie-auth plugin emits `${auth.baseUrl}/auth/...`, so we set
	 * `auth.baseUrl = '/sesamy'` in [Core/Assets.php] to keep its URLs
	 * inside this prefix (requires `@sesamy/sesamy-js` >= 1.120.0).
	 *
	 * @return void
	 */
	public function register_rewrites() {
		add_rewrite_tag( '%sesamy_proxy_type%', '([^&]+)' );
		add_rewrite_tag( '%sesamy_proxy_path%', '(.+)' );
		add_rewrite_rule(
			'^sesamy/(api|auth)/(.+)$',
			'index.php?sesamy_proxy_type=$matches[1]&sesamy_proxy_path=$matches[2]',
			'top'
		);
	}

	/**
	 * Whitelist the proxy query vars.
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string>
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'sesamy_proxy_type';
		$vars[] = 'sesamy_proxy_path';
		return $vars;
	}

	/**
	 * Dispatch a proxy request, if the rewrite matched.
	 *
	 * @return void
	 */
	public function maybe_handle_proxy() {
		$type = get_query_var( 'sesamy_proxy_type' );
		$path = get_query_var( 'sesamy_proxy_path' );

		if ( ! is_string( $type ) || '' === $type || ! is_string( $path ) || '' === $path ) {
			return;
		}
		if ( ! in_array( $type, [ 'api', 'auth' ], true ) ) {
			return;
		}
		if ( 'wordpress_proxy' !== get_sesamy_routing_mode() ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		$this->forward( $type, $path );
	}

	/**
	 * Forward the current HTTP request to the upstream Sesamy host and
	 * stream the response back to the reader.
	 *
	 * @param string $type 'api' or 'auth'.
	 * @param string $path Path under the upstream host.
	 * @return never
	 */
	private function forward( $type, $path ): never {
		$upstream = $this->upstream_url( $type, $path );

		// Forward existing query string (rewrite rules consume the path; $_GET
		// still holds anything appended by the caller).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- transparent proxy; auth handled upstream.
		$query = $_GET;
		unset( $query['sesamy_proxy_type'], $query['sesamy_proxy_path'] );
		if ( ! empty( $query ) ) {
			$upstream .= ( false === strpos( $upstream, '?' ) ? '?' : '&' )
				. http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		$method  = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';
		$headers = $this->collect_request_headers();

		$body = '';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			$raw  = file_get_contents( 'php://input' );
			$body = false === $raw ? '' : $raw;
		}

		$response = wp_remote_request(
			$upstream,
			[
				'method'      => $method,
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'sslverify'   => true,
				'decompress'  => false,
				'headers'     => $headers,
				'body'        => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			status_header( 502 );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Cache-Control: no-store' );
			echo wp_json_encode(
				[
					'error'             => 'sesamy_proxy_upstream_failed',
					'error_description' => $response->get_error_message(),
				]
			);
			exit;
		}

		$this->emit_response( $response );
		exit;
	}

	/**
	 * Catch bare `/auth/*` requests and 302 them to `/sesamy/auth/*`.
	 *
	 * The upstream BFF on api2 builds its callback `redirect_uri` from the
	 * publisher's `Host` header alone and doesn't know we proxy under
	 * `/sesamy/`, so post-login the browser lands at e.g.
	 * `/auth/{vendor}/callback` — outside the proxy namespace. This hook
	 * normalises any such hit into the prefixed equivalent so the
	 * subsequent proxy round-trip can complete.
	 *
	 * Only active in `wordpress_proxy` mode so we don't shadow
	 * publisher-owned `/auth/*` URLs when proxying is off.
	 *
	 * @return void
	 */
	public function maybe_redirect_unprefixed_auth() {
		if ( 'wordpress_proxy' !== get_sesamy_routing_mode() ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $uri ) {
			return;
		}
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( 1 !== preg_match( '#^/auth/(.+)$#', $path, $m ) ) {
			return;
		}
		$query  = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		$target = home_url( '/sesamy/auth/' . $m[1] . ( '' !== $query ? '?' . $query : '' ) );
		wp_safe_redirect( $target, 302 );
		exit;
	}

	/**
	 * Compute the upstream URL for a proxied request.
	 *
	 * Both BFF auth and runtime API live on `api2.sesamy.{com,dev}`; auth
	 * additionally sits under an `/auth/` segment (the Token Handler set
	 * by `/auth/{vendor}/login`, `/auth/userinfo`, `/auth/logout`). This
	 * is distinct from `auth2.sesamy.{com,dev}`, which hosts the
	 * OIDC/AuthHero endpoints (`/connect/start`, `/oauth/token`) used by
	 * the admin connect flow with `direct=true`.
	 *
	 * @param string $type 'api' or 'auth'.
	 * @param string $path Path captured after the `/sesamy/{api|auth}/` prefix.
	 * @return string
	 */
	private function upstream_url( $type, $path ) {
		$tld    = 'dev' === get_sesamy_environment() ? 'dev' : 'com';
		$host   = "https://api2.sesamy.{$tld}";
		$prefix = 'auth' === $type ? '/auth/' : '/';
		return $host . $prefix . ltrim( (string) $path, '/' );
	}

	/**
	 * Build the outgoing header array from $_SERVER.
	 *
	 * @return array<string, string>
	 */
	private function collect_request_headers() {
		$pass_through = [
			'HTTP_AUTHORIZATION'   => 'Authorization',
			'HTTP_ACCEPT'          => 'Accept',
			'HTTP_ACCEPT_LANGUAGE' => 'Accept-Language',
			'HTTP_COOKIE'          => 'Cookie',
			'HTTP_USER_AGENT'      => 'User-Agent',
			'HTTP_REFERER'         => 'Referer',
		];

		$out = [];
		foreach ( $pass_through as $server_key => $header ) {
			if ( ! empty( $_SERVER[ $server_key ] ) ) {
				$out[ $header ] = sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) );
			}
		}

		// Content-Type and Content-Length live outside the HTTP_* prefix.
		if ( ! empty( $_SERVER['CONTENT_TYPE'] ) ) {
			$out['Content-Type'] = sanitize_text_field( wp_unslash( $_SERVER['CONTENT_TYPE'] ) );
		}

		// Append client IP to X-Forwarded-For so upstream sees the real reader.
		$existing = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
			: '';
		$client   = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';
		if ( '' !== $client ) {
			$out['X-Forwarded-For'] = '' === $existing ? $client : $existing . ', ' . $client;
		}
		if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
			$out['X-Forwarded-Host'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		}
		$out['X-Forwarded-Proto'] = is_ssl() ? 'https' : 'http';

		// Tell upstream about the `/sesamy` proxy prefix so it can build
		// callback URLs (and any other self-referential redirects) with
		// the prefix included. Standard reverse-proxy convention used by
		// nginx, traefik, etc. — upstream that honours it skips the
		// `/auth/*` → `/sesamy/auth/*` redirect we install separately.
		$routing                   = get_option( 'sesamy_routing', [] );
		$proxy_prefix              = is_array( $routing ) && ! empty( $routing['proxy_base_path'] ) ? (string) $routing['proxy_base_path'] : '/sesamy';
		$out['X-Forwarded-Prefix'] = '/' . trim( $proxy_prefix, '/' );

		return $out;
	}

	/**
	 * Stream a `wp_remote_request` response back to the client, rewriting
	 * Set-Cookie domains to the publisher host and forcing no-store.
	 *
	 * @param array<string, mixed> $response Upstream response (callers must ensure it's not a WP_Error).
	 * @return void
	 */
	private function emit_response( $response ) {
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status <= 0 ) {
			$status = 502;
		}
		status_header( $status );

		$headers = wp_remote_retrieve_headers( $response );
		$skip    = [
			'transfer-encoding' => true,
			'connection'        => true,
			'keep-alive'        => true,
			'content-length'    => true, // recomputed by PHP/SAPI.
			'content-encoding'  => true, // body is already decoded by HTTP API when decompress=true; we passed false, but headers may still mismatch on chunked transforms.
		];

		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$pairs = $headers->getAll();
		} elseif ( is_array( $headers ) ) {
			$pairs = $headers;
		} else {
			$pairs = [];
		}

		$has_cache_control = false;
		foreach ( $pairs as $name => $value ) {
			$lname = strtolower( (string) $name );
			if ( isset( $skip[ $lname ] ) ) {
				continue;
			}
			if ( 'cache-control' === $lname ) {
				$has_cache_control = true;
			}
			$values = is_array( $value ) ? $value : [ $value ];
			foreach ( $values as $v ) {
				if ( 'set-cookie' === $lname ) {
					$v = $this->rewrite_cookie_domain( (string) $v );
				}
				header( $name . ': ' . $v, false );
			}
		}

		// Fail safe: if upstream didn't declare a cache policy, default to
		// no-store so an edge cache doesn't grab an auth/api response.
		if ( ! $has_cache_control ) {
			header( 'Cache-Control: no-store' );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- proxied response body is opaque.
		echo wp_remote_retrieve_body( $response );
	}

	/**
	 * Strip the `Domain=` attribute from a Set-Cookie value so the cookie binds
	 * to the publisher's host (first-party) instead of the upstream Sesamy host.
	 *
	 * @param string $cookie Raw Set-Cookie header value.
	 * @return string
	 */
	private function rewrite_cookie_domain( $cookie ) {
		// Remove `Domain=...;` (case-insensitive) and tidy up doubled separators.
		$cookie = preg_replace( '/;\s*Domain\s*=[^;]*/i', '', $cookie );
		return is_string( $cookie ) ? $cookie : '';
	}
}
