<?php
/**
 * PayPal V3 Payments Class Method Tests
 *
 * Tests the v3-specific methods on the Payments class: order creation,
 * capture, and refund via the proxy, plus v3 readiness detection.
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;
use EDD\Gateways\PayPal\Payments;
use EDD\Gateways\PayPal\CommerceVersion;

/**
 * Tests for Payments class v3 methods.
 *
 * @group gateways
 * @group paypal
 * @group paypal-v3-payments
 */
class V3PaymentsTest extends EDD_UnitTestCase {

	/**
	 * Set up V3 options.
	 */
	public function setUp(): void {
		parent::setUp();
		add_filter( 'edd_is_test_mode', '__return_true' );
		update_option( 'edd_paypal_sandbox_commerce_version', 'v3' );
		update_option( 'edd_paypal_sandbox_store_id', 'test-store-id' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );
		update_option( 'edd_paypal_sandbox_merchant_id', 'MERCHANT_ID' );
	}

	/**
	 * Clean up.
	 */
	public function tearDown(): void {
		foreach ( array( 'sandbox', 'live' ) as $mode ) {
			delete_option( "edd_paypal_{$mode}_commerce_version" );
			delete_option( "edd_paypal_{$mode}_store_id" );
			delete_option( "edd_paypal_{$mode}_hmac_key" );
			delete_option( "edd_paypal_{$mode}_merchant_id" );
		}
		remove_all_filters( 'pre_http_request' );
		remove_filter( 'edd_is_test_mode', '__return_true' );
		parent::tearDown();
	}

	/**
	 * create_order returns order data on success.
	 */
	public function test_create_order_success() {
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'id'     => 'ORDER_123',
					'status' => 'CREATED',
					'links'  => array(),
				) ),
			);
		} );

		$result = Payments::create_order(
			array( 'intent' => 'CAPTURE', 'purchase_units' => array() ),
			1
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'ORDER_123', $result['id'] );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * create_order sets EDD error on failure.
	 */
	public function test_create_order_failure_sets_error() {
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'error' => array(
						'code'    => 'payment_declined',
						'message' => 'Payment was declined',
					),
				) ),
			);
		} );

		$result = Payments::create_order(
			array( 'intent' => 'CAPTURE', 'purchase_units' => array() ),
			1
		);

		$errors = edd_get_errors();
		$this->assertNotEmpty( $errors );

		// Clean up EDD errors.
		edd_clear_errors();
		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * capture_order returns capture data on success.
	 */
	public function test_capture_order_success() {
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'id'     => 'ORDER_123',
					'status' => 'COMPLETED',
					'purchase_units' => array(
						array(
							'payments' => array(
								'captures' => array(
									array( 'id' => 'CAP_123', 'status' => 'COMPLETED' ),
								),
							),
						),
					),
				) ),
			);
		} );

		$result = Payments::capture_order( 'ORDER_123' );

		$this->assertIsArray( $result );
		$this->assertSame( 'COMPLETED', $result['status'] );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * capture_order returns error response on failure.
	 */
	public function test_capture_order_failure() {
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'error' => array(
						'code'    => 'paypal_error',
						'message' => 'Capture failed',
					),
				) ),
			);
		} );

		$result = Payments::capture_order( 'ORDER_123' );

		$this->assertArrayHasKey( 'error', $result );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * is_v3_ready returns true when all credentials are set.
	 */
	public function test_is_v3_ready_with_credentials() {
		$this->assertTrue( Payments::is_v3_ready() );
	}

	/**
	 * is_v3_ready returns false when store_id is missing.
	 */
	public function test_is_v3_ready_missing_store_id() {
		delete_option( 'edd_paypal_sandbox_store_id' );

		$this->assertFalse( Payments::is_v3_ready() );
	}

	/**
	 * is_v3_ready returns false when hmac_key is missing.
	 */
	public function test_is_v3_ready_missing_hmac_key() {
		delete_option( 'edd_paypal_sandbox_hmac_key' );

		$this->assertFalse( Payments::is_v3_ready() );
	}

	/**
	 * is_v3_ready returns false when merchant_id is missing.
	 */
	public function test_is_v3_ready_missing_merchant_id() {
		delete_option( 'edd_paypal_sandbox_merchant_id' );

		$this->assertFalse( Payments::is_v3_ready() );
	}

	/**
	 * is_v3_ready returns false for v2 stores.
	 */
	public function test_is_v3_ready_false_for_v2() {
		update_option( 'edd_paypal_sandbox_commerce_version', 'v2' );

		$this->assertFalse( Payments::is_v3_ready() );
	}
}
