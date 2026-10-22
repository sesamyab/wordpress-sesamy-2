<?php
/**
 * The inline sesamy-js config names the configured vendor.
 *
 * @package Sesamy2
 */

use PHPUnit\Framework\TestCase;
use SesamyPlugin\Core\Assets;

class AssetsTest extends TestCase {

	/**
	 * Simulated `wp_options` rows read through `get_option`.
	 *
	 * @var array<string, mixed>
	 */
	private $options = [];

	protected function setUp(): void {
		WP_Mock::setUp();

		if ( ! defined( 'SESAMY_PLUGIN_DIST_PATH' ) ) {
			// No built loader in the test tree, so rendering stops after the config.
			define( 'SESAMY_PLUGIN_DIST_PATH', sys_get_temp_dir() . '/sesamy-no-dist/' );
		}

		$this->options = [
			'sesamy_connection' => [
				'client_id'   => 'm2m-client',
				'tenant'      => 'acme-vendor',
				'environment' => 'prod',
			],
			'sesamy_settings'   => [
				'default_paywall'       => 'pw_1',
				'enabled_content_types' => [ 'post' ],
				'lock_mode'             => 'capsule',
				'use_first_party_proxy' => false,
			],
		];

		WP_Mock::userFunction(
			'get_option',
			[ 'return' => fn( $name, $default_value = false ) => $this->options[ $name ] ?? $default_value ]
		);
		WP_Mock::userFunction( 'esc_html', [ 'return' => fn( $text ) => $text ] );
		WP_Mock::userFunction( 'wp_json_encode', [ 'return' => fn( $data ) => json_encode( $data ) ] );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	/**
	 * Render the bootstrap and decode the `<script id="sesamy-js">` config.
	 *
	 * @return array<string, mixed>
	 */
	private function renderedConfig(): array {
		ob_start();
		( new Assets() )->render_sesamy_bootstrap();
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match( '#<script type="application/json" id="sesamy-js">(.*?)</script>#s', $html, $m ) );
		return json_decode( $m[1], true );
	}

	/**
	 * sesamy-js >= 1.139.0 reads `vendorId`; older versions only know
	 * `clientId`. Both must name the vendor (tenant), not the M2M client.
	 */
	public function test_config_names_the_vendor_under_both_keys() {
		$config = $this->renderedConfig();

		$this->assertSame( 'acme-vendor', $config['vendorId'] ?? null );
		$this->assertSame( 'acme-vendor', $config['clientId'] ?? null );
	}
}
