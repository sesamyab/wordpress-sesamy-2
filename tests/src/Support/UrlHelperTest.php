<?php
use PHPUnit\Framework\TestCase;
use WP_Mock;

use function SesamyPlugin\Helpers\sesamy_url;
use function SesamyPlugin\Helpers\get_sesamy_routing_mode;

class UrlHelperTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	/**
	 * Mock the inputs `sesamy_url()` reads: no stored connection bundle, the
	 * `development_mode` setting (via the `sesamy_settings` option) and the
	 * `sesamy_routing` option. `use_first_party_proxy` is stored as an
	 * explicit null so routing falls through to the legacy `sesamy_routing`
	 * option these tests exercise.
	 */
	private function set_state( bool $dev_mode, array $routing ): void {
		WP_Mock::userFunction(
			'get_option',
			[
				'args'   => [ 'sesamy_connection' ],
				'return' => false,
			]
		);
		WP_Mock::userFunction(
			'get_option',
			[
				'args'   => [ 'sesamy_settings' ],
				'return' => [
					'development_mode'      => $dev_mode,
					'use_first_party_proxy' => null,
				],
			]
		);
		WP_Mock::userFunction(
			'get_option',
			[
				'args'   => [ 'sesamy_routing', [] ],
				'return' => $routing,
			]
		);
	}

	public function test_direct_mode_emits_production_api_host() {
		$this->set_state( false, [] );

		$this->assertSame(
			'https://api2.sesamy.com/foo',
			sesamy_url( 'foo', 'api' )
		);
	}

	public function test_direct_mode_emits_production_auth_host() {
		$this->set_state( false, [ 'mode' => 'direct' ] );

		$this->assertSame(
			'https://auth2.sesamy.com/connect/start',
			sesamy_url( 'connect/start', 'auth' )
		);
	}

	public function test_development_mode_swaps_tld_to_dev() {
		$this->set_state( true, [] );

		$this->assertSame(
			'https://api2.sesamy.dev/foo',
			sesamy_url( 'foo', 'api' )
		);
		$this->assertSame(
			'https://auth2.sesamy.dev/foo',
			sesamy_url( 'foo', 'auth' )
		);
		$this->assertSame(
			'https://scripts.sesamy.dev/s/wordpress-cli_x/bundle/latest',
			sesamy_url( 's/wordpress-cli_x/bundle/latest', 'scripts' )
		);
	}

	public function test_wordpress_proxy_mode_routes_api_through_publisher_host() {
		WP_Mock::userFunction(
			'home_url',
			[
				'args'   => [ '/sesamy' ],
				'return' => 'https://example.com/sesamy',
			]
		);
		$this->set_state( false, [ 'mode' => 'wordpress_proxy' ] );

		$this->assertSame(
			'https://example.com/sesamy/api/foo',
			sesamy_url( 'foo', 'api' )
		);
	}

	public function test_wordpress_proxy_mode_routes_auth_through_publisher_host() {
		WP_Mock::userFunction(
			'home_url',
			[
				'args'   => [ '/sesamy' ],
				'return' => 'https://example.com/sesamy',
			]
		);
		$this->set_state( false, [ 'mode' => 'wordpress_proxy' ] );

		$this->assertSame(
			'https://example.com/sesamy/auth/connect/start',
			sesamy_url( 'connect/start', 'auth' )
		);
	}

	public function test_wordpress_proxy_mode_keeps_scripts_direct() {
		// Even in proxy mode, the script bundle is fetched cross-origin.
		// Only its runtime API/auth bases are proxied — and that's encoded
		// in the bundle, not in the script URL itself.
		$this->set_state( false, [ 'mode' => 'wordpress_proxy' ] );

		$this->assertSame(
			'https://scripts.sesamy.com/s/wordpress-cli_abc/bundle/latest',
			sesamy_url( 's/wordpress-cli_abc/bundle/latest', 'scripts' )
		);
	}

	public function test_routing_mode_helper_defaults_to_direct() {
		$this->set_state( false, [] );

		$this->assertSame( 'direct', get_sesamy_routing_mode() );
	}

	public function test_routing_mode_helper_reads_wordpress_proxy() {
		$this->set_state( false, [ 'mode' => 'wordpress_proxy' ] );

		$this->assertSame( 'wordpress_proxy', get_sesamy_routing_mode() );
	}

	public function test_routing_mode_helper_prefers_proxy_toggle() {
		WP_Mock::userFunction(
			'get_option',
			[
				'args'   => [ 'sesamy_settings' ],
				'return' => [ 'use_first_party_proxy' => true ],
			]
		);

		$this->assertSame( 'wordpress_proxy', get_sesamy_routing_mode() );
	}
}
