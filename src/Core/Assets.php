<?php
/**
 * Assets module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Core;

use function SesamyPlugin\Helpers\get_enabled_post_types;
use function SesamyPlugin\Helpers\get_sesamy_environment;
use function SesamyPlugin\Helpers\get_sesamy_routing_mode;
use function SesamyPlugin\Helpers\get_sesamy_setting;
use function SesamyPlugin\Helpers\get_sesamy_vendor_id;
use function SesamyPlugin\Helpers\is_config_valid;
use function SesamyPlugin\Releases\is_update_available;

/**
 * Assets module.
 *
 * @package Sesamy2
 */
class Assets {
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
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'update_badge' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'block_editor_scripts' ] );

		// Render the inline bootstrap directly in `<head>`. Hooked at
		// register-time (not from `wp_enqueue_scripts`) so the priority-1
		// bucket isn't already being iterated when we attach.
		add_action( 'wp_head', [ $this, 'render_sesamy_bootstrap' ], 1 );
	}

	/**
	 * Enqueue scripts for admin.
	 *
	 * @return void
	 */
	public function admin_scripts() {
		// TODO: check getting asset version
		wp_enqueue_script(
			'sesamy_plugin_admin',
			SESAMY_PLUGIN_URL . 'dist/js/admin.js',
			[],
			SESAMY_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Enqueue styles for admin.
	 *
	 * @return void
	 */
	public function admin_styles() {
		wp_enqueue_style(
			'sesamy_plugin_admin',
			SESAMY_PLUGIN_URL . 'dist/css/admin.css',
			[],
			SESAMY_PLUGIN_VERSION
		);
	}

	/**
	 * Update the visibility of the Sesamy update badge in the admin menu.
	 *
	 * @return void
	 */
	public function update_badge() {
		if ( is_update_available() ) {
			$css = '#adminmenu .sesamy-update-badge { display: inline-block; }';
		} else {
			$css = '#adminmenu .sesamy-update-badge { display: none; }';
		}

		wp_add_inline_style( 'admin-menu', $css );
	}

	/**
	 * Render the bundle config and bootstrap loader inline in `<head>`.
	 *
	 * The bundle config (`<script id="sesamy-js">`) is read by the sesamy-js
	 * core on init. The loader (`window.__sesamyBootOptions` + the IIFE
	 * built from `@sesamy/sesamy-js/bootstrap`) kicks off the script chain
	 * and the userinfo prefetch as soon as the parser hits it — no extra
	 * round-trip to fetch `sesamy.js` itself.
	 *
	 * @return void
	 */
	public function render_sesamy_bootstrap() {
		if ( ! is_config_valid() ) {
			return;
		}
		$vendor_id = get_sesamy_vendor_id();
		if ( ! $vendor_id ) {
			return;
		}

		// Single config element. The bootstrap IIFE auto-runs on load and
		// picks bootstrap option keys (clientId, environment, apiBaseUrl,
		// skipAuthPrefetch, …) out of this element directly; the sesamy-js
		// core reads its own keys (clientId, auth, content, …) from the same
		// element. Unknown keys are ignored on both sides.
		//
		// `version` pins the core (and plugins) loaded from the scripts host.
		// The `stable` channel can lag behind the bootstrap we ship, and
		// older cores predate the `useHttpCookies` / `api.endpoint` config
		// keys we emit below — pinning keeps bootstrap and core in lockstep.
		// `content` selectors override the bundle's hardcoded `<sesamy-article>`
		// defaults so we can render plain `<article class="sesamy-article">` and
		// still feed sesamy-js the same article metadata (id, item-src, paywall).
		$article_selector = 'article.sesamy-article';
		$env              = get_sesamy_environment();
		$config           = [
			'clientId'          => $vendor_id,
			'environment'       => $env,
			// `version` pins core + auth0-plugin + capsule-plugin to the
			// build that matches the inlined bootstrap. `sesamy-components`
			// releases on its own track, so it gets a separate channel —
			// otherwise the script host 404s on a non-existent components
			// build matching the core semver.
			'version'           => SESAMY_JS_VERSION,
			'componentsVersion' => 'stable',
			'content'           => [
				[
					'type'      => 'article',
					'selectors' => [
						'article'     => [ 'selector' => $article_selector ],
						'id'          => [
							'selector'  => $article_selector,
							'attribute' => 'publisher-content-id',
						],
						'url'         => [
							'selector'  => $article_selector,
							'attribute' => 'item-src',
						],
						'price'       => [
							'selector'  => $article_selector,
							'attribute' => 'price',
						],
						'currency'    => [
							'selector'  => $article_selector,
							'attribute' => 'currency',
						],
						'accessLevel' => [
							'selector'  => $article_selector,
							'attribute' => 'access-level',
						],
						'pass'        => [
							'selector'  => $article_selector,
							'attribute' => 'pass',
						],
						'paywallUrl'  => [
							'selector'  => $article_selector,
							'attribute' => 'paywall-url',
						],
					],
				],
			],
			// Opt the page into capsule auto-processing. Without `enabled: true`
			// the capsule plugin loads but never initialises a DcaClient, so
			// it never calls unlock or renderToPage even when a manifest is
			// on the page. `autoProcess` defaults to true.
			'capsule'           => [
				'enabled' => true,
			],
		];

		// In proxy mode, point the bundle's network surfaces at the WP proxy:
		// - `apiBaseUrl` — bootstrap userinfo prefetch (`${apiBaseUrl}/auth/userinfo`).
		// - `api.endpoint` — runtime API calls (entitlements, paywalls,
		// etc.) live under `/sesamy/api/*`.
		// - `auth.useHttpCookies` — tells the bootstrap to skip auth0-plugin
		// and the core to install the cookie-based BFF plugin instead,
		// so no SPA flow runs and no Bearer tokens land in the browser.
		// - `auth.baseUrl` — namespace the BFF plugin's `/auth/*` paths
		// under our proxy prefix (requires sesamy-js >= 1.120.0).
		// Without this the BFF would reserve `/auth/*` at the publisher
		// domain root.
		// In direct mode, skip the userinfo prefetch (same-origin 404,
		// cross-origin blocked) and let the bundle do its default thing.
		// The bundle's `api`/`auth`/`analytics` subconfigs each carry their
		// own `environment` flag and don't inherit the top-level one. Without
		// these the core falls back to `prod` and hits `api.sesamy.com` /
		// `auth.sesamy.com` even when we registered on `.dev`. Set them
		// explicitly in both routing modes — `endpoint`/`baseUrl` overrides
		// take precedence anyway in proxy mode, but the analytics config
		// only honours `environment`.
		$config['api']       = [ 'environment' => $env ];
		$config['auth']      = [ 'environment' => $env ];
		$config['analytics'] = [ 'environment' => $env ];

		if ( 'wordpress_proxy' === get_sesamy_routing_mode() ) {
			$routing                   = get_option( 'sesamy_routing', [] );
			$proxy_base                = rtrim(
				home_url( is_array( $routing ) && ! empty( $routing['proxy_base_path'] ) ? (string) $routing['proxy_base_path'] : '/sesamy' ),
				'/'
			);
			$config['apiBaseUrl']      = $proxy_base;
			$config['api']['endpoint'] = $proxy_base . '/api';
			$config['auth']           += [
				'useHttpCookies' => true,
				'baseUrl'        => $proxy_base,
			];
		} else {
			$config['skipAuthPrefetch'] = true;
		}

		// Debug breadcrumb: emit the resolved environment + connection inputs
		// as an HTML comment so we can rule out misconfiguration without
		// guessing. View page source on the frontend to read.
		$conn = function_exists( '\\SesamyPlugin\\Helpers\\get_sesamy_connection' )
			? \SesamyPlugin\Helpers\get_sesamy_connection()
			: null;
		printf(
			"<!-- sesamy: env=%s | connection.environment=%s | registration_client_uri=%s | development_mode=%s -->\n",
			esc_html( $env ),
			esc_html( is_array( $conn ) && ! empty( $conn['environment'] ) ? (string) $conn['environment'] : '(unset)' ),
			esc_html( is_array( $conn ) && ! empty( $conn['registration_client_uri'] ) ? (string) $conn['registration_client_uri'] : '(unset)' ),
			esc_html( get_sesamy_setting( 'development_mode' ) ? '1' : '0' )
		);

		printf(
			'<script type="application/json" id="sesamy-js">%s</script>',
			wp_json_encode( $config )
		);

		$loader_path = SESAMY_PLUGIN_DIST_PATH . 'js/sesamy.js';
		if ( ! is_readable( $loader_path ) ) {
			return;
		}
		$loader = file_get_contents( $loader_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $loader ) {
			return;
		}

		printf(
			'<script>%s</script>',
			$loader // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built bundle from our own webpack output, not user data.
		);
	}

	/**
	 * Enqueue scripts for the block editor.
	 *
	 * @return void
	 */
	public function block_editor_scripts() {
		global $post;
		$enabled_post_types = get_enabled_post_types();
		if ( isset( $post ) && is_config_valid() && in_array( $post->post_type, $enabled_post_types, true ) ) {
			wp_enqueue_script(
				'sesamy_plugin_post_settings',
				SESAMY_PLUGIN_URL . 'dist/js/post-settings.js',
				[],
				SESAMY_PLUGIN_VERSION,
				true
			);
		}
	}
}
