<?php
/**
 * PayPal V3 CaptureStatus Tests
 *
 * Pins the PayPal-capture-status → EDD-order-status table that every v3 capture
 * path shares. PENDING must route to on_hold (fraud review / e-check holds) and
 * unknown statuses must not silently complete.
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Gateways\PayPal\V3\CaptureStatus;
use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * Tests for the PayPal V3 capture status mapping.
 *
 * @group gateways
 * @group paypal
 */
class CaptureStatusTest extends EDD_UnitTestCase {

	/**
	 * COMPLETED captures map to a complete order.
	 */
	public function test_completed_maps_to_complete() {
		$this->assertSame( 'complete', CaptureStatus::to_edd_status( 'COMPLETED' ) );
	}

	/**
	 * PENDING captures map to on_hold so fraud review / e-check holds aren't completed.
	 */
	public function test_pending_maps_to_on_hold() {
		$this->assertSame( 'on_hold', CaptureStatus::to_edd_status( 'PENDING' ) );
	}

	/**
	 * DECLINED and FAILED captures map to failed.
	 */
	public function test_declined_and_failed_map_to_failed() {
		$this->assertSame( 'failed', CaptureStatus::to_edd_status( 'DECLINED' ) );
		$this->assertSame( 'failed', CaptureStatus::to_edd_status( 'FAILED' ) );
	}

	/**
	 * The mapping is case-insensitive (PayPal statuses are upper-cased by callers).
	 */
	public function test_mapping_is_case_insensitive() {
		$this->assertSame( 'complete', CaptureStatus::to_edd_status( 'completed' ) );
		$this->assertSame( 'on_hold', CaptureStatus::to_edd_status( 'Pending' ) );
	}

	/**
	 * Empty or unknown statuses return '' so callers treat them as failures.
	 */
	public function test_empty_and_unknown_return_empty_string() {
		$this->assertSame( '', CaptureStatus::to_edd_status( '' ) );
		$this->assertSame( '', CaptureStatus::to_edd_status( 'PARTIALLY_REFUNDED' ) );
		$this->assertSame( '', CaptureStatus::to_edd_status( 'SOMETHING_NEW' ) );
	}
}
