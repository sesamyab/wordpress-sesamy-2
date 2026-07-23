<?php
use PHPUnit\Framework\TestCase;
use WP_Mock;

use function SesamyPlugin\Helpers\get_locked_term_rules;
use function SesamyPlugin\Helpers\get_post_locking_term;
use function SesamyPlugin\Helpers\is_post_locked;

class LockHelpersTest extends TestCase {
	protected function setUp(): void {
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function mockSettings( array $settings ) {
		WP_Mock::userFunction(
			'get_option',
			[
				'args'   => [ 'sesamy_settings' ],
				'return' => $settings,
			]
		);
	}

	private function mockLockedMeta( int $postId, $value ) {
		WP_Mock::userFunction(
			'get_post_meta',
			[
				'args'   => [ $postId, '_sesamy_locked', true ],
				'return' => $value,
			]
		);
	}

	private function mockLockFilterPassthrough( int $postId ) {
		WP_Mock::userFunction(
			'apply_filters',
			[
				'args'       => [ 'sesamy_is_post_locked', WP_Mock\Functions::type( 'bool' ), $postId ],
				'return_arg' => 1,
			]
		);
	}

	public function test_meta_locked_post_is_locked() {
		$this->mockLockedMeta( 1, '1' );
		$this->mockLockFilterPassthrough( 1 );

		$this->assertTrue( is_post_locked( 1 ) );
	}

	public function test_unlocked_post_without_rules_is_not_locked() {
		$this->mockSettings( [] );
		$this->mockLockedMeta( 2, '' );
		$this->mockLockFilterPassthrough( 2 );

		$this->assertFalse( is_post_locked( 2 ) );
	}

	public function test_legacy_zero_string_meta_is_not_locked() {
		$this->mockSettings( [] );
		$this->mockLockedMeta( 3, '0' );
		$this->mockLockFilterPassthrough( 3 );

		$this->assertFalse( is_post_locked( 3 ) );
	}

	public function test_term_rule_locks_post_without_meta() {
		$this->mockSettings( [ 'locked_terms' => [ [ 'taxonomy' => 'category', 'term_id' => 12 ] ] ] );
		$this->mockLockedMeta( 4, '' );
		WP_Mock::userFunction(
			'has_term',
			[
				'args'   => [ 12, 'category', 4 ],
				'return' => true,
			]
		);
		$this->mockLockFilterPassthrough( 4 );

		$this->assertTrue( is_post_locked( 4 ) );
	}

	public function test_non_matching_term_rule_leaves_post_unlocked() {
		$this->mockSettings( [ 'locked_terms' => [ [ 'taxonomy' => 'category', 'term_id' => 12 ] ] ] );
		$this->mockLockedMeta( 5, '' );
		WP_Mock::userFunction(
			'has_term',
			[
				'args'   => [ 12, 'category', 5 ],
				'return' => false,
			]
		);
		$this->mockLockFilterPassthrough( 5 );

		$this->assertFalse( is_post_locked( 5 ) );
	}

	public function test_accepts_numeric_string_id() {
		$this->mockLockedMeta( 6, '1' );
		$this->mockLockFilterPassthrough( 6 );

		$this->assertTrue( is_post_locked( '6' ) );
	}

	public function test_filter_can_override_lock_state() {
		$this->mockSettings( [] );
		$this->mockLockedMeta( 7, '' );
		WP_Mock::onFilter( 'sesamy_is_post_locked' )
			->with( false, 7 )
			->reply( true );

		$this->assertTrue( is_post_locked( 7 ) );
	}

	public function test_get_post_locking_term_returns_first_match() {
		$this->mockSettings(
			[
				'locked_terms' => [
					[ 'taxonomy' => 'category', 'term_id' => 12 ],
					[ 'taxonomy' => 'post_tag', 'term_id' => 34 ],
				],
			]
		);
		WP_Mock::userFunction(
			'has_term',
			[
				'args'   => [ 12, 'category', 8 ],
				'return' => false,
			]
		);
		WP_Mock::userFunction(
			'has_term',
			[
				'args'   => [ 34, 'post_tag', 8 ],
				'return' => true,
			]
		);

		$this->assertSame(
			[
				'taxonomy' => 'post_tag',
				'term_id'  => 34,
			],
			get_post_locking_term( 8 )
		);
	}

	public function test_get_locked_term_rules_skips_malformed_entries() {
		$this->mockSettings(
			[
				'locked_terms' => [
					[ 'taxonomy' => 'category', 'term_id' => '12' ],
					[ 'taxonomy' => '' ],
					'not-an-array',
					[ 'term_id' => 5 ],
				],
			]
		);

		$this->assertSame(
			[
				[
					'taxonomy' => 'category',
					'term_id'  => 12,
				],
			],
			get_locked_term_rules()
		);
	}
}
