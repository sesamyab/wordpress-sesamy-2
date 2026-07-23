<?php
use PHPUnit\Framework\TestCase;
use WP_Mock;

class CoreTest extends TestCase {
	protected $core;

	protected function setUp(): void {
		WP_Mock::setUp();
		$this->core = new SesamyPlugin\Admin\Settings\Core();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function mockSanitizers() {
		WP_Mock::userFunction(
			'sanitize_key',
			[
				'return' => function ( $key ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
				},
			]
		);
		WP_Mock::userFunction(
			'absint',
			[
				'return' => function ( $value ) {
					return abs( (int) $value );
				},
			]
		);
		// Only `category` and `post_tag` exist as public + REST-exposed.
		WP_Mock::userFunction(
			'get_taxonomy',
			[
				'return' => function ( $taxonomy ) {
					if ( in_array( $taxonomy, [ 'category', 'post_tag' ], true ) ) {
						return (object) [
							'public'       => true,
							'show_in_rest' => true,
						];
					}
					if ( 'internal_tax' === $taxonomy ) {
						return (object) [
							'public'       => true,
							'show_in_rest' => false,
						];
					}
					return false;
				},
			]
		);
	}

	public function test_sanitize_locked_terms_parses_form_encoding() {
		$this->mockSanitizers();

		$result = $this->core->sanitize_sesamy_settings( [ 'locked_terms' => [ 'category:12', 'post_tag:34' ] ] );

		$this->assertSame(
			[
				[
					'taxonomy' => 'category',
					'term_id'  => 12,
				],
				[
					'taxonomy' => 'post_tag',
					'term_id'  => 34,
				],
			],
			$result['locked_terms']
		);
	}

	public function test_sanitize_locked_terms_accepts_canonical_rest_shape() {
		$this->mockSanitizers();

		$result = $this->core->sanitize_sesamy_settings(
			[
				'locked_terms' => [
					[
						'taxonomy' => 'category',
						'term_id'  => '12',
					],
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
			$result['locked_terms']
		);
	}

	public function test_sanitize_locked_terms_drops_malformed_values() {
		$this->mockSanitizers();

		$result = $this->core->sanitize_sesamy_settings(
			[
				'locked_terms' => [
					'no-term-id',
					'category:',
					[ 'taxonomy' => 'category' ],
					[
						'taxonomy' => '!!!',
						'term_id'  => 5,
					],
					[
						'taxonomy' => 'category',
						'term_id'  => 'abc',
					],
					// Unregistered and non-REST taxonomies are rejected even in
					// the canonical shape — the settings contract, not just the UI.
					[
						'taxonomy' => 'ghost_tax',
						'term_id'  => 7,
					],
					[
						'taxonomy' => 'internal_tax',
						'term_id'  => 8,
					],
					'internal_tax:9',
					'category:12',
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
			$result['locked_terms']
		);
	}
}
