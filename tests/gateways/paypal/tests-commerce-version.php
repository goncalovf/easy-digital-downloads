<?php
/**
 * PayPal Commerce Version Detection Tests
 *
 * Tests the CommerceVersion class: version detection, constants,
 * partner client ID helper, and upgrade migration.
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Gateways\PayPal\CommerceVersion as CV;
use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * Tests for the CommerceVersion class.
 *
 * @group gateways
 * @group paypal
 * @group paypal-commerce-version
 */
class CommerceVersionTest extends EDD_UnitTestCase {

	/**
	 * Clean up PayPal options before and after each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clean_up_paypal_options();
	}

	/**
	 * Clean up PayPal options after each test.
	 */
	public function tearDown(): void {
		$this->clean_up_paypal_options();
		parent::tearDown();
	}

	/**
	 * Remove all PayPal-related options used by these tests.
	 */
	private function clean_up_paypal_options() {
		delete_option( 'edd_paypal_sandbox_commerce_version' );
		delete_option( 'edd_paypal_live_commerce_version' );

		// Reset the upgrade flag.
		$completed = get_option( 'edd_completed_upgrades', array() );
		$completed = array_diff( $completed, array( 'paypal_commerce_version_v3' ) );
		update_option( 'edd_completed_upgrades', $completed );

		// Clean up EDD settings.
		edd_delete_option( 'paypal_sandbox_client_id' );
		edd_delete_option( 'paypal_live_client_id' );
	}

	/**
	 * New installs with no options set should default to v3.
	 */
	public function test_default_is_v3_for_new_installs() {
		$this->assertSame( 'v3', CV::get_version() );
	}

	/**
	 * When the option is explicitly set to v2, it should return v2.
	 */
	public function test_returns_v2_when_set() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		update_option( 'edd_paypal_sandbox_commerce_version', 'v2' );

		$this->assertSame( 'v2', CV::get_version() );

		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * When the option is explicitly set to v3, it should return v3.
	 */
	public function test_returns_v3_when_set() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		update_option( 'edd_paypal_sandbox_commerce_version', 'v3' );

		$this->assertSame( 'v3', CV::get_version() );

		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * In live mode, the live option should be read.
	 */
	public function test_reads_live_option_in_live_mode() {
		add_filter( 'edd_is_test_mode', '__return_false' );

		update_option( 'edd_paypal_live_commerce_version', 'v2' );

		$this->assertSame( 'v2', CV::get_version() );

		remove_filter( 'edd_is_test_mode', '__return_false' );
	}

	/**
	 * In sandbox mode, the sandbox option should be read.
	 */
	public function test_reads_sandbox_option_in_sandbox_mode() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		update_option( 'edd_paypal_sandbox_commerce_version', 'v2' );
		update_option( 'edd_paypal_live_commerce_version', 'v3' );

		$this->assertSame( 'v2', CV::get_version() );

		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * Migration should set v2 for stores with existing sandbox client credentials.
	 */
	public function test_migration_sets_v2_for_existing_sandbox_credentials() {
		edd_update_option( 'paypal_sandbox_client_id', 'test-sandbox-client-id' );

		$cv = new CV();
		$cv->maybe_migrate();

		$this->assertSame( 'v2', get_option( 'edd_paypal_sandbox_commerce_version' ) );
	}

	/**
	 * Migration should set v2 for stores with existing live client credentials.
	 */
	public function test_migration_sets_v2_for_existing_live_credentials() {
		edd_update_option( 'paypal_live_client_id', 'test-live-client-id' );

		$cv = new CV();
		$cv->maybe_migrate();

		$this->assertSame( 'v2', get_option( 'edd_paypal_live_commerce_version' ) );
	}

	/**
	 * Migration should not set v2 for stores without existing credentials.
	 */
	public function test_migration_does_not_set_v2_for_new_installs() {
		$cv = new CV();
		$cv->maybe_migrate();

		$this->assertFalse( get_option( 'edd_paypal_sandbox_commerce_version' ) );
		$this->assertFalse( get_option( 'edd_paypal_live_commerce_version' ) );
	}

	/**
	 * Migration should not overwrite an already-set commerce version.
	 */
	public function test_migration_does_not_overwrite_existing_version() {
		update_option( 'edd_paypal_sandbox_commerce_version', 'v3' );
		edd_update_option( 'paypal_sandbox_client_id', 'test-sandbox-client-id' );

		$cv = new CV();
		$cv->maybe_migrate();

		$this->assertSame( 'v3', get_option( 'edd_paypal_sandbox_commerce_version' ) );
	}

	/**
	 * Migration should only run once.
	 */
	public function test_migration_runs_only_once() {
		edd_update_option( 'paypal_sandbox_client_id', 'test-sandbox-client-id' );

		$cv = new CV();

		// First run should set v2.
		$cv->maybe_migrate();
		$this->assertSame( 'v2', get_option( 'edd_paypal_sandbox_commerce_version' ) );

		// Clear the option to simulate a situation where it should stay untouched.
		delete_option( 'edd_paypal_sandbox_commerce_version' );

		// Second run should be a no-op because the upgrade is marked complete.
		$cv->maybe_migrate();
		$this->assertFalse( get_option( 'edd_paypal_sandbox_commerce_version' ) );
	}

	/**
	 * Migration should handle both modes independently.
	 */
	public function test_migration_handles_both_modes() {
		edd_update_option( 'paypal_sandbox_client_id', 'test-sandbox-client-id' );

		$cv = new CV();
		$cv->maybe_migrate();

		// Sandbox should be v2 (has credentials).
		$this->assertSame( 'v2', get_option( 'edd_paypal_sandbox_commerce_version' ) );

		// Live should be unset (no credentials).
		$this->assertFalse( get_option( 'edd_paypal_live_commerce_version' ) );
	}

	/**
	 * get_connect_url should return the Connect URL.
	 */
	public function test_get_connect_url_returns_default() {
		$this->assertSame( 'https://connect.easydigitaldownloads.com', CV::get_connect_url() );
	}

	/**
	 * The subscriber should hook into admin_init.
	 */
	public function test_get_subscribed_events_hooks_admin_init() {
		$events = CV::get_subscribed_events();

		$this->assertArrayHasKey( 'admin_init', $events );
		$this->assertSame( 'maybe_migrate', $events['admin_init'] );
	}
}
