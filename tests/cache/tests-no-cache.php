<?php
/**
 * Tests for EDD\Cache\NoCache.
 *
 * @package   EDD\Tests\Cache
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     3.6.7
 */

namespace EDD\Tests\Cache;

use EDD\Cache\NoCache as Utility;
use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * @coversDefaultClass \EDD\Cache\NoCache
 */
class NoCache extends EDD_UnitTestCase {

	/**
	 * Tear down after each test.
	 */
	public function tearDown(): void {
		Utility::reset();
		parent::tearDown();
	}

	/**
	 * @covers ::set_headers
	 */
	public function test_set_headers_registers_nocache_headers_filter() {
		Utility::set_headers();

		$this->assertNotFalse( has_filter( 'nocache_headers' ) );
	}

	/**
	 * @covers ::set_headers
	 */
	public function test_nocache_headers_filter_adds_referrer_policy() {
		Utility::set_headers();

		$headers = apply_filters( 'nocache_headers', array() );

		$this->assertArrayHasKey( 'Referrer-Policy', $headers );
		$this->assertSame( 'no-referrer', $headers['Referrer-Policy'] );
	}

	/**
	 * @covers ::set_headers
	 */
	public function test_nocache_headers_filter_adds_litespeed_header() {
		Utility::set_headers();

		$headers = apply_filters( 'nocache_headers', array() );

		$this->assertArrayHasKey( 'X-LiteSpeed-Cache-Control', $headers );
		$this->assertSame( 'no-store', $headers['X-LiteSpeed-Cache-Control'] );
	}

	/**
	 * @covers ::set_headers
	 */
	public function test_extra_headers_are_merged_with_defaults() {
		Utility::set_headers( array( 'X-Custom-Header' => 'custom-value' ) );

		$headers = apply_filters( 'nocache_headers', array() );

		$this->assertArrayHasKey( 'X-Custom-Header', $headers );
		$this->assertSame( 'custom-value', $headers['X-Custom-Header'] );
		// Defaults should still be present.
		$this->assertArrayHasKey( 'Referrer-Policy', $headers );
	}

	/**
	 * @covers ::set_headers
	 */
	public function test_empty_string_value_removes_default_header() {
		Utility::set_headers( array( 'Referrer-Policy' => '' ) );

		$headers = apply_filters( 'nocache_headers', array() );

		$this->assertArrayNotHasKey( 'Referrer-Policy', $headers );
		// Other defaults should still be present.
		$this->assertArrayHasKey( 'X-LiteSpeed-Cache-Control', $headers );
	}

	/**
	 * @covers ::set_headers
	 */
	public function test_extra_headers_override_defaults() {
		Utility::set_headers( array( 'Referrer-Policy' => 'strict-origin' ) );

		$headers = apply_filters( 'nocache_headers', array() );

		$this->assertSame( 'strict-origin', $headers['Referrer-Policy'] );
	}

	/**
	 * @covers ::set_headers
	 */
	public function test_header_names_are_sanitized() {
		Utility::set_headers( array( "X-Injected\r\nHeader" => 'value' ) );

		$headers = apply_filters( 'nocache_headers', array() );

		// The injected newline should have been stripped by sanitize_text_field.
		foreach ( array_keys( $headers ) as $name ) {
			$this->assertStringNotContainsString( "\r\n", $name );
		}
	}

	/**
	 * @covers ::set_headers
	 */
	public function test_header_values_are_sanitized() {
		Utility::set_headers( array( 'X-Test' => "value\r\nX-Injected: bad" ) );

		$headers = apply_filters( 'nocache_headers', array() );

		if ( isset( $headers['X-Test'] ) ) {
			$this->assertStringNotContainsString( "\r\n", $headers['X-Test'] );
		}
	}

	/**
	 * @covers ::set_headers
	 */
	public function test_donotcachepage_constant_is_boolean_true() {
		Utility::set_headers();

		// Constant is defined somewhere in this test run; verify it is boolean true, not string 'true'.
		$this->assertTrue( DONOTCACHEPAGE );
		$this->assertIsBool( DONOTCACHEPAGE );
	}

	/**
	 * @covers ::set_headers
	 */
	public function test_existing_headers_are_preserved() {
		Utility::set_headers();

		$headers = apply_filters( 'nocache_headers', array( 'Cache-Control' => 'no-cache' ) );

		$this->assertArrayHasKey( 'Cache-Control', $headers );
	}

	/**
	 * A second call with no overrides must not re-introduce a header that an
	 * earlier call suppressed — this guards against the cart-recovery / checkout
	 * double-call regression where Recovery::handle_recovery_actions() called
	 * set_headers() with defaults after Handler::check_request() had already
	 * suppressed Referrer-Policy.
	 *
	 * @covers ::set_headers
	 */
	public function test_second_call_with_no_args_preserves_earlier_suppression() {
		Utility::set_headers( array( 'Referrer-Policy' => '' ) );
		Utility::set_headers();

		$headers = apply_filters( 'nocache_headers', array() );

		$this->assertArrayNotHasKey( 'Referrer-Policy', $headers );
	}

	/**
	 * Only one filter should be registered on nocache_headers regardless of
	 * how many times set_headers() is called.
	 *
	 * @covers ::set_headers
	 */
	public function test_only_one_filter_is_registered_after_multiple_calls() {
		Utility::set_headers();
		Utility::set_headers();
		Utility::set_headers();

		$this->assertNotFalse( has_filter( 'nocache_headers' ) );

		global $wp_filter;
		$callbacks = $wp_filter['nocache_headers']->callbacks[10] ?? array();
		$this->assertCount( 1, $callbacks );
	}
}
