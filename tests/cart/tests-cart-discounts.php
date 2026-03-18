<?php
/**
 * Cart Discount Normalization Tests
 *
 * Tests for cart discount normalization to ensure discounts are always
 * returned as arrays, even when stored as strings in the session.
 *
 * @package     EDD
 * @subpackage  Tests\Cart
 * @copyright   Copyright (c) 2026, Easy Digital Downloads, LLC
 * @since       3.6.6
 * @see         https://github.com/awesomemotive/easy-digital-downloads-pro/issues/2214
 */

namespace EDD\Tests\Cart;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * Cart Discounts tests.
 *
 * @group edd_cart
 * @group edd_discounts
 */
class Discounts extends EDD_UnitTestCase {

	/**
	 * Download fixture.
	 *
	 * @var \EDD_Download
	 */
	protected static $download;

	/**
	 * Set up fixtures once.
	 */
	public static function wpSetUpBeforeClass() {
		$post_id = static::factory()->post->create(
			array(
				'post_title'  => 'Test Download for Discounts',
				'post_type'   => 'download',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, 'edd_price', '20.00' );
		update_post_meta( $post_id, '_edd_product_type', 'default' );

		self::$download = edd_get_download( $post_id );
	}

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		edd_empty_cart();
	}

	/**
	 * Tear down after each test.
	 */
	public function tearDown(): void {
		parent::tearDown();
		edd_empty_cart();
	}

	/**
	 * Test that get_discounts returns an array when session has a single discount string.
	 */
	public function test_get_discounts_returns_array_for_single_string() {
		EDD()->session->set( 'cart_discounts', '10off' );

		$discounts = EDD()->cart->get_discounts();

		$this->assertIsArray( $discounts );
		$this->assertContains( '10off', $discounts );
		$this->assertCount( 1, $discounts );
	}

	/**
	 * Test that get_discounts returns an array when session has pipe-delimited discount string.
	 */
	public function test_get_discounts_returns_array_for_pipe_delimited_string() {
		EDD()->session->set( 'cart_discounts', '10off|20off' );

		$discounts = EDD()->cart->get_discounts();

		$this->assertIsArray( $discounts );
		$this->assertContains( '10off', $discounts );
		$this->assertContains( '20off', $discounts );
		$this->assertCount( 2, $discounts );
	}

	/**
	 * Test that get_discounts returns an array when session already has an array.
	 */
	public function test_get_discounts_returns_array_when_already_array() {
		EDD()->session->set( 'cart_discounts', array( '10off', '20off' ) );

		$discounts = EDD()->cart->get_discounts();

		$this->assertIsArray( $discounts );
		$this->assertContains( '10off', $discounts );
		$this->assertContains( '20off', $discounts );
		$this->assertCount( 2, $discounts );
	}

	/**
	 * Test that get_discounts returns empty array when session is empty.
	 */
	public function test_get_discounts_returns_empty_array_when_no_discounts() {
		EDD()->session->set( 'cart_discounts', null );

		$discounts = EDD()->cart->get_discounts();

		$this->assertIsArray( $discounts );
		$this->assertEmpty( $discounts );
	}

	/**
	 * Test that get_discounts returns empty array for empty string.
	 */
	public function test_get_discounts_returns_empty_array_for_empty_string() {
		EDD()->session->set( 'cart_discounts', '' );

		$discounts = EDD()->cart->get_discounts();

		$this->assertIsArray( $discounts );
		$this->assertEmpty( $discounts );
	}

	/**
	 * Test that calling get_discounts multiple times does not cause a fatal error.
	 *
	 * This is the primary regression test for issue #2214 where calling
	 * get_discounts() multiple times caused a fatal error because the first call
	 * converted the string to an array, and the second call tried to explode the array.
	 */
	public function test_get_discounts_called_multiple_times_does_not_fatal() {
		EDD()->session->set( 'cart_discounts', '10off' );

		$discounts_first  = EDD()->cart->get_discounts();
		$discounts_second = EDD()->cart->get_discounts();

		$this->assertIsArray( $discounts_first );
		$this->assertIsArray( $discounts_second );
		$this->assertEquals( $discounts_first, $discounts_second );
	}

	/**
	 * Test that calling get_discounts multiple times with pipe-delimited string works.
	 */
	public function test_get_discounts_called_multiple_times_with_pipe_delimited() {
		EDD()->session->set( 'cart_discounts', '10off|20off|30off' );

		$discounts_first  = EDD()->cart->get_discounts();
		$discounts_second = EDD()->cart->get_discounts();

		$this->assertIsArray( $discounts_first );
		$this->assertIsArray( $discounts_second );
		$this->assertCount( 3, $discounts_first );
		$this->assertCount( 3, $discounts_second );
		$this->assertEquals( $discounts_first, $discounts_second );
	}

	/**
	 * Test that get_discounts_from_session returns an array for a single string.
	 */
	public function test_get_discounts_from_session_returns_array_for_string() {
		EDD()->session->set( 'cart_discounts', '10off' );

		$discounts = EDD()->cart->get_discounts_from_session();

		$this->assertIsArray( $discounts );
		$this->assertContains( '10off', $discounts );
	}

	/**
	 * Test that get_discounts_from_session returns an array for pipe-delimited string.
	 */
	public function test_get_discounts_from_session_returns_array_for_pipe_delimited() {
		EDD()->session->set( 'cart_discounts', '10off|20off' );

		$discounts = EDD()->cart->get_discounts_from_session();

		$this->assertIsArray( $discounts );
		$this->assertContains( '10off', $discounts );
		$this->assertContains( '20off', $discounts );
	}

	/**
	 * Test that get_discounts_from_session returns an array when already an array.
	 */
	public function test_get_discounts_from_session_returns_array_when_already_array() {
		EDD()->session->set( 'cart_discounts', array( '10off' ) );

		$discounts = EDD()->cart->get_discounts_from_session();

		$this->assertIsArray( $discounts );
		$this->assertContains( '10off', $discounts );
	}

	/**
	 * Test that Sessions\Cart::get_discounts normalizes a string to array.
	 */
	public function test_sessions_cart_get_discounts_normalizes_string() {
		EDD()->session->set( 'cart_discounts', '10off' );

		$cart_session = new \EDD\Sessions\Cart( EDD()->cart );
		$cart_session->get_discounts();

		$this->assertIsArray( EDD()->cart->discounts );
		$this->assertContains( '10off', EDD()->cart->discounts );
	}

	/**
	 * Test that Sessions\Cart::get_discounts normalizes a pipe-delimited string.
	 */
	public function test_sessions_cart_get_discounts_normalizes_pipe_string() {
		EDD()->session->set( 'cart_discounts', '10off|20off' );

		$cart_session = new \EDD\Sessions\Cart( EDD()->cart );
		$cart_session->get_discounts();

		$this->assertIsArray( EDD()->cart->discounts );
		$this->assertContains( '10off', EDD()->cart->discounts );
		$this->assertContains( '20off', EDD()->cart->discounts );
		$this->assertCount( 2, EDD()->cart->discounts );
	}

	/**
	 * Test that Sessions\Cart::get_discounts handles null session data.
	 */
	public function test_sessions_cart_get_discounts_handles_null() {
		EDD()->session->set( 'cart_discounts', null );

		$cart_session = new \EDD\Sessions\Cart( EDD()->cart );
		$cart_session->get_discounts();

		$this->assertIsArray( EDD()->cart->discounts );
		$this->assertEmpty( EDD()->cart->discounts );
	}

	/**
	 * Test that has_discounts works correctly after discount normalization.
	 */
	public function test_has_discounts_works_with_string_discount() {
		EDD()->session->set( 'cart_discounts', '10off' );

		// Reset the cached has_discounts value.
		EDD()->cart->has_discounts = null;

		$this->assertTrue( EDD()->cart->has_discounts() );
	}
}
