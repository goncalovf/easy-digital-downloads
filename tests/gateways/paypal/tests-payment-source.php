<?php
/**
 * PayPal V3 PaymentSource Tests
 *
 * Pins the vault-with-purchase `attributes` shape, which PayPal's Orders API
 * requires exactly — including that `customer` is a sibling of `vault` (PayPal
 * silently drops it when nested inside `vault`).
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Gateways\PayPal\V3\PaymentSource;
use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * Tests for the PayPal V3 PaymentSource shaping.
 *
 * @group gateways
 * @group paypal
 */
class PaymentSourceTest extends EDD_UnitTestCase {

	/**
	 * The vault attributes carry the exact merchant-recurring vault block.
	 */
	public function test_vault_attributes_shape() {
		$this->assertSame(
			array(
				'vault' => array(
					'store_in_vault' => 'ON_SUCCESS',
					'usage_type'     => 'MERCHANT',
					'usage_pattern'  => 'SUBSCRIPTION_PREPAID',
				),
			),
			PaymentSource::vault_attributes()
		);
	}

	/**
	 * A returning buyer's customer id is attached as a sibling of vault.
	 */
	public function test_vault_attributes_attaches_customer_as_sibling_of_vault() {
		$attributes = PaymentSource::vault_attributes( 'CUST-123' );

		$this->assertSame( array( 'id' => 'CUST-123' ), $attributes['customer'] );
		$this->assertArrayHasKey( 'vault', $attributes );
		$this->assertArrayNotHasKey( 'customer', $attributes['vault'] );
	}

	/**
	 * No customer block is added for a new buyer (empty id).
	 */
	public function test_vault_attributes_omits_customer_for_new_buyer() {
		$this->assertArrayNotHasKey( 'customer', PaymentSource::vault_attributes( '' ) );
	}
}
