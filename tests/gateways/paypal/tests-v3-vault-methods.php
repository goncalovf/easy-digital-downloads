<?php
/**
 * PayPal V3 Vault Method Tests
 *
 * Tests vault setup token creation, payment token creation, and token
 * deletion via the proxy, complementing the existing DB-level vault tests.
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;
use EDD\Gateways\PayPal\V3\Vault;
use EDD\Gateways\PayPal\V3\ConnectAPI;

/**
 * Tests for Vault class proxy methods.
 *
 * @group gateways
 * @group paypal
 * @group paypal-v3-vault-methods
 */
class V3VaultMethodsTest extends EDD_UnitTestCase {

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
	 * create_setup_token sends correct payload to proxy.
	 */
	public function test_create_setup_token_sends_payload() {
		$captured_body = '';

		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function( $status, $args, $url ) use ( &$captured_body ) {
			$captured_body = $args['body'];

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'id'     => 'SETUP_TOKEN_123',
					'status' => 'CREATED',
				) ),
			);
		}, 10, 3 );

		$result = Vault::create_setup_token( 1, 'https://example.com/return', 'https://example.com/cancel' );

		$decoded = json_decode( $captured_body, true );

		$this->assertIsArray( $result );
		$this->assertSame( 'SETUP_TOKEN_123', $result['id'] );
		$this->assertArrayHasKey( 'payment_source', $decoded );
		$this->assertSame( 'MERCHANT', $decoded['payment_source']['paypal']['usage_type'] );
		$this->assertSame( 'CONSUMER', $decoded['payment_source']['paypal']['customer_type'] );
		$this->assertSame( 'SUBSCRIPTION_PREPAID', $decoded['payment_source']['paypal']['usage_pattern'] );
		$this->assertSame( 'NO_SHIPPING', $decoded['payment_source']['paypal']['experience_context']['shipping_preference'] );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * create_setup_token returns false on proxy error.
	 */
	public function test_create_setup_token_failure() {
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'error' => array(
						'code'    => 'paypal_error',
						'message' => 'Setup token failed',
					),
				) ),
			);
		} );

		$result = Vault::create_setup_token( 1, 'https://example.com/return', 'https://example.com/cancel' );

		$this->assertFalse( $result );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * create_setup_token includes customer ID when available.
	 */
	public function test_create_setup_token_includes_customer_id() {
		$captured_body = '';

		// Create a customer and set their PayPal vault customer ID.
		$customer_id = edd_add_customer( array(
			'email' => 'vault-test@example.com',
			'name'  => 'Vault Test',
		) );
		edd_update_customer_meta( $customer_id, '_edd_paypal_vault_customer_id_test', 'PP-CUSTOMER-123' );

		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function( $status, $args, $url ) use ( &$captured_body ) {
			$captured_body = $args['body'];

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'id' => 'SETUP_TOKEN_456',
				) ),
			);
		}, 10, 3 );

		Vault::create_setup_token( $customer_id, 'https://example.com/return', 'https://example.com/cancel' );

		$decoded = json_decode( $captured_body, true );
		$this->assertArrayHasKey( 'customer', $decoded );
		$this->assertSame( 'PP-CUSTOMER-123', $decoded['customer']['id'] );

		// Clean up.
		edd_delete_customer( $customer_id );
		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * create_payment_token sends correct payload.
	 */
	public function test_create_payment_token_sends_payload() {
		$captured_body = '';

		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function( $status, $args, $url ) use ( &$captured_body ) {
			$captured_body = $args['body'];

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'id'             => 'PT_123',
					'customer'       => array( 'id' => 'CUST_123' ),
					'payment_source' => array(
						'paypal' => array(
							'email_address' => 'buyer@example.com',
						),
					),
				) ),
			);
		}, 10, 3 );

		$result = Vault::create_payment_token( 'SETUP_TOKEN_123', 1 );

		$this->assertIsArray( $result );
		$this->assertSame( 'PT_123', $result['id'] );

		$decoded = json_decode( $captured_body, true );
		$this->assertArrayHasKey( 'payment_source', $decoded );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * create_payment_token returns false on error.
	 */
	public function test_create_payment_token_failure() {
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'error' => array(
						'code'    => 'token_not_found',
						'message' => 'Setup token not found',
					),
				) ),
			);
		} );

		$result = Vault::create_payment_token( 'INVALID_TOKEN', 1 );

		$this->assertFalse( $result );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * charge_vault sends correct payload with stored_credential.
	 */
	public function test_charge_vault_sends_stored_credential() {
		$captured_body = '';
		$captured_url  = '';

		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function( $status, $args, $url ) use ( &$captured_body, &$captured_url ) {
			$captured_body = $args['body'];
			$captured_url  = $url;

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'id'         => 'ORDER_123',
					'status'     => 'COMPLETED',
					'capture_id' => 'CAP_123',
				) ),
			);
		}, 10, 3 );

		$result = Vault::charge_vault( array(
			'vault_id'                       => 'VT_123',
			'amount'                         => '9.99',
			'currency_code'                  => 'USD',
			'custom_id'                      => 'edd_sub_5',
			'description'                    => 'Subscription renewal #5',
			'previous_transaction_reference' => 'CAP_PREV',
		) );

		$this->assertIsArray( $result );
		$this->assertStringContainsString( '/v3/paypal/orders/recurring', $captured_url );

		$decoded = json_decode( $captured_body, true );
		$this->assertSame( 'VT_123', $decoded['vault_id'] );
		$this->assertSame( '9.99', $decoded['amount'] );
		$this->assertSame( 'MERCHANT', $decoded['stored_credential']['payment_initiator'] );
		$this->assertSame( 'RECURRING', $decoded['stored_credential']['payment_type'] );
		$this->assertSame( 'CAP_PREV', $decoded['stored_credential']['previous_transaction_reference'] );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * charge_vault returns a WP_Error when vault_id is missing.
	 */
	public function test_charge_vault_requires_vault_id() {
		$result = Vault::charge_vault( array(
			'amount'        => '9.99',
			'currency_code' => 'USD',
		) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_arguments', $result->get_error_code() );
	}

	/**
	 * charge_vault returns a WP_Error when amount is missing.
	 */
	public function test_charge_vault_requires_amount() {
		$result = Vault::charge_vault( array(
			'vault_id'      => 'VT_123',
			'currency_code' => 'USD',
		) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_arguments', $result->get_error_code() );
	}

	/**
	 * charge_vault returns a WP_Error carrying the proxy error on failure.
	 */
	public function test_charge_vault_failure() {
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'error' => array(
						'code'    => 'payment_declined',
						'message' => 'Declined',
					),
				) ),
			);
		} );

		$result = Vault::charge_vault( array(
			'vault_id'      => 'VT_123',
			'amount'        => '9.99',
			'currency_code' => 'USD',
		) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'payment_declined', $result->get_error_code() );
		$this->assertSame( 'Declined', $result->get_error_message() );

		remove_all_filters( 'pre_http_request' );
	}
}
