<?php
use PHPUnit\Framework\TestCase;
use SesamyPlugin\Proxy\Router;

class RouterTest extends TestCase {
	/**
	 * Snapshot of $_SERVER, restored after each test.
	 *
	 * @var array<string, mixed>
	 */
	private $server;

	protected function setUp(): void {
		WP_Mock::setUp();
		$this->server = $_SERVER;
		unset( $_SERVER['HTTP_VENDOR_ID'] );

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::userFunction( 'is_ssl', [ 'return' => true ] );
		WP_Mock::userFunction(
			'get_option',
			[
				'args'   => [ 'sesamy_routing', [] ],
				'return' => [],
			]
		);
	}

	protected function tearDown(): void {
		$_SERVER = $this->server;
		WP_Mock::tearDown();
	}

	/**
	 * Mock the stored connection bundle; `tenant` is the vendor id.
	 *
	 * @param string|null $tenant Configured vendor id, or null for not connected.
	 */
	private function mockVendor( $tenant ) {
		WP_Mock::userFunction(
			'get_option',
			[
				'args'   => [ 'sesamy_connection' ],
				'return' => null === $tenant ? false : [ 'tenant' => $tenant ],
			]
		);
		WP_Mock::userFunction(
			'get_option',
			[
				'args'   => [ 'sesamy_settings' ],
				'return' => false,
			]
		);
	}

	private function collectHeaders() {
		$method = new ReflectionMethod( Router::class, 'collect_request_headers' );
		return $method->invoke( new Router() );
	}

	public function test_sets_vendor_id_from_configured_vendor() {
		$this->mockVendor( 'acme' );

		$headers = $this->collectHeaders();

		$this->assertSame( 'acme', $headers['Vendor-Id'] );
	}

	public function test_configured_vendor_overrides_browser_vendor_id() {
		$this->mockVendor( 'acme' );
		$_SERVER['HTTP_VENDOR_ID'] = 'other';

		$headers = $this->collectHeaders();

		$this->assertSame( 'acme', $headers['Vendor-Id'] );
	}

	public function test_forwards_browser_vendor_id_when_no_vendor_is_configured() {
		$this->mockVendor( null );
		$_SERVER['HTTP_VENDOR_ID'] = 'acme';

		$headers = $this->collectHeaders();

		$this->assertSame( 'acme', $headers['Vendor-Id'] );
	}

	public function test_omits_vendor_id_when_neither_is_present() {
		$this->mockVendor( null );

		$headers = $this->collectHeaders();

		$this->assertArrayNotHasKey( 'Vendor-Id', $headers );
	}
}
