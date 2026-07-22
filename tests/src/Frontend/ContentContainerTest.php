<?php
use PHPUnit\Framework\TestCase;
use WP_Mock;

class ContentContainerTest extends TestCase {
	protected $contentContainer;

	protected function setUp(): void {
		WP_Mock::setUp();
		$this->contentContainer = new SesamyPlugin\Frontend\ContentContainer();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	/**
	 * Set up common WordPress function mocks
	 */
	private function setupCommonMocks( $postId, $isLocked = false, $lockMode = 'encode' ) {
		// Common WordPress function mocks
		WP_Mock::userFunction( 'get_the_ID', [ 'return' => $postId ] );
		WP_Mock::userFunction( 'get_the_excerpt', [ 'return' => 'Preview' ] );
		WP_Mock::userFunction(
			'get_option',
			[
				'args'   => [ 'sesamy_settings' ],
				'return' => [ 'lock_mode' => $lockMode ],
			]
		);
		WP_Mock::userFunction(
			'get_extended',
			[
				'return' => [
					'main'     => 'Preview',
					'extended' => '',
				],
			]
		);
		WP_Mock::userFunction(
			'get_permalink',
			[
				'args'   => [ $postId ],
				'return' => "https://example.com/post/{$postId}",
			]
		);

		// Mock all possible get_post_meta calls
		WP_Mock::userFunction(
			'get_post_meta',
			[
				'args'   => [ $postId, '_sesamy_locked', true ],
				'return' => $isLocked ? '1' : '',
			]
		);
		WP_Mock::userFunction(
			'get_post_meta',
			[
				'args'   => [ $postId, '_sesamy_custom_paywall_url', true ],
				'return' => '',
			]
		);
		WP_Mock::userFunction(
			'get_post_meta',
			[
				'args'   => [ $postId, '_sesamy_locked_content_redirect_url', true ],
				'return' => '',
			]
		);

		// Mock helper functions
		WP_Mock::userFunction(
			'get_sesamy_setting',
			[
				'args'   => [ 'lock_mode' ],
				'return' => $lockMode,
			]
		);

		// Mock filters
		WP_Mock::userFunction(
			'apply_filters',
			[
				'args'       => [ 'sesamy_is_post_locked', WP_Mock\Functions::type( 'bool' ), $postId ],
				'return_arg' => 1,
			]
		);
		WP_Mock::userFunction(
			'apply_filters',
			[
				'args'   => [ 'sesamy_paywall_preview', WP_Mock\Functions::type( 'string' ) ],
				'return' => 'Preview',
			]
		);
		WP_Mock::userFunction(
			'apply_filters',
			[
				'args'   => [ 'sesamy_paywall', WP_Mock\Functions::type( 'string' ) ],
				'return' => '',
			]
		);

		// Mock wp_kses_post for various content
		WP_Mock::userFunction(
			'wp_kses_post',
			[
				'return' => function ( $input ) {
					return $input; },
			]
		);
	}

	public function test_process_content_returns_embed_for_public_article() {
		$postId = 123;
		$this->setupCommonMocks( $postId, false, 'encode' );

		$post = (object) [
			'ID'           => $postId,
			'post_content' => 'Test content',
		];

		$result = $this->contentContainer->process_content( $post, 'Test content' );

		$this->assertStringContainsString( 'lock-mode="embed"', $result );
		$this->assertStringContainsString( '<div slot="content">Test content</div>', $result );
	}

	public function test_process_content_locks_article_via_term_rule() {
		$postId = 789;
		// Meta says unlocked, but a locked_terms rule matches the post.
		WP_Mock::userFunction( 'get_the_ID', [ 'return' => $postId ] );
		WP_Mock::userFunction( 'get_the_excerpt', [ 'return' => 'Preview' ] );
		WP_Mock::userFunction(
			'get_option',
			[
				'args'   => [ 'sesamy_settings' ],
				'return' => [
					'lock_mode'    => 'encode',
					'locked_terms' => [
						[
							'taxonomy' => 'category',
							'term_id'  => 12,
						],
					],
				],
			]
		);
		WP_Mock::userFunction(
			'get_extended',
			[
				'return' => [
					'main'     => 'Preview',
					'extended' => '',
				],
			]
		);
		WP_Mock::userFunction(
			'get_permalink',
			[
				'args'   => [ $postId ],
				'return' => "https://example.com/post/{$postId}",
			]
		);
		WP_Mock::userFunction(
			'get_post_meta',
			[
				'args'   => [ $postId, '_sesamy_locked', true ],
				'return' => '',
			]
		);
		WP_Mock::userFunction(
			'get_post_meta',
			[
				'args'   => [ $postId, '_sesamy_custom_paywall_url', true ],
				'return' => '',
			]
		);
		WP_Mock::userFunction(
			'get_post_meta',
			[
				'args'   => [ $postId, '_sesamy_locked_content_redirect_url', true ],
				'return' => '',
			]
		);
		WP_Mock::userFunction(
			'has_term',
			[
				'args'   => [ 12, 'category', $postId ],
				'return' => true,
			]
		);
		WP_Mock::userFunction(
			'apply_filters',
			[
				'args'       => [ 'sesamy_is_post_locked', WP_Mock\Functions::type( 'bool' ), $postId ],
				'return_arg' => 1,
			]
		);
		WP_Mock::userFunction(
			'apply_filters',
			[
				'args'   => [ 'sesamy_paywall_preview', WP_Mock\Functions::type( 'string' ) ],
				'return' => 'Preview',
			]
		);
		WP_Mock::userFunction(
			'apply_filters',
			[
				'args'   => [ 'sesamy_paywall', WP_Mock\Functions::type( 'string' ) ],
				'return' => '',
			]
		);

		$post = (object) [
			'ID'           => $postId,
			'post_content' => 'Term locked content',
		];

		$result = $this->contentContainer->process_content( $post, 'Term locked content' );

		$this->assertStringContainsString( 'lock-mode="encode"', $result );
		$this->assertStringContainsString( 'style="display:none;">' . base64_encode( 'Term locked content' ), $result );
	}

	public function test_process_content_uses_plugin_setting_for_locked_article() {
		$postId = 456;
		$this->setupCommonMocks( $postId, true, 'encode' );

		$post = (object) [
			'ID'           => $postId,
			'post_content' => 'Locked content',
		];

		$result = $this->contentContainer->process_content( $post, 'Locked content' );

		$this->assertStringContainsString( 'lock-mode="encode"', $result );
		$this->assertStringContainsString( 'style="display:none;">' . base64_encode( 'Locked content' ), $result );
	}
}
