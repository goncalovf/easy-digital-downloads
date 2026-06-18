<?php
/**
 * PayPal v3 Payments Tests
 *
 * Tests the webhook signature verification, v3 readiness detection, and
 * event subscriptions for the Payments class.
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
 * Tests for PayPal v3 Payments routing.
 *
 * @group gateways
 * @group paypal
 * @group paypal-payments
 */
class PaymentsTest extends EDD_UnitTestCase {

	/**
	 * Clean up PayPal options before and after each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clean_up_options();
	}

	/**
	 * Clean up PayPal options after each test.
	 */
	public function tearDown(): void {
		$this->clean_up_options();
		parent::tearDown();
	}

	/**
	 * Remove all PayPal-related options used by these tests.
	 */
	private function clean_up_options() {
		delete_option( 'edd_paypal_sandbox_commerce_version' );
		delete_option( 'edd_paypal_live_commerce_version' );
		delete_option( 'edd_paypal_sandbox_store_id' );
		delete_option( 'edd_paypal_live_store_id' );
		delete_option( 'edd_paypal_sandbox_hmac_key' );
		delete_option( 'edd_paypal_live_hmac_key' );
		delete_option( 'edd_paypal_sandbox_merchant_id' );
		delete_option( 'edd_paypal_live_merchant_id' );
	}

	// -----------------------------------------------------------------------
	// Webhook Signature Verification Tests
	// -----------------------------------------------------------------------

	/**
	 * Valid proxy webhook signature should be verified successfully.
	 */
	public function test_verify_connect_webhook_signature_valid() {
		$hmac_key    = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$timestamp   = (string) time();
		$webhook_id  = 'WH-TEST-001';
		$body        = '{"event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAP-001"}}';
		$body_hash   = hash( 'sha256', $body );

		$message   = sprintf(
			'%s.%s.POST./wp-json/edd/webhooks/v1/paypal.%s',
			$timestamp,
			$webhook_id,
			$body_hash
		);
		$signature = hash_hmac( 'sha256', $message, $hmac_key );

		$headers = array(
			'HTTP_X_EDD_TIMESTAMP'       => $timestamp,
			'HTTP_X_EDD_WEBHOOK_ID'      => $webhook_id,
			'HTTP_X_EDD_PROXY_SIGNATURE' => $signature,
		);

		$this->assertTrue( Payments::verify_connect_webhook_signature( $body, $headers, $hmac_key ) );
	}

	/**
	 * Invalid signature should fail verification.
	 */
	public function test_verify_connect_webhook_signature_invalid() {
		$hmac_key = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

		$headers = array(
			'HTTP_X_EDD_TIMESTAMP'       => (string) time(),
			'HTTP_X_EDD_WEBHOOK_ID'      => 'WH-TEST-002',
			'HTTP_X_EDD_PROXY_SIGNATURE' => 'invalid_signature_here',
		);

		$this->assertFalse( Payments::verify_connect_webhook_signature( '{}', $headers, $hmac_key ) );
	}

	/**
	 * Missing headers should fail verification.
	 */
	public function test_verify_connect_webhook_signature_missing_headers() {
		$hmac_key = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

		// Missing timestamp.
		$this->assertFalse(
			Payments::verify_connect_webhook_signature(
				'{}',
				array(
					'HTTP_X_EDD_WEBHOOK_ID'      => 'WH-001',
					'HTTP_X_EDD_PROXY_SIGNATURE' => 'sig',
				),
				$hmac_key
			)
		);

		// Missing webhook ID.
		$this->assertFalse(
			Payments::verify_connect_webhook_signature(
				'{}',
				array(
					'HTTP_X_EDD_TIMESTAMP'       => (string) time(),
					'HTTP_X_EDD_PROXY_SIGNATURE' => 'sig',
				),
				$hmac_key
			)
		);

		// Missing signature.
		$this->assertFalse(
			Payments::verify_connect_webhook_signature(
				'{}',
				array(
					'HTTP_X_EDD_TIMESTAMP'  => (string) time(),
					'HTTP_X_EDD_WEBHOOK_ID' => 'WH-001',
				),
				$hmac_key
			)
		);
	}

	/**
	 * Stale timestamp should fail verification.
	 */
	public function test_verify_connect_webhook_signature_stale_timestamp() {
		$hmac_key    = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$timestamp   = (string) ( time() - 600 ); // 10 minutes ago.
		$webhook_id  = 'WH-STALE-001';
		$body        = '{}';
		$body_hash   = hash( 'sha256', $body );

		$message   = sprintf(
			'%s.%s.POST./wp-json/edd/webhooks/v1/paypal.%s',
			$timestamp,
			$webhook_id,
			$body_hash
		);
		$signature = hash_hmac( 'sha256', $message, $hmac_key );

		$headers = array(
			'HTTP_X_EDD_TIMESTAMP'       => $timestamp,
			'HTTP_X_EDD_WEBHOOK_ID'      => $webhook_id,
			'HTTP_X_EDD_PROXY_SIGNATURE' => $signature,
		);

		$this->assertFalse( Payments::verify_connect_webhook_signature( $body, $headers, $hmac_key ) );
	}

	/**
	 * Different body should produce different signature and fail.
	 */
	public function test_verify_connect_webhook_signature_body_tampering() {
		$hmac_key    = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$timestamp   = (string) time();
		$webhook_id  = 'WH-TAMPER-001';
		$original    = '{"amount":"100.00"}';
		$tampered    = '{"amount":"999.99"}';

		$body_hash = hash( 'sha256', $original );
		$message   = sprintf(
			'%s.%s.POST./wp-json/edd/webhooks/v1/paypal.%s',
			$timestamp,
			$webhook_id,
			$body_hash
		);
		$signature = hash_hmac( 'sha256', $message, $hmac_key );

		$headers = array(
			'HTTP_X_EDD_TIMESTAMP'       => $timestamp,
			'HTTP_X_EDD_WEBHOOK_ID'      => $webhook_id,
			'HTTP_X_EDD_PROXY_SIGNATURE' => $signature,
		);

		// Verify with tampered body should fail.
		$this->assertFalse( Payments::verify_connect_webhook_signature( $tampered, $headers, $hmac_key ) );

		// Verify with original body should pass.
		$this->assertTrue( Payments::verify_connect_webhook_signature( $original, $headers, $hmac_key ) );
	}

	// -----------------------------------------------------------------------
	// v3 Readiness Tests
	// -----------------------------------------------------------------------

	/**
	 * v3 ready should require store_id, hmac_key, and merchant_id.
	 */
	public function test_is_v3_ready_all_credentials() {
		add_filter( 'edd_is_test_mode', '__return_true' );
		update_option( 'edd_paypal_sandbox_commerce_version', 'v3' );
		update_option( 'edd_paypal_sandbox_store_id', 'store-uuid-001' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );
		update_option( 'edd_paypal_sandbox_merchant_id', 'MERCHANT-001' );

		$this->assertTrue( Payments::is_v3_ready() );

		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * v3 ready should return false when store_id is missing.
	 */
	public function test_is_v3_ready_missing_store_id() {
		add_filter( 'edd_is_test_mode', '__return_true' );
		update_option( 'edd_paypal_sandbox_commerce_version', 'v3' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );
		update_option( 'edd_paypal_sandbox_merchant_id', 'MERCHANT-001' );

		$this->assertFalse( Payments::is_v3_ready() );

		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * v3 ready should return false for v2 stores.
	 */
	public function test_is_v3_ready_returns_false_for_v2() {
		add_filter( 'edd_is_test_mode', '__return_true' );
		update_option( 'edd_paypal_sandbox_commerce_version', 'v2' );
		update_option( 'edd_paypal_sandbox_store_id', 'store-uuid-001' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );
		update_option( 'edd_paypal_sandbox_merchant_id', 'MERCHANT-001' );

		$this->assertFalse( Payments::is_v3_ready() );

		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	// -----------------------------------------------------------------------
	// SubscriberInterface Tests
	// -----------------------------------------------------------------------

	/**
	 * Payments should implement SubscriberInterface.
	 */
	public function test_implements_subscriber_interface() {
		$payments = new Payments();
		$this->assertInstanceOf( \EDD\EventManagement\SubscriberInterface::class, $payments );
	}

	/**
	 * get_subscribed_events should register the label and receipt hooks, and
	 * no longer register the SDK arg/data-attribute filters (those values are
	 * owned by register_js()/add_data_attributes() in the base loader).
	 */
	public function test_subscribed_events() {
		$events = Payments::get_subscribed_events();

		$this->assertArrayHasKey( 'edd_gateway_checkout_label', $events );
		$this->assertArrayHasKey( 'edd_gateway_admin_label', $events );
		$this->assertArrayNotHasKey( 'edd_paypal_js_sdk_query_args', $events );
		$this->assertArrayNotHasKey( 'edd_paypal_js_sdk_data_attributes', $events );
	}
}
