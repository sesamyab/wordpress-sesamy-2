<?php
/**
 * Capsule (DCA) publisher service.
 *
 * Wraps the `sesamy/capsule-publisher` package: lazy-generates and persists the
 * publisher's ES256 signing keypair plus rotation secret, builds the Publisher
 * on demand, and renders one ContentItem per post. The rotation secret is
 * publisher-private — per the OSS DCA spec the issuer never sees it, just
 * unwraps each wrapKey blob with its own private key.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Capsule;

use Sesamy\Capsule\Publisher\ContentItem;
use Sesamy\Capsule\Publisher\IssuerConfig;
use Sesamy\Capsule\Publisher\Publisher;
use Sesamy\Capsule\Publisher\PublisherConfig;
use Sesamy\Capsule\Publisher\RenderOptions;
use Sesamy\Capsule\Publisher\RenderResult;
use Sesamy\Capsule\Publisher\Jwks\IssuerJwksResolver;
use Sesamy\Capsule\Publisher\Jwks\PublisherJwks;

use function SesamyPlugin\Helpers\get_sesamy_setting;
use function SesamyPlugin\Helpers\get_sesamy_vendor_id;
use function SesamyPlugin\Helpers\publisher_domain;
use function SesamyPlugin\Helpers\sesamy_url;

/**
 * Capsule service.
 */
class Service {

	/**
	 * Option key holding the persisted publisher signing keypair + rotation secret.
	 */
	public const OPTION = 'sesamy_capsule_keys';

	/**
	 * Cached Publisher instance for the current request.
	 *
	 * @var Publisher|null
	 */
	private static ?Publisher $publisher = null;

	/**
	 * TEMP debug — diagnostic info from the last render, surfaced as an HTML
	 * comment by `process_content()`. Diffing this against the JS publisher's
	 * equivalents tells us which input differs (domain, signing key, issuer
	 * JWKS kid, scope bytes, derived wrapKey W). Remove once decrypt works.
	 *
	 * @var string
	 */
	public static string $debug_marker = '';

	/**
	 * Render encrypted content for one post. Returns the publisher's manifest
	 * `<script>` plus the resource id used for embedding. Returns `null` if
	 * the connected configuration is incomplete or rendering fails.
	 *
	 * @param \WP_Post $post    The post being rendered.
	 * @param string   $content Plaintext post content (already filtered).
	 * @return array{manifestScript: string, resourceId: string, contentName: string}|null
	 */
	public static function render( $post, $content ) {
		$pass_sku = (string) ( get_sesamy_setting( 'default_pass' ) ?? '' );
		if ( '' === $pass_sku ) {
			error_log( '[sesamy] Capsule render skipped: default_pass is not configured.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return null;
		}

		$vendor_id = (string) get_sesamy_vendor_id();
		if ( '' === $vendor_id ) {
			error_log( '[sesamy] Capsule render skipped: vendor id is not configured.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return null;
		}

		try {
			$publisher = self::get_publisher();
		} catch ( \Throwable $e ) {
			error_log( '[sesamy] Capsule publisher unavailable: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return null;
		}

		$resource_id = (string) $post->ID;

		// TEMP debug — dump everything that would differ between us and the
		// JS publisher hitting the same backend. Compare line-by-line with
		// what nomad-blackbook (or any working JS publisher) emits.
		self::$debug_marker = self::build_debug_marker( $pass_sku, $vendor_id, $resource_id );

		try {
			/**
			 * Sealed manifest returned by the publisher SDK.
			 *
			 * @var RenderResult $result
			 */
			$result = $publisher->render(
				new RenderOptions(
					resourceId: $resource_id,
					contentItems: [
						new ContentItem(
							contentName: $pass_sku,
							content: $content,
						),
					],
					issuers: [ self::issuer_config( $pass_sku, $vendor_id ) ],
					resourceData: [
						'title' => get_the_title( $post ),
						'url'   => (string) get_permalink( $post ),
					],
				)
			);
		} catch ( \Throwable $e ) {
			error_log( '[sesamy] Capsule render failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return null;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- third-party RenderResult property.
		$manifest = $result->manifestScript;
		return [
			'manifestScript' => $manifest,
			'resourceId'     => $resource_id,
			'contentName'    => $pass_sku,
		];
	}

	/**
	 * Build the publisher JWKS document so issuers can verify the signed
	 * resourceJWT against `${publisher_origin}/.well-known/dca-publishers.json`.
	 *
	 * @return array{keys: list<array<string,mixed>>}
	 */
	public static function jwks_document() {
		$keys = self::ensure_keys();
		return PublisherJwks::buildPublisherJwksDocument(
			[
				[
					'publicKeyPem' => $keys['signing_public_key_pem'],
					'kid'          => $keys['signing_key_id'],
				],
			]
		);
	}

	/**
	 * Get the publisher signing key bundle, generating it if absent. Lets
	 * callers outside this class (e.g. `Capsule\Registration`, when pinning
	 * the PEM with the api-proxy on first connect) trigger key generation
	 * eagerly without having to render a capsule-locked post first.
	 *
	 * @return array{
	 *   signing_private_key_pem: string,
	 *   signing_public_key_pem: string,
	 *   signing_key_id: string,
	 *   rotation_secret: string,
	 *   created_at: int
	 * }
	 */
	public static function publisher_keys() {
		return self::ensure_keys();
	}

	/**
	 * Build (or return cached) Publisher instance.
	 */
	private static function get_publisher(): Publisher {
		if ( self::$publisher instanceof Publisher ) {
			return self::$publisher;
		}

		$keys = self::ensure_keys();

		// Not passing PublisherConfig::vendorId — its only effect is to enable
		// the package's `sesamyUnlockUrl()` / `sesamyJwksUri()` helpers, and
		// we build both URLs through `sesamy_url()` so dev/prod switching
		// works (the package helpers hardcode `.com`).
		self::$publisher = new Publisher(
			new PublisherConfig(
				domain: publisher_domain(),
				signingKeyPem: $keys['signing_private_key_pem'],
				rotationSecret: $keys['rotation_secret'],
				signingKeyId: $keys['signing_key_id'],
				issuerJwksResolver: new IssuerJwksResolver(),
			)
		);

		return self::$publisher;
	}

	/**
	 * Issuer config — Sesamy is the only issuer for now. Both endpoints live
	 * under `/capsule/vendors/{vendor_id}/` on `api2.sesamy.{com,dev}`.
	 *
	 * Both URLs use our own `sesamy_url()` so the `development_mode` setting
	 * routes to `api2.sesamy.dev`; the package's `sesamyUnlockUrl()` /
	 * `sesamyJwksUri()` helpers hardcode `.com` and would regress dev users.
	 *
	 * `unlockUrl` is consumed by the capsule client in the browser, so in
	 * proxy mode it goes through the WP proxy for first-party cookie
	 * semantics. `jwksUri` is fetched server-to-server, so we always go
	 * direct (no point bouncing through the WP proxy).
	 *
	 * Scope mode (vs name-granular) is required because Sesamy's unlock
	 * endpoint expects `wrapKeys` on each issuer key entry — the publisher
	 * only emits those when the issuer is configured with `scopes`. Since
	 * `ContentItem.effectiveScope()` defaults to `contentName`, the pass SKU
	 * we set as both ends up identifying the scope cleanly here.
	 *
	 * @param string $scope     Pass SKU used as the scope identifier.
	 * @param string $vendor_id Sesamy vendor (tenant) id.
	 */
	private static function issuer_config( string $scope, string $vendor_id ): IssuerConfig {
		$base = 'capsule/vendors/' . rawurlencode( $vendor_id );
		return new IssuerConfig(
			issuerName: 'sesamy',
			unlockUrl: sesamy_url( $base . '/unlock', 'api' ),
			jwksUri: sesamy_url( $base . '/.well-known/jwks.json', 'api', true ),
			scopes: [ $scope ],
		);
	}

	/**
	 * Get (or generate-on-first-use) the persisted publisher keypair and
	 * rotation secret. Stored as a non-autoloaded `wp_options` row. The
	 * rotation secret is HKDF input only and never leaves the publisher.
	 *
	 * @return array{
	 *   signing_private_key_pem: string,
	 *   signing_public_key_pem: string,
	 *   signing_key_id: string,
	 *   rotation_secret: string,
	 *   created_at: int
	 * }
	 */
	private static function ensure_keys(): array {
		$stored = get_option( self::OPTION );
		if ( is_array( $stored ) && self::keys_complete( $stored ) ) {
			/**
			 * Persisted publisher keypair bundle.
			 *
			 * @var array{signing_private_key_pem: string, signing_public_key_pem: string, signing_key_id: string, rotation_secret: string, created_at: int} $stored
			 */
			return $stored;
		}

		$keypair = self::generate_es256_keypair();
		$bundle  = [
			'signing_private_key_pem' => $keypair['private'],
			'signing_public_key_pem'  => $keypair['public'],
			'signing_key_id'          => gmdate( 'Y-m' ),
			'rotation_secret'         => self::random_base64url( 32 ),
			'created_at'              => time(),
		];

		update_option( self::OPTION, $bundle, false );
		return $bundle;
	}

	/**
	 * Whether a stored key bundle has every field the publisher needs.
	 *
	 * @param array<string,mixed> $stored Bundle pulled from the `wp_options` row.
	 */
	private static function keys_complete( array $stored ): bool {
		return ! empty( $stored['signing_private_key_pem'] )
			&& ! empty( $stored['signing_public_key_pem'] )
			&& ! empty( $stored['signing_key_id'] )
			&& ! empty( $stored['rotation_secret'] );
	}

	/**
	 * Generate a fresh ES256 (P-256) keypair via OpenSSL.
	 *
	 * @return array{private: string, public: string}
	 * @throws \RuntimeException When OpenSSL fails to generate or export the keypair.
	 */
	private static function generate_es256_keypair(): array {
		$res = openssl_pkey_new(
			[
				'private_key_type' => OPENSSL_KEYTYPE_EC,
				'curve_name'       => 'prime256v1',
			]
		);
		if ( false === $res ) {
			throw new \RuntimeException( esc_html( 'Failed to generate ES256 keypair: ' . (string) openssl_error_string() ) );
		}
		$private = '';
		if ( ! openssl_pkey_export( $res, $private ) ) {
			throw new \RuntimeException( 'Failed to export ES256 private key' );
		}
		$details = openssl_pkey_get_details( $res );
		if ( false === $details || empty( $details['key'] ) ) {
			throw new \RuntimeException( 'Failed to export ES256 public key' );
		}
		return [
			'private' => $private,
			'public'  => (string) $details['key'],
		];
	}

	/**
	 * Generate a base64url-encoded random string.
	 *
	 * @param positive-int $bytes Number of random bytes to generate.
	 */
	private static function random_base64url( int $bytes ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- transport encoding for random bytes, not obfuscation.
		return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' );
	}

	/**
	 * TEMP debug — build a multi-line `<!-- … -->` comment dumping every
	 * input that goes into the wrap so we can diff against the JS publisher.
	 * Emits cryptographic fingerprints, so it's gated behind WP_DEBUG (or
	 * the SESAMY_DEBUG override) and returns '' in production.
	 *
	 * TODO: remove once the publisher/JS-publisher parity work is signed off.
	 *
	 * Best-effort; never throws to the caller.
	 *
	 * @param string $scope       Pass SKU used as the capsule scope.
	 * @param string $vendor_id   Sesamy vendor (tenant) id.
	 * @param string $resource_id WP post id rendered as a string.
	 */
	private static function build_debug_marker( string $scope, string $vendor_id, string $resource_id ): string {
		$debug_enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
			|| ( defined( 'SESAMY_DEBUG' ) && SESAMY_DEBUG );
		if ( ! $debug_enabled ) {
			return '';
		}

		try {
			$keys     = self::ensure_keys();
			$secret   = self::base64url_decode( $keys['rotation_secret'] );
			$rotation = \Sesamy\Capsule\Publisher\Dca\Rotation::getCurrentRotationVersions( 1 );

			$lines   = [];
			$lines[] = 'sesamy-debug v2';
			$lines[] = 'domain (iss):    ' . publisher_domain();
			$lines[] = 'resourceId:      ' . $resource_id;
			$lines[] = 'vendor:          ' . $vendor_id;
			$lines[] = 'scope:           ' . $scope;
			$lines[] = 'scope-bytes-hex: ' . bin2hex( $scope );
			$lines[] = 'aad-content:     ' . publisher_domain() . '|' . $resource_id . '|' . $scope . '|' . $scope;
			$lines[] = 'aad-wrap-bytes:  ' . bin2hex( $scope );
			$lines[] = 'signing-kid:     ' . $keys['signing_key_id'];
			$lines[] = 'rotation-secret-fp: ' . substr( hash( 'sha256', $secret ), 0, 16 );
			$lines[] = 'rotation-interval-hours: 1';

			// Issuer JWKS kid + algorithm we'd be wrapping against.
			try {
				$jwks_url    = sesamy_url( 'capsule/vendors/' . rawurlencode( $vendor_id ) . '/.well-known/jwks.json', 'api', true );
				$lines[]     = 'issuer-jwks-url: ' . $jwks_url;
				$resolver    = new IssuerJwksResolver();
				$issuer_keys = $resolver->getActiveIssuerKeys( $jwks_url );
				foreach ( $issuer_keys as $i => $k ) {
					$pem_fp  = substr( hash( 'sha256', $k['publicKeyPem'] ), 0, 16 );
					$lines[] = sprintf( 'issuer-key[%d]:   kid=%s alg=%s pem-sha256=%s', $i, $k['kid'], $k['algorithm'], $pem_fp );
				}
			} catch ( \Throwable $e ) {
				$lines[] = 'issuer-jwks-error: ' . $e->getMessage();
			}

			foreach ( [ 'current', 'next' ] as $slot ) {
				$kid = $rotation[ $slot ]['kid'];
				$w   = \Sesamy\Capsule\Publisher\Dca\Rotation::deriveWrapKey( $secret, $scope, $kid );
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64url transport encoding for the wrap key fingerprint.
				$b64     = rtrim( strtr( base64_encode( $w ), '+/', '-_' ), '=' );
				$lines[] = sprintf( 'wrapKey %-4s %s: %s (sha256-prefix=%s)', $slot, $kid, $b64, substr( hash( 'sha256', $w ), 0, 16 ) );
			}

			return "<!--\n" . implode( "\n", $lines ) . "\n-->";
		} catch ( \Throwable $e ) {
			return '<!-- sesamy-debug build-failed: ' . esc_html( $e->getMessage() ) . ' -->';
		}
	}

	/**
	 * Decode a base64url string to raw bytes.
	 *
	 * @param string $s Base64url-encoded input.
	 */
	private static function base64url_decode( string $s ): string {
		$s   = strtr( $s, '-_', '+/' );
		$pad = strlen( $s ) % 4;
		if ( 0 < $pad ) {
			$s .= str_repeat( '=', 4 - $pad );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- base64url decode for binary capsule material.
		return (string) base64_decode( $s, true );
	}
}
