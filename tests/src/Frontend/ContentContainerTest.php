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

	/**
	 * Set up mocks for a post that is locked only via a matching `locked_terms`
	 * rule (per-post `_sesamy_locked` meta stays unset) under the given lock mode.
	 */
	private function setupTermLockMocks( $post_id, $lockMode ) {
		WP_Mock::userFunction( 'get_the_ID', [ 'return' => $post_id ] );
		WP_Mock::userFunction( 'get_the_excerpt', [ 'return' => 'Preview' ] );
		WP_Mock::userFunction(
			'get_option',
			[
				'args'   => [ 'sesamy_settings' ],
				'return' => [
					'lock_mode'    => $lockMode,
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
				'args'   => [ $post_id ],
				'return' => "https://example.com/post/{$post_id}",
			]
		);
		WP_Mock::userFunction(
			'get_post_meta',
			[
				'args'   => [ $post_id, '_sesamy_locked', true ],
				'return' => '',
			]
		);
		WP_Mock::userFunction(
			'get_post_meta',
			[
				'args'   => [ $post_id, '_sesamy_custom_paywall_url', true ],
				'return' => '',
			]
		);
		WP_Mock::userFunction(
			'get_post_meta',
			[
				'args'   => [ $post_id, '_sesamy_locked_content_redirect_url', true ],
				'return' => '',
			]
		);
		WP_Mock::userFunction(
			'has_term',
			[
				'args'   => [ 12, 'category', $post_id ],
				'return' => true,
			]
		);
		WP_Mock::userFunction(
			'apply_filters',
			[
				'args'       => [ 'sesamy_is_post_locked', WP_Mock\Functions::type( 'bool' ), $post_id ],
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
	}

	public function test_process_content_locks_article_via_term_rule() {
		$post_id = 789;
		// Meta says unlocked, but a locked_terms rule matches the post.
		$this->setupTermLockMocks( $post_id, 'encode' );

		$post = (object) [
			'ID'           => $post_id,
			'post_content' => 'Term locked content',
		];

		$result = $this->contentContainer->process_content( $post, 'Term locked content' );

		$this->assertStringContainsString( 'lock-mode="encode"', $result );
		$this->assertStringContainsString( 'style="display:none;">' . base64_encode( 'Term locked content' ), $result );
	}

	public function test_process_content_honors_embed_lock_mode_for_term_rule() {
		$post_id = 790;
		// Same term-locked scenario, but the plugin lock mode is `embed`, so the
		// content must render visibly inside the container (no base64 encoding).
		$this->setupTermLockMocks( $post_id, 'embed' );

		$post = (object) [
			'ID'           => $post_id,
			'post_content' => 'Term locked content',
		];

		$result = $this->contentContainer->process_content( $post, 'Term locked content' );

		$this->assertStringContainsString( 'lock-mode="embed"', $result );
		$this->assertStringContainsString( '<div slot="content">Term locked content</div>', $result );
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

	/**
	 * HTML has no self-closing syntax for non-void elements. A trailing `/`
	 * leaves `<sesamy-paywall>` open, so the parser nests every sibling that
	 * follows it inside the element. Assert the explicit closing tag.
	 */
	public function test_render_paywall_emits_an_explicit_closing_tag() {
		$post_id = 789;

		WP_Mock::userFunction( 'get_the_ID', [ 'return' => $post_id ] );
		WP_Mock::userFunction(
			'get_post_meta',
			[
				'args'   => [ $post_id, '_sesamy_custom_paywall_url', true ],
				'return' => '',
			]
		);
		WP_Mock::userFunction(
			'get_post_meta',
			[
				'args'   => [ $post_id, '_sesamy_locked_content_redirect_url', true ],
				'return' => '',
			]
		);
		WP_Mock::userFunction(
			'get_option',
			[
				'args'   => [ 'sesamy_settings' ],
				'return' => [ 'default_paywall' => 'https://example.com/paywall.json' ],
			]
		);

		$result = $this->contentContainer->render_paywall();

		$this->assertSame(
			'<sesamy-paywall settings-url="https://example.com/paywall.json"></sesamy-paywall>',
			$result
		);
		$this->assertStringNotContainsString( '/>', $result );
	}
}
