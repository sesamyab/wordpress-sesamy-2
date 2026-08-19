<?php
/**
 * Registration mode selection: the plugin pins its signing key PEM rather
 * than pointing the api-proxy at our JWKS endpoint.
 *
 * @package Sesamy2
 */

use PHPUnit\Framework\TestCase;
use SesamyPlugin\Capsule\Registration;

class RegistrationModeTest extends TestCase {

	/**
	 * Body of the last PUT `Registration::request()` issued.
	 *
	 * @var array<string, mixed>|null
	 */
	private $captured_body = null;

	/**
	 * URL of the last request `Registration::request()` issued.
	 *
	 * @var string
	 */
	private $captured_url = '';

	protected function setUp(): void {
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		$this->captured_body = null;
		$this->captured_url  = '';
	}

	// -----------------------------------------------------------------
	// pick_mode()
	// -----------------------------------------------------------------

	private function pickMode( string $domain ): string {
		$method = new ReflectionMethod( Registration::class, 'pick_mode' );
		$method->setAccessible( true );
		return (string) $method->invoke( null, $domain );
	}

	/**
	 * The case that regressed production: a public HTTPS install used to get
	 * `jwks_uri`, which put a fail-closed fetch of the publisher's own origin
	 * in every reader's unlock path.
	 */
	public function test_public_https_canonical_domain_picks_pem() {
		$this->setupCommonMocks();

		$this->assertSame( 'pem', $this->pickMode( 'example.com' ) );
	}

	public function test_local_install_picks_pem() {
		$this->setupCommonMocks( 'local', 'http://example.test' );

		$this->assertSame( 'pem', $this->pickMode( 'example.test' ) );
	}

	public function test_admin_added_domain_picks_pem() {
		$this->setupCommonMocks();

		$this->assertSame( 'pem', $this->pickMode( 'staging.example.com' ) );
	}

	// -----------------------------------------------------------------
	// register_domain()
	// -----------------------------------------------------------------

	public function test_register_domain_defaults_to_signing_key_pem() {
		$this->mockConnectedSite();

		$result = Registration::register_domain( 'example.com' );

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 'https://api2.sesamy.com/management/domains/example.com', $this->captured_url );
		$this->assertArrayHasKey( 'signingKeyPem', (array) $this->captured_body );
		$this->assertArrayNotHasKey( 'jwksUri', (array) $this->captured_body );
		$this->assertSame( "-----BEGIN PUBLIC KEY-----\nstub\n-----END PUBLIC KEY-----\n", $this->captured_body['signingKeyPem'] );
	}

	/**
	 * `jwks_uri` is no longer auto-selected, but it stays a supported mode for
	 * consumers that ask for it — the settings UI still offers it.
	 */
	public function test_register_domain_still_honours_forced_jwks_uri() {
		$this->mockConnectedSite();

		$result = Registration::register_domain( 'example.com', 'jwks_uri' );

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame(
			[ 'jwksUri' => 'https://example.com/.well-known/dca-publishers.json' ],
			(array) $this->captured_body
		);
	}

	public function test_register_domain_rejects_an_unknown_mode() {
		$this->mockConnectedSite();

		$result = Registration::register_domain( 'example.com', 'nonsense' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'sesamy_invalid_mode', $result->get_error_code() );
	}

	// -----------------------------------------------------------------
	// Fixtures
	// -----------------------------------------------------------------

	/**
	 * Set up common WordPress function mocks.
	 *
	 * @param string $environment Value for `wp_get_environment_type()`.
	 * @param string $home_url    Base URL `home_url()` builds on.
	 */
	private function setupCommonMocks( $environment = 'production', $home_url = 'https://example.com' ): void {
		WP_Mock::userFunction( 'wp_get_environment_type', [ 'return' => $environment ] );
		WP_Mock::userFunction( 'home_url', [ 'return' => fn( $path = '' ) => $home_url . $path ] );
	}

	/**
	 * Stand up a connected, publicly-reachable site with a persisted key
	 * bundle, and capture whatever `Registration` PUTs to the management API.
	 */
	private function mockConnectedSite(): void {
		$this->setupCommonMocks();
		WP_Mock::userFunction( 'wp_json_encode', [ 'return' => fn( $value ) => json_encode( $value ) ] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test double for wp_json_encode itself.

		WP_Mock::userFunction(
			'get_option',
			[
				'return' => function ( $name, $default_value = false ) {
					switch ( $name ) {
						case 'sesamy_connection':
							return [
								'client_id'     => 'cid',
								'client_secret' => 'secret',
								'environment'   => 'prod',
							];
						case 'sesamy_settings':
							return [ 'use_first_party_proxy' => false ];
						// A complete bundle keeps `Service::ensure_keys()` from
						// generating a real keypair mid-test.
						case 'sesamy_capsule_keys':
							return [
								'signing_private_key_pem' => "-----BEGIN PRIVATE KEY-----\nstub\n-----END PRIVATE KEY-----\n",
								'signing_public_key_pem'  => "-----BEGIN PUBLIC KEY-----\nstub\n-----END PUBLIC KEY-----\n",
								'signing_key_id'          => '2026-08',
								'rotation_secret'         => 'stub-secret',
								'created_at'              => 1755000000,
							];
					}
					return $default_value;
				},
			]
		);

		// A cached management token short-circuits the client-credentials call.
		WP_Mock::userFunction( 'get_transient', [ 'return' => 'stub-access-token' ] );

		WP_Mock::userFunction(
			'wp_remote_request',
			[
				'return' => function ( $url, $args ) {
					$this->captured_url  = (string) $url;
					$this->captured_body = json_decode( (string) ( $args['body'] ?? '' ), true );
					return [ 'stub_response' => true ];
				},
			]
		);
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code', [ 'return' => 200 ] );
		WP_Mock::userFunction( 'wp_remote_retrieve_body', [ 'return' => '{"domain":"example.com"}' ] );
	}
}

