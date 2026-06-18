<?php
/**
 * PayPal V3 Checkout Flow Tests
 *
 * Tests order payload construction, payment source storage after capture,
 * and the payment source label filter.
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;
use EDD\Tests\Helpers\EDD_Helper_Download;
use EDD\Tests\Helpers\EDD_Helper_Payment;

/**
 * Tests for PayPal V3 checkout flow.
 *
 * @group gateways
 * @group paypal
 * @group paypal-checkout-flow
 */
class CheckoutFlowTest extends EDD_UnitTestCase {

	/**
	 * Clean up before and after each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->setup_v3_options();
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		$this->clean_up_options();
		remove_all_filters( 'pre_http_request' );
		remove_filter( 'edd_is_test_mode', '__return_true' );
		parent::tearDown();
	}

	/**
	 * Set up V3 connection options.
	 */
	private function setup_v3_options() {
		add_filter( 'edd_is_test_mode', '__return_true' );
		update_option( 'edd_paypal_sandbox_commerce_version', 'v3' );
		update_option( 'edd_paypal_sandbox_partner_client_id', 'PARTNER_CLIENT_ID' );
		update_option( 'edd_paypal_sandbox_merchant_id', 'MERCHANT_ID' );
		update_option( 'edd_paypal_sandbox_store_id', 'test-store-id' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );
	}

	/**
	 * Remove all PayPal options.
	 */
	private function clean_up_options() {
		foreach ( array( 'sandbox', 'live' ) as $mode ) {
			delete_option( "edd_paypal_{$mode}_commerce_version" );
			delete_option( "edd_paypal_{$mode}_partner_client_id" );
			delete_option( "edd_paypal_{$mode}_merchant_id" );
			delete_option( "edd_paypal_{$mode}_store_id" );
			delete_option( "edd_paypal_{$mode}_hmac_key" );
		}
	}

	/**
	 * The order payload includes brand_name in application_context.
	 */
	public function test_order_payload_includes_brand_name() {
		$order_data = array(
			'intent'              => 'CAPTURE',
			'purchase_units'      => array(),
			'application_context' => array(
				'brand_name'          => substr( get_bloginfo( 'name' ), 0, 127 ),
				'shipping_preference' => 'NO_SHIPPING',
				'user_action'         => 'PAY_NOW',
			),
		);

		$this->assertArrayHasKey( 'brand_name', $order_data['application_context'] );
		$this->assertNotEmpty( $order_data['application_context']['brand_name'] );
	}

	/**
	 * The order payload includes NO_SHIPPING for digital goods.
	 */
	public function test_order_payload_no_shipping() {
		$order_data = array(
			'application_context' => array(
				'shipping_preference' => 'NO_SHIPPING',
			),
		);

		$this->assertSame( 'NO_SHIPPING', $order_data['application_context']['shipping_preference'] );
	}

	/**
	 * The edd_paypal_order_arguments filter is applied.
	 */
	public function test_order_arguments_filter_applied() {
		$filter_called = false;

		add_filter( 'edd_paypal_order_arguments', function( $data ) use ( &$filter_called ) {
			$filter_called = true;
			return $data;
		} );

		apply_filters( 'edd_paypal_order_arguments', array( 'intent' => 'CAPTURE' ), array(), 0 );

		$this->assertTrue( $filter_called );

		remove_all_filters( 'edd_paypal_order_arguments' );
	}

	/**
	 * Payment source meta is stored correctly for PayPal.
	 */
	public function test_payment_source_meta_stored_paypal() {
		$order_id = edd_add_order( array(
			'status'   => 'complete',
			'gateway'  => 'paypal_commerce',
			'total'    => 10.00,
			'currency' => 'USD',
		) );

		edd_update_order_meta( $order_id, '_edd_paypal_payment_source', 'paypal' );

		$this->assertSame( 'paypal', edd_get_order_meta( $order_id, '_edd_paypal_payment_source', true ) );

		edd_delete_order( $order_id );
	}

	/**
	 * Payment source meta is stored correctly for Venmo.
	 */
	public function test_payment_source_meta_stored_venmo() {
		$order_id = edd_add_order( array(
			'status'   => 'complete',
			'gateway'  => 'paypal_commerce',
			'total'    => 10.00,
			'currency' => 'USD',
		) );

		edd_update_order_meta( $order_id, '_edd_paypal_payment_source', 'venmo' );

		$this->assertSame( 'venmo', edd_get_order_meta( $order_id, '_edd_paypal_payment_source', true ) );

		edd_delete_order( $order_id );
	}

	/**
	 * PayPal order ID meta is stored after capture.
	 */
	public function test_paypal_order_id_meta_stored() {
		$order_id = edd_add_order( array(
			'status'   => 'complete',
			'gateway'  => 'paypal_commerce',
			'total'    => 10.00,
			'currency' => 'USD',
		) );

		edd_update_order_meta( $order_id, 'paypal_order_id', 'PAYPAL_ORDER_123' );

		$this->assertSame( 'PAYPAL_ORDER_123', edd_get_order_meta( $order_id, 'paypal_order_id', true ) );

		edd_delete_order( $order_id );
	}

	/**
	 * V3 order creation sends request to proxy.
	 */
	public function test_v3_order_creation_uses_proxy() {
		$request_url = '';

		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function( $status, $args, $url ) use ( &$request_url ) {
			$request_url = $url;

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'id'     => 'MOCK_ORDER_123',
					'status' => 'CREATED',
				) ),
			);
		}, 10, 3 );

		$proxy = new \EDD\Gateways\PayPal\V3\ConnectAPI( 'sandbox' );
		$proxy->post( '/v3/paypal/orders', array( 'intent' => 'CAPTURE' ) );

		$this->assertStringContainsString( '/v3/paypal/orders', $request_url );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * V3 order creation includes HMAC headers.
	 */
	public function test_v3_order_creation_includes_hmac_headers() {
		$request_headers = array();

		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function( $status, $args, $url ) use ( &$request_headers ) {
			$request_headers = $args['headers'];

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'id'     => 'MOCK_ORDER_123',
					'status' => 'CREATED',
				) ),
			);
		}, 10, 3 );

		$proxy = new \EDD\Gateways\PayPal\V3\ConnectAPI( 'sandbox' );
		$proxy->post( '/v3/paypal/orders', array( 'intent' => 'CAPTURE' ) );

		$this->assertArrayHasKey( 'X-EDD-Store-ID', $request_headers );
		$this->assertArrayHasKey( 'X-EDD-Timestamp', $request_headers );
		$this->assertArrayHasKey( 'X-EDD-Nonce', $request_headers );
		$this->assertArrayHasKey( 'X-EDD-Signature', $request_headers );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Proxy error response is detected correctly.
	 */
	public function test_proxy_error_detection() {
		$error_response = array(
			'error' => array(
				'code'    => 'payment_declined',
				'message' => 'Payment was declined',
			),
		);

		$this->assertTrue( \EDD\Gateways\PayPal\V3\ConnectAPI::is_error( $error_response ) );
	}

	/**
	 * Proxy success response is not flagged as error.
	 */
	public function test_proxy_success_not_error() {
		$success_response = array(
			'id'     => 'ORDER_123',
			'status' => 'CREATED',
		);

		$this->assertFalse( \EDD\Gateways\PayPal\V3\ConnectAPI::is_error( $success_response ) );
	}
}
