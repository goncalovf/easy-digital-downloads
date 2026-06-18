<?php
/**
 * PayPal V3 Webhook Flow Tests
 *
 * Tests webhook signature verification, timestamp validation, and
 * proxy webhook detection.
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;
use EDD\Gateways\PayPal\Payments;
use EDD\Gateways\PayPal\V3\Credentials;
use EDD\Gateways\PayPal\V3\Onboarding;

/**
 * Tests for PayPal V3 webhook flow.
 *
 * @group gateways
 * @group paypal
 * @group paypal-webhook-flow
 */
class WebhookFlowTest extends EDD_UnitTestCase {

	/**
	 * The HMAC key used for test signatures.
	 *
	 * @var string
	 */
	private $hmac_key = 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890';

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		foreach ( array( 'sandbox', 'live' ) as $mode ) {
			delete_option( "edd_paypal_{$mode}_hmac_key" );
			delete_option( "edd_paypal_{$mode}_commerce_version" );
		}
		remove_filter( 'edd_is_test_mode', '__return_true' );
		parent::tearDown();
	}

	/**
	 * Valid HMAC signature passes verification.
	 */
	public function test_valid_signature_passes() {
		$timestamp  = (string) time();
		$webhook_id = 'WH-TEST-123';
		$body       = '{"event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAP123"}}';
		$body_hash  = hash( 'sha256', $body );

		$message   = sprintf( '%s.%s.POST./wp-json/edd/webhooks/v1/paypal.%s', $timestamp, $webhook_id, $body_hash );
		$signature = hash_hmac( 'sha256', $message, $this->hmac_key );

		$headers = array(
			'HTTP_X_EDD_TIMESTAMP'       => $timestamp,
			'HTTP_X_EDD_WEBHOOK_ID'      => $webhook_id,
			'HTTP_X_EDD_PROXY_SIGNATURE' => $signature,
		);

		$result = Payments::verify_connect_webhook_signature( $body, $headers, $this->hmac_key );

		$this->assertTrue( $result );
	}

	/**
	 * Invalid HMAC signature is rejected.
	 */
	public function test_invalid_signature_rejected() {
		$timestamp  = (string) time();
		$webhook_id = 'WH-TEST-123';
		$body       = '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}';

		$headers = array(
			'HTTP_X_EDD_TIMESTAMP'       => $timestamp,
			'HTTP_X_EDD_WEBHOOK_ID'      => $webhook_id,
			'HTTP_X_EDD_PROXY_SIGNATURE' => 'invalid_signature_here',
		);

		$result = Payments::verify_connect_webhook_signature( $body, $headers, $this->hmac_key );

		$this->assertFalse( $result );
	}

	/**
	 * Expired timestamp (> 5 minutes) is rejected.
	 */
	public function test_expired_timestamp_rejected() {
		$timestamp  = (string) ( time() - 400 ); // 6+ minutes ago.
		$webhook_id = 'WH-TEST-123';
		$body       = '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}';
		$body_hash  = hash( 'sha256', $body );

		$message   = sprintf( '%s.%s.POST./wp-json/edd/webhooks/v1/paypal.%s', $timestamp, $webhook_id, $body_hash );
		$signature = hash_hmac( 'sha256', $message, $this->hmac_key );

		$headers = array(
			'HTTP_X_EDD_TIMESTAMP'       => $timestamp,
			'HTTP_X_EDD_WEBHOOK_ID'      => $webhook_id,
			'HTTP_X_EDD_PROXY_SIGNATURE' => $signature,
		);

		$result = Payments::verify_connect_webhook_signature( $body, $headers, $this->hmac_key );

		$this->assertFalse( $result );
	}

	/**
	 * Missing timestamp header is rejected.
	 */
	public function test_missing_timestamp_rejected() {
		$headers = array(
			'HTTP_X_EDD_WEBHOOK_ID'      => 'WH-TEST-123',
			'HTTP_X_EDD_PROXY_SIGNATURE' => 'some_signature',
		);

		$result = Payments::verify_connect_webhook_signature( '{}', $headers, $this->hmac_key );

		$this->assertFalse( $result );
	}

	/**
	 * Missing webhook ID header is rejected.
	 */
	public function test_missing_webhook_id_rejected() {
		$headers = array(
			'HTTP_X_EDD_TIMESTAMP'       => (string) time(),
			'HTTP_X_EDD_PROXY_SIGNATURE' => 'some_signature',
		);

		$result = Payments::verify_connect_webhook_signature( '{}', $headers, $this->hmac_key );

		$this->assertFalse( $result );
	}

	/**
	 * Missing signature header is rejected.
	 */
	public function test_missing_signature_rejected() {
		$headers = array(
			'HTTP_X_EDD_TIMESTAMP'  => (string) time(),
			'HTTP_X_EDD_WEBHOOK_ID' => 'WH-TEST-123',
		);

		$result = Payments::verify_connect_webhook_signature( '{}', $headers, $this->hmac_key );

		$this->assertFalse( $result );
	}

	/**
	 * Empty headers are rejected.
	 */
	public function test_empty_headers_rejected() {
		$result = Payments::verify_connect_webhook_signature( '{}', array(), $this->hmac_key );

		$this->assertFalse( $result );
	}

	/**
	 * Proxy webhook is detected by integration type header.
	 */
	public function test_proxy_webhook_detected() {
		$_SERVER['HTTP_X_EDD_INTEGRATION_TYPE'] = 'third_party';

		$this->assertTrue( Payments::is_connect_webhook() );

		unset( $_SERVER['HTTP_X_EDD_INTEGRATION_TYPE'] );
	}

	/**
	 * Non-proxy webhook is not detected as proxy.
	 */
	public function test_non_proxy_webhook_not_detected() {
		unset( $_SERVER['HTTP_X_EDD_INTEGRATION_TYPE'] );

		$this->assertFalse( Payments::is_connect_webhook() );
	}

	/**
	 * Proxy webhook with wrong integration type is not detected.
	 */
	public function test_wrong_integration_type_not_detected() {
		$_SERVER['HTTP_X_EDD_INTEGRATION_TYPE'] = 'first_party';

		$this->assertFalse( Payments::is_connect_webhook() );

		unset( $_SERVER['HTTP_X_EDD_INTEGRATION_TYPE'] );
	}

	/**
	 * HMAC key retrieval from options works.
	 */
	public function test_webhook_hmac_key_retrieval() {
		add_filter( 'edd_is_test_mode', '__return_true' );
		Credentials::store_hmac_key( 'sandbox', $this->hmac_key );

		$key = Payments::get_webhook_hmac_key();

		$this->assertSame( $this->hmac_key, $key );

		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * HMAC key returns empty string when not set.
	 */
	public function test_webhook_hmac_key_empty_when_unset() {
		add_filter( 'edd_is_test_mode', '__return_true' );
		delete_option( 'edd_paypal_sandbox_hmac_key' );

		$key = Payments::get_webhook_hmac_key();

		$this->assertSame( '', $key );

		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * Signature with tampered body is rejected.
	 */
	public function test_tampered_body_rejected() {
		$timestamp     = (string) time();
		$webhook_id    = 'WH-TEST-123';
		$original_body = '{"event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAP123"}}';
		$tampered_body = '{"event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAP999"}}';
		$body_hash     = hash( 'sha256', $original_body );

		// Sign the original body.
		$message   = sprintf( '%s.%s.POST./wp-json/edd/webhooks/v1/paypal.%s', $timestamp, $webhook_id, $body_hash );
		$signature = hash_hmac( 'sha256', $message, $this->hmac_key );

		$headers = array(
			'HTTP_X_EDD_TIMESTAMP'       => $timestamp,
			'HTTP_X_EDD_WEBHOOK_ID'      => $webhook_id,
			'HTTP_X_EDD_PROXY_SIGNATURE' => $signature,
		);

		// Verify with tampered body.
		$result = Payments::verify_connect_webhook_signature( $tampered_body, $headers, $this->hmac_key );

		$this->assertFalse( $result );
	}
}
