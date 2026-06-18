<?php
/**
 * PayPal V3 Merchant Status Tests
 *
 * Tests the MerchantStatus helper methods for product vetting
 * and status label resolution.
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;
use EDD\Gateways\PayPal\V3\MerchantStatus;

/**
 * Tests for MerchantStatus.
 *
 * @group gateways
 * @group paypal
 * @group paypal-merchant-status
 */
class MerchantStatusTest extends EDD_UnitTestCase {

	/**
	 * Returns vetting status for a matching product.
	 */
	public function test_get_product_vetting_status_found() {
		$response = array(
			'product_details' => array(
				array( 'name' => 'PPCP', 'vetting_status' => 'SUBSCRIBED' ),
				array( 'name' => 'ADVANCED_VAULTING', 'vetting_status' => 'PENDING' ),
			),
		);

		$this->assertSame( 'SUBSCRIBED', MerchantStatus::get_product_vetting_status( $response, 'PPCP' ) );
		$this->assertSame( 'PENDING', MerchantStatus::get_product_vetting_status( $response, 'ADVANCED_VAULTING' ) );
	}

	/**
	 * Returns null for a non-matching product.
	 */
	public function test_get_product_vetting_status_not_found() {
		$response = array(
			'product_details' => array(
				array( 'name' => 'PPCP', 'vetting_status' => 'SUBSCRIBED' ),
			),
		);

		$this->assertNull( MerchantStatus::get_product_vetting_status( $response, 'NONEXISTENT' ) );
	}

	/**
	 * Returns null when product_details is empty.
	 */
	public function test_get_product_vetting_status_empty() {
		$this->assertNull( MerchantStatus::get_product_vetting_status( array(), 'PPCP' ) );
		$this->assertNull( MerchantStatus::get_product_vetting_status( array( 'product_details' => array() ), 'PPCP' ) );
	}

	/**
	 * Returns null when product_details is missing.
	 */
	public function test_get_product_vetting_status_missing_key() {
		$this->assertNull( MerchantStatus::get_product_vetting_status( array( 'other_key' => 'value' ), 'PPCP' ) );
	}

	/**
	 * Returns null when vetting_status is null in the product.
	 */
	public function test_get_product_vetting_status_null_status() {
		$response = array(
			'product_details' => array(
				array( 'name' => 'PPCP', 'vetting_status' => null ),
			),
		);

		$this->assertNull( MerchantStatus::get_product_vetting_status( $response, 'PPCP' ) );
	}

	/**
	 * SUBSCRIBED returns "Approved and active." label.
	 */
	public function test_label_subscribed() {
		$label = MerchantStatus::get_vetting_status_label( 'SUBSCRIBED' );
		$this->assertStringContainsString( 'Approved', $label );
	}

	/**
	 * PENDING returns review message.
	 */
	public function test_label_pending() {
		$label = MerchantStatus::get_vetting_status_label( 'PENDING' );
		$this->assertStringContainsString( 'review', $label );
	}

	/**
	 * NEED_MORE_DATA returns additional info message.
	 */
	public function test_label_need_more_data() {
		$label = MerchantStatus::get_vetting_status_label( 'NEED_MORE_DATA' );
		$this->assertStringContainsString( 'Additional information', $label );
	}

	/**
	 * DENIED returns denied message.
	 */
	public function test_label_denied() {
		$label = MerchantStatus::get_vetting_status_label( 'DENIED' );
		$this->assertStringContainsString( 'denied', $label );
	}

	/**
	 * SUSPENDED returns suspended message.
	 */
	public function test_label_suspended() {
		$label = MerchantStatus::get_vetting_status_label( 'SUSPENDED' );
		$this->assertStringContainsString( 'suspended', $label );
	}

	/**
	 * IN_REVIEW returns review message.
	 */
	public function test_label_in_review() {
		$label = MerchantStatus::get_vetting_status_label( 'IN_REVIEW' );
		$this->assertStringContainsString( 'review', $label );
	}

	/**
	 * APPROVAL_PENDING returns pending message.
	 */
	public function test_label_approval_pending() {
		$label = MerchantStatus::get_vetting_status_label( 'APPROVAL_PENDING' );
		$this->assertStringContainsString( 'pending', $label );
	}

	/**
	 * Unknown status returns the raw status string.
	 */
	public function test_label_unknown_returns_raw() {
		$this->assertSame( 'SOME_UNKNOWN_STATUS', MerchantStatus::get_vetting_status_label( 'SOME_UNKNOWN_STATUS' ) );
	}
}
