<?php
/**
 * Minimal stand-ins for core WordPress pieces WP_Mock doesn't provide.
 *
 * WP_Mock fakes *functions* on demand, but `WP_Error` is a class and
 * `is_wp_error()` has to agree with it, so both have to exist for real before
 * any code under test constructs one. Only the surface the plugin actually
 * uses is implemented.
 *
 * @package Sesamy2
 */

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Test double for core's `WP_Error`.
	 */
	class WP_Error {

		/**
		 * Error codes in insertion order.
		 *
		 * @var array<int, string>
		 */
		private $codes = [];

		/**
		 * Messages keyed by code.
		 *
		 * @var array<string, array<int, string>>
		 */
		private $messages = [];

		/**
		 * Data keyed by code.
		 *
		 * @var array<string, mixed>
		 */
		private $data = [];

		/**
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Optional error data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( '' === $code ) {
				return;
			}
			$this->add( $code, $message, $data );
		}

		/**
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Optional error data.
		 * @return void
		 */
		public function add( $code, $message = '', $data = '' ) {
			$this->codes[]            = $code;
			$this->messages[ $code ][] = (string) $message;
			if ( '' !== $data ) {
				$this->data[ $code ] = $data;
			}
		}

		/**
		 * @return string The first code added, or `''`.
		 */
		public function get_error_code() {
			return $this->codes[0] ?? '';
		}

		/**
		 * @param string $code Optional code; defaults to the first one.
		 * @return string
		 */
		public function get_error_message( $code = '' ) {
			$code = '' !== $code ? $code : $this->get_error_code();
			return $this->messages[ $code ][0] ?? '';
		}

		/**
		 * @param string $code Optional code; defaults to the first one.
		 * @return mixed
		 */
		public function get_error_data( $code = '' ) {
			$code = '' !== $code ? $code : $this->get_error_code();
			return $this->data[ $code ] ?? null;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value to check.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
