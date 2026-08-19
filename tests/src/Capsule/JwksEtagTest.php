<?php
/**
 * `If-None-Match` parsing for the JWKS endpoint's 304 handling.
 *
 * @package Sesamy2
 */

use PHPUnit\Framework\TestCase;
use SesamyPlugin\Capsule\Jwks;

class JwksEtagTest extends TestCase {

	/**
	 * ETag of the document under test.
	 */
	private const ETAG = '"d41d8cd98f00b204e9800998ecf8427e"';

	protected function setUp(): void {
		WP_Mock::setUp();
		$this->setupCommonMocks();
	}

	/**
	 * Set up common WordPress function mocks — the request-superglobal
	 * sanitisers `etag_matches()` runs the header through.
	 */
	private function setupCommonMocks(): void {
		WP_Mock::userFunction( 'wp_unslash', [ 'return' => fn( $value ) => $value ] );
		WP_Mock::userFunction( 'sanitize_text_field', [ 'return' => fn( $value ) => trim( strip_tags( (string) $value ) ) ] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test double for sanitize_text_field itself.
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		unset( $_SERVER['HTTP_IF_NONE_MATCH'] );
	}

	/**
	 * @param string|null $header Value for the request's `If-None-Match`, or null to omit it.
	 */
	private function etagMatches( $header ): bool {
		if ( null === $header ) {
			unset( $_SERVER['HTTP_IF_NONE_MATCH'] );
		} else {
			$_SERVER['HTTP_IF_NONE_MATCH'] = $header;
		}

		$method = new ReflectionMethod( Jwks::class, 'etag_matches' );
		$method->setAccessible( true );
		return (bool) $method->invoke( new Jwks(), self::ETAG );
	}

	public function test_absent_header_is_not_a_match() {
		$this->assertFalse( $this->etagMatches( null ) );
	}

	public function test_empty_header_is_not_a_match() {
		$this->assertFalse( $this->etagMatches( '   ' ) );
	}

	public function test_wildcard_matches() {
		$this->assertTrue( $this->etagMatches( '*' ) );
	}

	public function test_exact_tag_matches() {
		$this->assertTrue( $this->etagMatches( self::ETAG ) );
	}

	/**
	 * Weak comparison per RFC 9110 §8.8.3.2 — caches and proxies routinely
	 * weaken a validator (nginx does it whenever it gzips), so `W/"x"` has to
	 * satisfy `"x"` or every revalidation would come back as a full 200.
	 */
	public function test_weak_tag_matches() {
		$this->assertTrue( $this->etagMatches( 'W/' . self::ETAG ) );
	}

	public function test_tag_in_a_list_matches() {
		$this->assertTrue( $this->etagMatches( '"aaa", "bbb", ' . self::ETAG ) );
	}

	public function test_weak_tag_in_a_list_matches() {
		$this->assertTrue( $this->etagMatches( 'W/"aaa", W/' . self::ETAG . ', "ccc"' ) );
	}

	public function test_different_tag_is_not_a_match() {
		$this->assertFalse( $this->etagMatches( '"0000000000000000000000000000000a"' ) );
	}

	public function test_list_without_our_tag_is_not_a_match() {
		$this->assertFalse( $this->etagMatches( '"aaa", W/"bbb"' ) );
	}

	/**
	 * The unquoted form is malformed per the grammar; treat it as a miss and
	 * serve the body rather than guessing.
	 */
	public function test_unquoted_tag_is_not_a_match() {
		$this->assertFalse( $this->etagMatches( trim( self::ETAG, '"' ) ) );
	}
}
