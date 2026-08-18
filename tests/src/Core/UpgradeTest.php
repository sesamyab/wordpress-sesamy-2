<?php
/**
 * Schema migrations run once, record their progress, and back off on failure.
 *
 * @package Sesamy2
 */

use PHPUnit\Framework\TestCase;
use SesamyPlugin\Core\Upgrade;

/**
 * Subclass standing in for the real migration step, so these tests exercise
 * the runner's bookkeeping without reaching the management API.
 */
class UpgradeSpy extends Upgrade {

	/**
	 * How many times the step ran.
	 *
	 * @var int
	 */
	public static $calls = 0;

	/**
	 * What the step should report.
	 *
	 * @var bool
	 */
	public static $succeeds = true;

	/**
	 * @return bool
	 */
	protected static function migrate_to_1() {
		++self::$calls;
		return self::$succeeds;
	}
}

class UpgradeTest extends TestCase {

	/**
	 * Simulated `wp_options` rows this test writes through `update_option`.
	 *
	 * @var array<string, mixed>
	 */
	private $options = [];

	/**
	 * Simulated transients.
	 *
	 * @var array<string, mixed>
	 */
	private $transients = [];

	protected function setUp(): void {
		WP_Mock::setUp();
		UpgradeSpy::$calls    = 0;
		UpgradeSpy::$succeeds = true;
		$this->options        = [];
		$this->transients     = [];

		WP_Mock::userFunction(
			'get_option',
			[ 'return' => fn( $name, $default_value = false ) => $this->options[ $name ] ?? $default_value ]
		);
		WP_Mock::userFunction(
			'update_option',
			[
				'return' => function ( $name, $value ) {
					$this->options[ $name ] = $value;
					return true;
				},
			]
		);
		WP_Mock::userFunction(
			'get_transient',
			[ 'return' => fn( $name ) => $this->transients[ $name ] ?? false ]
		);
		WP_Mock::userFunction(
			'set_transient',
			[
				'return' => function ( $name, $value ) {
					$this->transients[ $name ] = $value;
					return true;
				},
			]
		);
		WP_Mock::userFunction(
			'delete_transient',
			[
				'return' => function ( $name ) {
					unset( $this->transients[ $name ] );
					return true;
				},
			]
		);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function storedVersion() {
		return $this->options['sesamy_schema_version'] ?? null;
	}

	/**
	 * An install that predates the runner has no version row at all.
	 */
	public function test_install_without_a_stored_version_is_pending() {
		$this->assertTrue( UpgradeSpy::is_pending() );
	}

	public function test_run_executes_the_migration_and_records_the_version() {
		$this->assertTrue( UpgradeSpy::run() );

		$this->assertSame( 1, UpgradeSpy::$calls );
		$this->assertSame( Upgrade::SCHEMA_VERSION, $this->storedVersion() );
		$this->assertFalse( UpgradeSpy::is_pending() );
	}

	/**
	 * The acceptance criterion: repeated runs are no-ops. This is what stops
	 * every admin page load from re-PUTting the domain.
	 */
	public function test_repeated_runs_are_no_ops() {
		UpgradeSpy::run();
		UpgradeSpy::run();
		UpgradeSpy::run();

		$this->assertSame( 1, UpgradeSpy::$calls );
		$this->assertSame( Upgrade::SCHEMA_VERSION, $this->storedVersion() );
	}

	/**
	 * A failed migration must not be recorded as done — the install is still
	 * on `jwksUri` and needs another attempt.
	 */
	public function test_failed_migration_leaves_the_version_behind_and_backs_off() {
		UpgradeSpy::$succeeds = false;

		$this->assertFalse( UpgradeSpy::run() );

		$this->assertSame( 1, UpgradeSpy::$calls );
		$this->assertNull( $this->storedVersion() );
		$this->assertTrue( UpgradeSpy::is_pending() );
		$this->assertNotEmpty( $this->transients['sesamy_schema_upgrade_retry'] ?? null );
	}

	/**
	 * ...and the retry succeeds once whatever broke is fixed.
	 */
	public function test_a_later_retry_completes_the_migration() {
		UpgradeSpy::$succeeds = false;
		UpgradeSpy::run();

		UpgradeSpy::$succeeds = true;
		$this->assertTrue( UpgradeSpy::run() );

		$this->assertSame( 2, UpgradeSpy::$calls );
		$this->assertSame( Upgrade::SCHEMA_VERSION, $this->storedVersion() );
		$this->assertArrayNotHasKey( 'sesamy_schema_upgrade_retry', $this->transients );
	}

	/**
	 * `maybe_run()` is the request-path entry point: it must stay out of
	 * front-end requests, where the outbound PUT would cost the reader
	 * latency — the whole point of the change it's migrating to.
	 */
	public function test_maybe_run_skips_front_end_requests() {
		WP_Mock::userFunction( 'is_admin', [ 'return' => false ] );
		WP_Mock::userFunction( 'wp_doing_cron', [ 'return' => false ] );

		( new UpgradeSpy() )->maybe_run();

		$this->assertSame( 0, UpgradeSpy::$calls );
		$this->assertTrue( UpgradeSpy::is_pending() );
	}

	public function test_maybe_run_migrates_on_an_admin_request() {
		WP_Mock::userFunction( 'is_admin', [ 'return' => true ] );
		WP_Mock::userFunction( 'wp_doing_cron', [ 'return' => false ] );

		( new UpgradeSpy() )->maybe_run();

		$this->assertSame( 1, UpgradeSpy::$calls );
		$this->assertFalse( UpgradeSpy::is_pending() );
	}

	public function test_maybe_run_respects_the_retry_back_off() {
		$this->transients['sesamy_schema_upgrade_retry'] = 1;
		WP_Mock::userFunction( 'is_admin', [ 'return' => true ] );
		WP_Mock::userFunction( 'wp_doing_cron', [ 'return' => false ] );

		( new UpgradeSpy() )->maybe_run();

		$this->assertSame( 0, UpgradeSpy::$calls );
	}

	public function test_maybe_run_does_nothing_once_migrated() {
		$this->options['sesamy_schema_version'] = Upgrade::SCHEMA_VERSION;
		WP_Mock::userFunction( 'is_admin', [ 'return' => true ] );
		WP_Mock::userFunction( 'wp_doing_cron', [ 'return' => false ] );

		( new UpgradeSpy() )->maybe_run();

		$this->assertSame( 0, UpgradeSpy::$calls );
	}
}
