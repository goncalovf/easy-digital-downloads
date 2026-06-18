<?php
/**
 * PayPal v3 Onboarding Tests
 *
 * Tests the Onboarding class: store registration, option storage,
 * onboarding completion, reconnect credential cleanup, merchant status,
 * and SubscriberInterface integration.
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Gateways\PayPal\V3\Credentials;
use EDD\Gateways\PayPal\V3\KeyRotation;
use EDD\Gateways\PayPal\V3\Merchant;
use EDD\Gateways\PayPal\V3\Onboarding;
use EDD\Tests\PHPUnit\EDD_UnitTestCase;
use EDD\Utils\Encryption;
use EDD\Utils\Transient;

/**
 * Tests for the Onboarding class.
 *
 * @group gateways
 * @group paypal
 * @group paypal-onboarding
 */
class OnboardingTest extends EDD_UnitTestCase {

	/**
	 * Stored current user ID to restore after each test.
	 *
	 * @var int
	 */
	private $original_user_id = 0;

	/**
	 * Clean up PayPal options before each test.
	 *
	 * Onboarding methods require manage_shop_settings; tests run an admin user so
	 * the capability check inside Onboarding::complete_onboarding() passes.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clean_up_options();

		$this->original_user_id = get_current_user_id();
		$admin_id               = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
	}

	/**
	 * Clean up PayPal options after each test.
	 */
	public function tearDown(): void {
		wp_set_current_user( $this->original_user_id );
		$this->clean_up_options();
		parent::tearDown();
	}

	/**
	 * Remove all onboarding-related options.
	 */
	private function clean_up_options() {
		foreach ( array( 'sandbox', 'live' ) as $mode ) {
			delete_option( "edd_paypal_{$mode}_store_id" );
			delete_option( "edd_paypal_{$mode}_hmac_key" );
			delete_option( "edd_paypal_{$mode}_hmac_key_fingerprint" );
			delete_option( "edd_paypal_{$mode}_hmac_key_previous" );
			delete_option( "edd_paypal_{$mode}_hmac_key_previous_expires" );
			delete_option( "edd_paypal_{$mode}_merchant_id" );
			delete_option( "edd_paypal_{$mode}_capabilities" );
			delete_option( "edd_paypal_{$mode}_vaulting_available" );
			delete_option( "edd_paypal_{$mode}_commerce_version" );
			delete_option( sprintf( Onboarding::TRACKING_ID_OPTION, $mode ) );
			delete_option( "edd_paypal_commerce_connect_details_{$mode}" );
			delete_option( "edd_paypal_commerce_webhook_id_{$mode}" );
			edd_delete_option( "paypal_{$mode}_client_id" );
			edd_delete_option( "paypal_{$mode}_client_secret" );
		}
	}

	/**
	 * Onboarding class implements SubscriberInterface.
	 */
	public function test_implements_subscriber_interface() {
		$this->assertInstanceOf(
			'EDD\EventManagement\SubscriberInterface',
			new Onboarding()
		);
	}

	/**
	 * Subscribed events include all AJAX hooks.
	 */
	public function test_subscribed_events_has_ajax_hooks() {
		$events = Onboarding::get_subscribed_events();

		$this->assertArrayHasKey( 'wp_ajax_edd_paypal_v3_register_store', $events );
		$this->assertArrayHasKey( 'wp_ajax_edd_paypal_v3_reconnect', $events );
		$this->assertArrayHasKey( 'wp_ajax_edd_paypal_v3_get_merchant_status', $events );
		$this->assertArrayHasKey( 'load-download_page_edd-settings', $events );
	}

	/**
	 * complete_onboarding saves the merchant ID to options.
	 */
	public function test_complete_onboarding_saves_merchant_id() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		// Set up store credentials so ConnectAPI can be instantiated.
		update_option( 'edd_paypal_sandbox_store_id', 'test-store-id' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );

		// Mock the HTTP request.
		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'store_id'           => 'test-store-id',
					'merchant_id'        => 'MERCHANT123',
					'integration_type'   => 'THIRD_PARTY',
					'capabilities'       => array( 'PAYMENT', 'REFUND', 'VAULT' ),
					'products'           => array( 'PPCP', 'ADVANCED_VAULTING' ),
					'vaulting_available'  => true,
				) ),
			);
		} );

		$result = Onboarding::complete_onboarding( 'MERCHANT123' );

		$this->assertIsArray( $result );
		$this->assertSame( 'MERCHANT123', get_option( 'edd_paypal_sandbox_merchant_id' ) );

		remove_all_filters( 'pre_http_request' );
		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * complete_onboarding sets commerce version to v3.
	 */
	public function test_complete_onboarding_sets_commerce_version_v3() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		update_option( 'edd_paypal_sandbox_store_id', 'test-store-id' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );

		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'store_id'           => 'test-store-id',
					'merchant_id'        => 'MERCHANT123',
					'integration_type'   => 'THIRD_PARTY',
					'capabilities'       => array( 'PAYMENT' ),
					'products'           => array( 'PPCP' ),
					'vaulting_available'  => false,
				) ),
			);
		} );

		Onboarding::complete_onboarding( 'MERCHANT123' );

		$this->assertSame( 'v3', get_option( 'edd_paypal_sandbox_commerce_version' ) );

		remove_all_filters( 'pre_http_request' );
		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * complete_onboarding saves capabilities.
	 */
	public function test_complete_onboarding_saves_capabilities() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		update_option( 'edd_paypal_sandbox_store_id', 'test-store-id' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );

		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'store_id'           => 'test-store-id',
					'merchant_id'        => 'MERCHANT123',
					'integration_type'   => 'THIRD_PARTY',
					'capabilities'       => array( 'PAYMENT', 'REFUND', 'VAULT' ),
					'products'           => array( 'PPCP', 'ADVANCED_VAULTING' ),
					'vaulting_available'  => true,
				) ),
			);
		} );

		Onboarding::complete_onboarding( 'MERCHANT123' );

		$capabilities = get_option( 'edd_paypal_sandbox_capabilities' );
		$this->assertIsArray( $capabilities );
		$this->assertContains( 'VAULT', $capabilities );

		remove_all_filters( 'pre_http_request' );
		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * complete_onboarding saves vaulting availability.
	 */
	public function test_complete_onboarding_saves_vaulting_available() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		update_option( 'edd_paypal_sandbox_store_id', 'test-store-id' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );

		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'store_id'           => 'test-store-id',
					'merchant_id'        => 'MERCHANT123',
					'integration_type'   => 'THIRD_PARTY',
					'capabilities'       => array( 'PAYMENT', 'VAULT' ),
					'products'           => array( 'PPCP', 'ADVANCED_VAULTING' ),
					'vaulting_available'  => true,
				) ),
			);
		} );

		Onboarding::complete_onboarding( 'MERCHANT123' );

		$this->assertTrue( get_option( 'edd_paypal_sandbox_vaulting_available' ) );

		remove_all_filters( 'pre_http_request' );
		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * complete_onboarding cleans up tracking ID after success.
	 */
	public function test_complete_onboarding_cleans_up_tracking_id() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		update_option( 'edd_paypal_sandbox_store_id', 'test-store-id' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );
		update_option( sprintf( Onboarding::TRACKING_ID_OPTION, 'sandbox' ), 'track-123' );

		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'store_id'           => 'test-store-id',
					'merchant_id'        => 'MERCHANT123',
					'integration_type'   => 'THIRD_PARTY',
					'capabilities'       => array( 'PAYMENT' ),
					'products'           => array( 'PPCP' ),
					'vaulting_available'  => false,
				) ),
			);
		} );

		Onboarding::complete_onboarding( 'MERCHANT123' );

		$this->assertFalse( get_option( sprintf( Onboarding::TRACKING_ID_OPTION, 'sandbox' ) ) );

		remove_all_filters( 'pre_http_request' );
		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * reconnect clears v2 client ID and client secret.
	 */
	public function test_reconnect_clears_v2_credentials() {
		edd_update_option( 'paypal_sandbox_client_id', 'old-client-id' );
		edd_update_option( 'paypal_sandbox_client_secret', 'old-client-secret' );

		Onboarding::reconnect( 'sandbox' );

		$this->assertEmpty( edd_get_option( 'paypal_sandbox_client_id' ) );
		$this->assertEmpty( edd_get_option( 'paypal_sandbox_client_secret' ) );
	}

	/**
	 * reconnect leaves a breadcrumb when a v2 connection existed, so the IPN
	 * fallback keeps processing the now-abandoned v2 subscriptions on v3.
	 */
	public function test_reconnect_records_had_v2_connection_breadcrumb() {
		edd_update_option( 'paypal_sandbox_client_id', 'old-client-id' );
		edd_update_option( 'paypal_sandbox_client_secret', 'old-client-secret' );

		Onboarding::reconnect( 'sandbox' );

		$this->assertTrue( (bool) get_option( 'edd_paypal_sandbox_had_v2_connection' ) );
	}

	/**
	 * reconnect does not set the v2 breadcrumb when there was no v2 connection.
	 */
	public function test_reconnect_skips_breadcrumb_without_v2_connection() {
		Onboarding::reconnect( 'sandbox' );

		$this->assertFalse( (bool) get_option( 'edd_paypal_sandbox_had_v2_connection' ) );
	}

	/**
	 * reconnect clears legacy connect details.
	 */
	public function test_reconnect_clears_legacy_connect_details() {
		update_option( 'edd_paypal_commerce_connect_details_sandbox', 'old-details' );
		update_option( 'edd_paypal_commerce_webhook_id_sandbox', 'old-webhook-id' );

		Onboarding::reconnect( 'sandbox' );

		$this->assertFalse( get_option( 'edd_paypal_commerce_connect_details_sandbox' ) );
		$this->assertFalse( get_option( 'edd_paypal_commerce_webhook_id_sandbox' ) );
	}

	/**
	 * reconnect clears v3 proxy credentials.
	 */
	public function test_reconnect_clears_v3_proxy_credentials() {
		update_option( 'edd_paypal_sandbox_store_id', 'store-uuid' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'b', 64 ) );
		update_option( 'edd_paypal_sandbox_merchant_id', 'MERCHANT123' );

		Onboarding::reconnect( 'sandbox' );

		$this->assertFalse( get_option( 'edd_paypal_sandbox_store_id' ) );
		$this->assertFalse( get_option( 'edd_paypal_sandbox_hmac_key' ) );
		$this->assertFalse( get_option( 'edd_paypal_sandbox_merchant_id' ) );
	}

	/**
	 * reconnect resets the commerce version.
	 */
	public function test_reconnect_resets_commerce_version() {
		update_option( 'edd_paypal_sandbox_commerce_version', 'v2' );

		Onboarding::reconnect( 'sandbox' );

		// reconnect() advances commerce_version to v3 so the settings UI shows
		// the v3 onboarding flow after a disconnect, even on a previously v2 store.
		$this->assertSame( 'v3', get_option( 'edd_paypal_sandbox_commerce_version' ) );
	}

	/**
	 * reconnect defaults to current mode.
	 */
	public function test_reconnect_defaults_to_current_mode() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		update_option( 'edd_paypal_sandbox_store_id', 'store-uuid' );
		update_option( 'edd_paypal_live_store_id', 'live-store-uuid' );

		Onboarding::reconnect();

		// Sandbox should be cleared.
		$this->assertFalse( get_option( 'edd_paypal_sandbox_store_id' ) );

		// Live should remain.
		$this->assertSame( 'live-store-uuid', get_option( 'edd_paypal_live_store_id' ) );

		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * reconnect clears live mode credentials when specified.
	 */
	public function test_reconnect_clears_live_credentials() {
		edd_update_option( 'paypal_live_client_id', 'live-client-id' );
		update_option( 'edd_paypal_live_store_id', 'live-store-uuid' );
		update_option( 'edd_paypal_live_merchant_id', 'LIVE_MERCHANT' );

		Onboarding::reconnect( 'live' );

		$this->assertEmpty( edd_get_option( 'paypal_live_client_id' ) );
		$this->assertFalse( get_option( 'edd_paypal_live_store_id' ) );
		$this->assertFalse( get_option( 'edd_paypal_live_merchant_id' ) );
	}

	/**
	 * is_v3_onboarded returns true when all credentials are present.
	 */
	public function test_is_v3_onboarded_returns_true_when_complete() {
		update_option( 'edd_paypal_sandbox_store_id', 'store-uuid' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );
		update_option( 'edd_paypal_sandbox_merchant_id', 'MERCHANT123' );

		$this->assertTrue( Onboarding::is_v3_onboarded( 'sandbox' ) );
	}

	/**
	 * is_v3_onboarded returns false when store_id is missing.
	 */
	public function test_is_v3_onboarded_returns_false_when_missing_store_id() {
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );
		update_option( 'edd_paypal_sandbox_merchant_id', 'MERCHANT123' );

		$this->assertFalse( Onboarding::is_v3_onboarded( 'sandbox' ) );
	}

	/**
	 * is_v3_onboarded returns false when hmac_key is missing.
	 */
	public function test_is_v3_onboarded_returns_false_when_missing_hmac_key() {
		update_option( 'edd_paypal_sandbox_store_id', 'store-uuid' );
		update_option( 'edd_paypal_sandbox_merchant_id', 'MERCHANT123' );

		$this->assertFalse( Onboarding::is_v3_onboarded( 'sandbox' ) );
	}

	/**
	 * is_v3_onboarded returns false when merchant_id is missing.
	 */
	public function test_is_v3_onboarded_returns_false_when_missing_merchant_id() {
		update_option( 'edd_paypal_sandbox_store_id', 'store-uuid' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );

		$this->assertFalse( Onboarding::is_v3_onboarded( 'sandbox' ) );
	}

	/**
	 * is_v3_onboarded defaults to current mode.
	 */
	public function test_is_v3_onboarded_defaults_to_current_mode() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		update_option( 'edd_paypal_sandbox_store_id', 'store-uuid' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );
		update_option( 'edd_paypal_sandbox_merchant_id', 'MERCHANT123' );

		$this->assertTrue( Onboarding::is_v3_onboarded() );

		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * complete_onboarding returns WP_Error on proxy failure.
	 */
	public function test_complete_onboarding_returns_error_on_proxy_failure() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		update_option( 'edd_paypal_sandbox_store_id', 'test-store-id' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );

		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 502, 'message' => 'Bad Gateway' ),
				'body'     => wp_json_encode( array(
					'error' => array(
						'code'    => 'paypal_error',
						'message' => 'Auth code exchange failed.',
					),
				) ),
			);
		} );

		$result = Onboarding::complete_onboarding( 'MERCHANT123' );

		$this->assertInstanceOf( 'WP_Error', $result );

		remove_all_filters( 'pre_http_request' );
		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * complete_onboarding returns WP_Error when HTTP request fails.
	 */
	public function test_complete_onboarding_returns_error_on_http_failure() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		update_option( 'edd_paypal_sandbox_store_id', 'test-store-id' );
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'a', 64 ) );

		add_filter( 'pre_http_request', function() {
			return new \WP_Error( 'http_request_failed', 'Connection timed out.' );
		} );

		$result = Onboarding::complete_onboarding( 'MERCHANT123' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'http_request_failed', $result->get_error_code() );

		remove_all_filters( 'pre_http_request' );
		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * complete_onboarding uses live mode options when not in test mode.
	 */
	public function test_complete_onboarding_uses_live_mode() {
		add_filter( 'edd_is_test_mode', '__return_false' );

		update_option( 'edd_paypal_live_store_id', 'live-store-id' );
		update_option( 'edd_paypal_live_hmac_key', str_repeat( 'c', 64 ) );

		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'store_id'          => 'live-store-id',
					'merchant_id'       => 'LIVE_MERCHANT',
					'integration_type'  => 'THIRD_PARTY',
					'capabilities'      => array( 'PAYMENT' ),
					'products'          => array( 'PPCP' ),
					'vaulting_available' => false,
				) ),
			);
		} );

		Onboarding::complete_onboarding( 'LIVE_MERCHANT' );

		$this->assertSame( 'LIVE_MERCHANT', get_option( 'edd_paypal_live_merchant_id' ) );
		$this->assertSame( 'v3', get_option( 'edd_paypal_live_commerce_version' ) );

		remove_all_filters( 'pre_http_request' );
		remove_filter( 'edd_is_test_mode', '__return_false' );
	}

	/**
	 * store_hmac_key encrypts the key and stores a fingerprint beside it.
	 */
	public function test_store_hmac_key_writes_fingerprint() {
		Credentials::store_hmac_key( 'sandbox', 'super-secret-key' );

		// The stored value is ciphertext, not the plaintext.
		$this->assertNotSame( 'super-secret-key', get_option( 'edd_paypal_sandbox_hmac_key' ) );
		// It round-trips back to the plaintext.
		$this->assertSame( 'super-secret-key', Credentials::get_hmac_key( 'sandbox' ) );
		// A fingerprint was written and matches the current encryption key.
		$this->assertSame(
			Encryption::key_fingerprint(),
			get_option( 'edd_paypal_sandbox_hmac_key_fingerprint' )
		);
	}

	/**
	 * validate_hmac_key returns true when no key is stored.
	 */
	public function test_validate_hmac_key_true_when_no_key() {
		$this->assertTrue( Credentials::validate_hmac_key( 'sandbox' ) );
	}

	/**
	 * validate_hmac_key returns true for a legacy key stored without a fingerprint.
	 */
	public function test_validate_hmac_key_true_for_legacy_key() {
		update_option( 'edd_paypal_sandbox_hmac_key', str_repeat( 'b', 64 ) );

		$this->assertTrue( Credentials::validate_hmac_key( 'sandbox' ) );
	}

	/**
	 * validate_hmac_key returns true when the stored fingerprint matches.
	 */
	public function test_validate_hmac_key_true_when_fingerprint_matches() {
		Credentials::store_hmac_key( 'sandbox', 'super-secret-key' );

		$this->assertTrue( Credentials::validate_hmac_key( 'sandbox' ) );
	}

	/**
	 * validate_hmac_key returns false when the fingerprint no longer matches
	 * (simulating a salt rotation that left the ciphertext undecryptable).
	 */
	public function test_validate_hmac_key_false_on_fingerprint_mismatch() {
		Credentials::store_hmac_key( 'sandbox', 'super-secret-key' );
		update_option( 'edd_paypal_sandbox_hmac_key_fingerprint', str_repeat( 'f', 64 ) );

		$this->assertFalse( Credentials::validate_hmac_key( 'sandbox' ) );
	}

	/**
	 * get_previous_hmac_key returns the decrypted key while the window is open.
	 */
	public function test_get_previous_hmac_key_within_window() {
		( new Transient( 'edd_paypal_sandbox_hmac_key_previous', '+1 hour' ) )->set( Encryption::encrypt( 'old-key' ) );

		$this->assertSame( 'old-key', Credentials::get_previous_hmac_key( 'sandbox' ) );
	}

	/**
	 * get_previous_hmac_key returns empty once the grace window has expired.
	 */
	public function test_get_previous_hmac_key_expired() {
		// Set a transient that already expired by backdating the timeout.
		$expired = wp_json_encode( array( 'value' => Encryption::encrypt( 'old-key' ), 'timeout' => time() - 1 ) );
		update_option( 'edd_paypal_sandbox_hmac_key_previous', $expired, false );

		$this->assertSame( '', Credentials::get_previous_hmac_key( 'sandbox' ) );
	}

	/**
	 * reconnect clears the fingerprint and previous-key grace options.
	 */
	public function test_reconnect_clears_hmac_rotation_options() {
		Credentials::store_hmac_key( 'sandbox', 'super-secret-key' );
		( new Transient( 'edd_paypal_sandbox_hmac_key_previous', '+1 hour' ) )->set( Encryption::encrypt( 'old-key' ) );

		Onboarding::reconnect( 'sandbox' );

		$this->assertFalse( get_option( 'edd_paypal_sandbox_hmac_key_fingerprint' ) );
		$this->assertFalse( get_option( 'edd_paypal_sandbox_hmac_key_previous' ) );
	}

	/**
	 * rotate_hmac_key returns a WP_Error when there is no current key to sign with.
	 */
	public function test_rotate_hmac_key_errors_without_current_key() {
		$result = KeyRotation::rotate( 'sandbox' );

		$this->assertWPError( $result );
		$this->assertSame( 'edd_paypal_v3_rotate_unavailable', $result->get_error_code() );
	}

	/**
	 * get_merchant_status adopts a proxy-handed rotated key and never caches it.
	 */
	public function test_get_merchant_status_adopts_rotated_key() {
		update_option( 'edd_paypal_sandbox_store_id', 'store-uuid' );
		Credentials::store_hmac_key( 'sandbox', 'current-key' );

		add_filter( 'pre_http_request', function() {
			return array(
				'response' => array( 'code' => 200 ),
				'headers'  => array( 'content-type' => 'application/json' ),
				'body'     => wp_json_encode( array(
					'merchant_id'       => 'MERCHANT123',
					'rotated_hmac_key'  => 'brand-new-key',
					'capabilities'      => array( 'PAYMENT' ),
				) ),
			);
		} );

		$result = Merchant::get_status( 'MERCHANT123', 'sandbox', true );

		remove_all_filters( 'pre_http_request' );

		// The new key was adopted, the old key moved to the grace window.
		$this->assertSame( 'brand-new-key', Credentials::get_hmac_key( 'sandbox' ) );
		$this->assertSame( 'current-key', Credentials::get_previous_hmac_key( 'sandbox' ) );
		// The secret is stripped from the response and never cached.
		$this->assertArrayNotHasKey( 'rotated_hmac_key', (array) $result );
		$cached = get_transient( Merchant::get_status_cache_key( 'sandbox' ) );
		$this->assertArrayNotHasKey( 'rotated_hmac_key', (array) $cached );
	}
}
