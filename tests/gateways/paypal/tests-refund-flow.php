<?php
/**
 * PayPal V3 Refund Flow Tests
 *
 * Tests refund payload construction, insufficient balance detection,
 * and error handling through the proxy.
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Tests\Helpers;
use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * Tests for PayPal V3 refund flow.
 *
 * @group gateways
 * @group paypal
 * @group paypal-refund-flow
 */
class RefundFlowTest extends EDD_UnitTestCase {

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
		update_option( 'edd_paypal_sandbox_store_id', 'test-store-id' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );
		update_option( 'edd_paypal_sandbox_merchant_id', 'MERCHANT_ID' );
	}

	/**
	 * Remove all PayPal options.
	 */
	private function clean_up_options() {
		foreach ( array( 'sandbox', 'live' ) as $mode ) {
			delete_option( "edd_paypal_{$mode}_commerce_version" );
			delete_option( "edd_paypal_{$mode}_store_id" );
			delete_option( "edd_paypal_{$mode}_hmac_key" );
			delete_option( "edd_paypal_{$mode}_merchant_id" );
		}
	}

	/**
	 * V3 refund sends request to the proxy refund endpoint.
	 */
	public function test_v3_refund_uses_proxy_endpoint() {
		$request_url = '';

		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function( $status, $args, $url ) use ( &$request_url ) {
			$request_url = $url;

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'id'     => 'REFUND_123',
					'status' => 'COMPLETED',
					'amount' => array( 'value' => '10.00', 'currency_code' => 'USD' ),
				) ),
			);
		}, 10, 3 );

		$proxy = new \EDD\Gateways\PayPal\V3\ConnectAPI( 'sandbox' );
		$proxy->post( '/v3/paypal/orders/ORDER_123/refund', array(
			'capture_id' => 'CAP_123',
		) );

		$this->assertStringContainsString( '/v3/paypal/orders/ORDER_123/refund', $request_url );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Partial refund includes amount in the request body.
	 */
	public function test_partial_refund_includes_amount() {
		$request_body = '';

		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function( $status, $args, $url ) use ( &$request_body ) {
			$request_body = $args['body'];

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'id'     => 'REFUND_123',
					'status' => 'COMPLETED',
					'amount' => array( 'value' => '25.00', 'currency_code' => 'USD' ),
				) ),
			);
		}, 10, 3 );

		$proxy = new \EDD\Gateways\PayPal\V3\ConnectAPI( 'sandbox' );
		$proxy->post( '/v3/paypal/orders/ORDER_123/refund', array(
			'capture_id' => 'CAP_123',
			'amount'     => array(
				'value'         => '25.00',
				'currency_code' => 'USD',
			),
		) );

		$decoded = json_decode( $request_body, true );
		$this->assertArrayHasKey( 'amount', $decoded );
		$this->assertSame( '25.00', $decoded['amount']['value'] );
		$this->assertSame( 'USD', $decoded['amount']['currency_code'] );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * INSUFFICIENT_FUNDS error is detected from proxy response.
	 */
	public function test_insufficient_funds_error_detected() {
		$error_response = array(
			'error' => array(
				'code'    => 'paypal_validation_error',
				'message' => 'PayPal rejected the request',
				'details' => array(
					'details' => array(
						array(
							'issue'       => 'INSUFFICIENT_FUNDS',
							'description' => 'The seller does not have enough funds to complete this transaction.',
						),
					),
				),
			),
		);

		$this->assertTrue( \EDD\Gateways\PayPal\V3\ConnectAPI::is_error( $error_response ) );

		// Verify the error details contain the INSUFFICIENT_FUNDS issue.
		$details = $error_response['error']['details']['details'];
		$found   = false;
		foreach ( $details as $detail ) {
			if ( 'INSUFFICIENT_FUNDS' === $detail['issue'] ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found );
	}

	/**
	 * TRANSACTION_REFUSED error is detected from proxy response.
	 */
	public function test_transaction_refused_error_detected() {
		$error_response = array(
			'error' => array(
				'code'    => 'paypal_validation_error',
				'message' => 'PayPal rejected the request',
				'details' => array(
					'details' => array(
						array(
							'issue'       => 'TRANSACTION_REFUSED',
							'description' => 'The transaction was refused.',
						),
					),
				),
			),
		);

		$this->assertTrue( \EDD\Gateways\PayPal\V3\ConnectAPI::is_error( $error_response ) );
	}

	/**
	 * Generic proxy error is detected.
	 */
	public function test_generic_proxy_error() {
		$error_response = array(
			'error' => array(
				'code'    => 'paypal_error',
				'message' => 'PayPal is temporarily unavailable.',
			),
		);

		$this->assertTrue( \EDD\Gateways\PayPal\V3\ConnectAPI::is_error( $error_response ) );
		$this->assertSame( 'paypal_error', \EDD\Gateways\PayPal\V3\ConnectAPI::get_error_code( $error_response ) );
		$this->assertSame( 'PayPal is temporarily unavailable.', \EDD\Gateways\PayPal\V3\ConnectAPI::get_error_message( $error_response ) );
	}

	/**
	 * WP_Error is detected as a proxy error.
	 */
	public function test_wp_error_is_proxy_error() {
		$error = new \WP_Error( 'http_request_failed', 'Connection timed out' );

		$this->assertTrue( \EDD\Gateways\PayPal\V3\ConnectAPI::is_error( $error ) );
	}

	// -------------------------------------------------------------------------
	// Legacy v2 → v3 refund routing tests.
	// -------------------------------------------------------------------------

	/**
	 * A v2 order (no paypal_order_id meta) on a v3 store routes to the
	 * captures refund endpoint, not the orders endpoint.
	 */
	public function test_v2_order_on_v3_store_routes_to_captures_endpoint() {
		$order_id = Helpers\EDD_Helper_Payment::create_simple_payment(
			array( 'gateway' => 'paypal_commerce' )
		);
		edd_update_order( $order_id, array( 'gateway' => 'paypal_commerce' ) );
		// Intentionally no paypal_order_id meta — this is a v2 order.

		$order        = edd_get_order( $order_id );
		$captured_url = '';

		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function( $status, $args, $url ) use ( &$captured_url ) {
			$captured_url = $url;
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'id'     => 'REFUND_V2_001',
					'status' => 'COMPLETED',
					'amount' => array( 'value' => '20.00', 'currency_code' => 'USD' ),
				) ),
			);
		}, 10, 3 );

		\EDD\Gateways\PayPal\refund_transaction( $order );

		$this->assertStringContainsString( '/v3/paypal/captures/', $captured_url );
		$this->assertStringContainsString( '/refund', $captured_url );
		$this->assertStringNotContainsString( '/v3/paypal/orders/', $captured_url );
	}

	/**
	 * A v3 order (has paypal_order_id meta) still routes to the orders endpoint.
	 *
	 * Regression guard: the v3 path must not change after the v2-legacy routing was added.
	 */
	public function test_v3_order_continues_to_use_orders_endpoint() {
		$order_id = Helpers\EDD_Helper_Payment::create_simple_payment(
			array( 'gateway' => 'paypal_commerce' )
		);
		edd_update_order( $order_id, array( 'gateway' => 'paypal_commerce' ) );
		edd_add_order_transaction( array(
			'object_id'      => $order_id,
			'object_type'    => 'order',
			'transaction_id' => 'CAP_V3_001',
			'gateway'        => 'paypal_commerce',
			'status'         => 'complete',
			'total'          => 20.00,
		) );
		edd_update_order_meta( $order_id, 'paypal_order_id', 'PPORDER_V3_001' );

		$order        = edd_get_order( $order_id );
		$captured_url = '';

		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function( $status, $args, $url ) use ( &$captured_url ) {
			$captured_url = $url;
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'id'     => 'REFUND_V3_001',
					'status' => 'COMPLETED',
					'amount' => array( 'value' => '20.00', 'currency_code' => 'USD' ),
				) ),
			);
		}, 10, 3 );

		\EDD\Gateways\PayPal\refund_transaction( $order );

		$this->assertStringContainsString( '/v3/paypal/orders/PPORDER_V3_001/refund', $captured_url );
		$this->assertStringNotContainsString( '/captures/', $captured_url );
	}

	/**
	 * A v2 order on a v2 store (not v3-onboarded) takes the legacy API path,
	 * which throws Authentication_Exception when no v2 credentials are configured.
	 */
	public function test_v2_order_on_v2_store_throws_without_credentials() {
		// Clear v3 options to simulate a pure v2 store.
		$this->clean_up_options();

		$order_id = Helpers\EDD_Helper_Payment::create_simple_payment(
			array( 'gateway' => 'paypal_commerce' )
		);
		edd_update_order( $order_id, array( 'gateway' => 'paypal_commerce' ) );
		edd_add_order_transaction( array(
			'object_id'      => $order_id,
			'object_type'    => 'order',
			'transaction_id' => 'TXN_V2_V2STORE',
			'gateway'        => 'paypal_commerce',
			'status'         => 'complete',
			'total'          => 20.00,
		) );

		$order = edd_get_order( $order_id );

		$this->expectException( \EDD\Gateways\PayPal\Exceptions\Authentication_Exception::class );

		\EDD\Gateways\PayPal\refund_transaction( $order );
	}

	/**
	 * A cached pre-flight response is consumed by refund_transaction() for a v2
	 * order on a v3 store, avoiding a second round-trip to the proxy.
	 */
	public function test_preflighted_response_is_reused_for_v2_order() {
		$order_id = Helpers\EDD_Helper_Payment::create_simple_payment(
			array( 'gateway' => 'paypal_commerce' )
		);
		edd_update_order( $order_id, array( 'gateway' => 'paypal_commerce' ) );
		// No paypal_order_id meta — v2 order. Use the actual transaction ID the
		// helper stored so the transient key matches what refund_transaction() computes.
		$order          = edd_get_order( $order_id );
		$transaction_id = $order->get_transaction_id();

		$cached_response = array(
			'id'     => 'REFUND_PREFLIGHTED',
			'status' => 'COMPLETED',
			'amount' => array( 'value' => '20.00', 'currency_code' => 'USD' ),
		);
		set_transient(
			\EDD\Gateways\PayPal\preflight_cache_key( $transaction_id ),
			$cached_response,
			MINUTE_IN_SECONDS
		);

		$http_request_made = false;
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function( $status, $args, $url ) use ( &$http_request_made ) {
			$http_request_made = true;
			// Return a mock to prevent actual network call if the cache is missed.
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array( 'id' => 'REFUND_FALLBACK', 'status' => 'COMPLETED' ) ),
			);
		}, 10, 3 );

		\EDD\Gateways\PayPal\refund_transaction( $order );

		$this->assertFalse( $http_request_made, 'refund_transaction() should reuse the pre-flighted response, not make a new HTTP request.' );
		$this->assertFalse( get_transient( \EDD\Gateways\PayPal\preflight_cache_key( $transaction_id ) ), 'Pre-flight transient should be deleted after use.' );
	}
}
