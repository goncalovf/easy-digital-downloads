<?php
/**
 * Tests for Stripe Checkout Validation.
 *
 * @group edd_stripe
 * @group edd_stripe_validation
 *
 * @coversDefaultClass \EDD\Gateways\Stripe\Checkout\Validation
 */

namespace EDD\Tests\Stripe;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;
use EDD\Gateways\Stripe\Checkout\Validation;

class CheckoutValidation extends EDD_UnitTestCase {

	/**
	 * @covers ::intent_status
	 */
	public function test_intent_status_succeeds_for_succeeded() {
		$intent         = new \stdClass();
		$intent->status = 'succeeded';

		// Should not throw.
		Validation::intent_status( $intent );
		// Intentional pointless assertion to satisfy PHPUnit.
		$this->assertTrue( true );
	}

	/**
	 * @covers ::intent_status
	 */
	public function test_intent_status_succeeds_for_requires_capture() {
		$intent         = new \stdClass();
		$intent->status = 'requires_capture';

		Validation::intent_status( $intent );
		// Intentional pointless assertion to satisfy PHPUnit.
		$this->assertTrue( true );
	}

	/**
	 * @covers ::intent_status
	 */
	public function test_intent_status_throws_for_invalid_status() {
		$intent         = new \stdClass();
		$intent->status = 'requires_payment_method';

		$this->expectException( \EDD_Stripe_Gateway_Exception::class );
		Validation::intent_status( $intent );
	}

	/**
	 * @covers ::intent_status
	 */
	public function test_intent_status_accepts_custom_valid_statuses() {
		$intent         = new \stdClass();
		$intent->status = 'processing';

		Validation::intent_status( $intent, array( 'processing' ) );
		// Intentional pointless assertion to satisfy PHPUnit.
		$this->assertTrue( true );
	}

	/**
	 * @covers ::intent_amount
	 */
	public function test_intent_amount_passes_for_matching_amount() {
		$intent         = new \stdClass();
		$intent->object = 'payment_intent';
		$intent->amount = 9999;
		$intent->id     = 'pi_test123';

		// $99.99 * 100 = 9999
		Validation::intent_amount( $intent, 99.99 );
		// Intentional pointless assertion to satisfy PHPUnit.
		$this->assertTrue( true );
	}

	/**
	 * @covers ::intent_amount
	 */
	public function test_intent_amount_throws_for_mismatched_amount() {
		$intent         = new \stdClass();
		$intent->object = 'payment_intent';
		$intent->amount = 5000;
		$intent->id     = 'pi_test_mismatch';

		$this->expectException( \EDD_Stripe_Gateway_Exception::class );
		Validation::intent_amount( $intent, 99.99 );
	}

	/**
	 * @covers ::intent_amount
	 */
	public function test_intent_amount_skips_for_setup_intent() {
		$intent         = new \stdClass();
		$intent->object = 'setup_intent';
		$intent->id     = 'seti_test123';

		// Should not throw — SetupIntents have no amount.
		Validation::intent_amount( $intent, 99.99 );
		// Intentional pointless assertion to satisfy PHPUnit.
		$this->assertTrue( true );
	}

	/**
	 * @covers ::intent_amount
	 */
	public function test_intent_amount_allows_one_unit_rounding_tolerance() {
		$intent         = new \stdClass();
		$intent->object = 'payment_intent';
		$intent->amount = 10000;
		$intent->id     = 'pi_test_rounding';

		// Expected = 99.99 * 100 = 9999, actual = 10000, diff = 1 (within tolerance).
		Validation::intent_amount( $intent, 99.99 );
		// Intentional pointless assertion to satisfy PHPUnit.
		$this->assertTrue( true );
	}

	/**
	 * @covers ::intent_amount
	 */
	public function test_intent_amount_fails_beyond_rounding_tolerance() {
		$intent         = new \stdClass();
		$intent->object = 'payment_intent';
		$intent->amount = 10001;
		$intent->id     = 'pi_test_beyond';

		// Expected = 99.99 * 100 = 9999, actual = 10001, diff = 2 (exceeds tolerance).
		$this->expectException( \EDD_Stripe_Gateway_Exception::class );
		Validation::intent_amount( $intent, 99.99 );
	}

	/**
	 * @covers ::intent_amount
	 */
	public function test_intent_amount_with_zero_decimal_currency() {
		$intent         = new \stdClass();
		$intent->object = 'payment_intent';
		$intent->amount = 500;
		$intent->id     = 'pi_test_jpy';

		// Simulate zero-decimal currency (e.g. JPY) by setting the EDD currency.
		$original_currency = edd_get_option( 'currency', 'USD' );
		edd_update_option( 'currency', 'JPY' );

		Validation::intent_amount( $intent, 500 );
		// Intentional pointless assertion to satisfy PHPUnit.
		$this->assertTrue( true );

		edd_update_option( 'currency', $original_currency );
	}

	/**
	 * @covers ::charge_amount
	 */
	public function test_charge_amount_passes_for_matching_amount() {
		$charge         = new \stdClass();
		$charge->amount = 2500;
		$charge->id     = 'ch_test123';

		// $25.00 * 100 = 2500
		Validation::charge_amount( $charge, 25.00 );
		// Intentional pointless assertion to satisfy PHPUnit.
		$this->assertTrue( true );
	}

	/**
	 * @covers ::charge_amount
	 */
	public function test_charge_amount_throws_for_mismatched_amount() {
		$charge         = new \stdClass();
		$charge->amount = 5000;
		$charge->id     = 'ch_test_mismatch';

		$this->expectException( \EDD_Stripe_Gateway_Exception::class );
		Validation::charge_amount( $charge, 25.00 );
	}

	/**
	 * @covers ::purchase_data
	 */
	public function test_purchase_data_passes_for_valid_data() {
		$purchase_data = array(
			'price'      => 99.99,
			'user_email' => 'test@example.com',
		);

		Validation::purchase_data( $purchase_data );
		// Intentional pointless assertion to satisfy PHPUnit.
		$this->assertTrue( true );
	}

	/**
	 * @covers ::purchase_data
	 */
	public function test_purchase_data_throws_for_empty_data() {
		$this->expectException( \EDD_Stripe_Gateway_Exception::class );
		Validation::purchase_data( array() );
	}

	/**
	 * @covers ::purchase_data
	 */
	public function test_purchase_data_throws_for_null() {
		$this->expectException( \EDD_Stripe_Gateway_Exception::class );
		Validation::purchase_data( null );
	}

	/**
	 * @covers ::intent_exists
	 */
	public function test_intent_exists_passes_for_array_with_id() {
		$intent = array( 'id' => 'pi_test123' );

		Validation::intent_exists( $intent );
		// Intentional pointless assertion to satisfy PHPUnit.
		$this->assertTrue( true );
	}

	/**
	 * @covers ::intent_exists
	 */
	public function test_intent_exists_passes_for_object_with_id() {
		$intent     = new \stdClass();
		$intent->id = 'pi_test123';

		Validation::intent_exists( $intent );
		// Intentional pointless assertion to satisfy PHPUnit.
		$this->assertTrue( true );
	}

	/**
	 * @covers ::intent_exists
	 */
	public function test_intent_exists_throws_for_empty_array() {
		$this->expectException( \EDD_Stripe_Gateway_Exception::class );
		Validation::intent_exists( array() );
	}

	/**
	 * @covers ::intent_exists
	 */
	public function test_intent_exists_throws_for_array_without_id() {
		$this->expectException( \EDD_Stripe_Gateway_Exception::class );
		Validation::intent_exists( array( 'object' => 'payment_intent' ) );
	}

	/**
	 * @covers ::intent_exists
	 */
	public function test_intent_exists_throws_for_object_without_id() {
		$intent         = new \stdClass();
		$intent->object = 'payment_intent';

		$this->expectException( \EDD_Stripe_Gateway_Exception::class );
		Validation::intent_exists( $intent );
	}
}
