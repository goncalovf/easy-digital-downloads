<?php
/**
 * PayPal V3 Vault capture-persistence Tests
 *
 * Characterizes Vault::persist_from_capture() — the single capture-side vault
 * path shared by the smart-button, Fastlane, and on-checkout card flows:
 * source precedence, order-meta stamping, the edd_paypal_v3_order_vaulted hook,
 * and the customer.id-only-when-present rule.
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Gateways\PayPal\V3\Vault;
use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * Tests for Vault::persist_from_capture().
 *
 * @group gateways
 * @group paypal
 * @group paypal-vault
 */
class VaultCaptureTest extends EDD_UnitTestCase {

	/**
	 * Builds a minimal EDD order for stamping.
	 *
	 * @return int
	 */
	private function make_order(): int {
		return edd_add_order(
			array(
				'status'   => 'pending',
				'gateway'  => 'paypal_commerce',
				'total'    => 10.00,
				'currency' => 'USD',
			)
		);
	}

	/**
	 * Builds a capture response carrying a vault block under the given source.
	 *
	 * @param string $source      payment_source key (paypal|card).
	 * @param string $vault_id    Vault token id.
	 * @param string $customer_id Vault customer id, or empty.
	 * @return array
	 */
	private function capture_response( string $source, string $vault_id, string $customer_id = '' ): array {
		$vault = array( 'id' => $vault_id );
		if ( '' !== $customer_id ) {
			$vault['customer'] = array( 'id' => $customer_id );
		}

		return array(
			'payment_source' => array(
				$source => array(
					'attributes' => array(
						'vault' => $vault,
					),
				),
			),
		);
	}

	/**
	 * A card-source capture stamps the vault meta and returns the ids.
	 */
	public function test_card_source_stamps_meta_and_returns_ids() {
		$order_id = $this->make_order();

		$result = Vault::persist_from_capture( $order_id, 'TXN-1', $this->capture_response( 'card', 'VID-1', 'CUST-1' ) );

		$this->assertSame( 'VID-1', $result['vault_id'] );
		$this->assertSame( 'CUST-1', $result['vault_customer_id'] );
		$this->assertSame( 'VID-1', edd_get_order_meta( $order_id, '_edd_paypal_vault_id', true ) );
		$this->assertSame( 'CUST-1', edd_get_order_meta( $order_id, '_edd_paypal_vault_customer_id', true ) );
	}

	/**
	 * The PayPal wallet source wins when both wallet and card vault data are present.
	 */
	public function test_paypal_source_takes_precedence_over_card() {
		$order_id = $this->make_order();

		$response                              = $this->capture_response( 'paypal', 'WALLET-VID', 'WALLET-CUST' );
		$response['payment_source']['card']    = array(
			'attributes' => array( 'vault' => array( 'id' => 'CARD-VID' ) ),
		);

		$result = Vault::persist_from_capture( $order_id, 'TXN-2', $response );

		$this->assertSame( 'WALLET-VID', $result['vault_id'] );
		$this->assertSame( 'WALLET-CUST', $result['vault_customer_id'] );
	}

	/**
	 * The edd_paypal_v3_order_vaulted hook fires with the canonical signature.
	 */
	public function test_fires_vaulted_hook_with_expected_args() {
		$order_id = $this->make_order();
		$captured = array();

		$listener = function ( $oid, $vid, $vcid, $tid ) use ( &$captured ) {
			$captured = array( $oid, $vid, $vcid, $tid );
		};
		add_action( 'edd_paypal_v3_order_vaulted', $listener, 10, 4 );

		Vault::persist_from_capture( $order_id, 'TXN-3', $this->capture_response( 'card', 'VID-3', 'CUST-3' ) );

		remove_action( 'edd_paypal_v3_order_vaulted', $listener, 10 );

		$this->assertSame( array( $order_id, 'VID-3', 'CUST-3', 'TXN-3' ), $captured );
	}

	/**
	 * A vault with no customer id stamps the token but not the customer meta.
	 */
	public function test_customer_meta_only_stamped_when_present() {
		$order_id = $this->make_order();

		Vault::persist_from_capture( $order_id, 'TXN-4', $this->capture_response( 'card', 'VID-4' ) );

		$this->assertSame( 'VID-4', edd_get_order_meta( $order_id, '_edd_paypal_vault_id', true ) );
		$this->assertEmpty( edd_get_order_meta( $order_id, '_edd_paypal_vault_customer_id', true ) );
	}

	/**
	 * A capture with no vault data is a no-op: no meta, no hook, empty result.
	 */
	public function test_no_vault_data_is_noop() {
		$order_id = $this->make_order();
		$fired    = false;

		$listener = function () use ( &$fired ) {
			$fired = true;
		};
		add_action( 'edd_paypal_v3_order_vaulted', $listener );

		$result = Vault::persist_from_capture( $order_id, 'TXN-5', array( 'payment_source' => array( 'card' => array() ) ) );

		remove_action( 'edd_paypal_v3_order_vaulted', $listener );

		$this->assertSame( '', $result['vault_id'] );
		$this->assertFalse( $fired );
		$this->assertEmpty( edd_get_order_meta( $order_id, '_edd_paypal_vault_id', true ) );
	}
}
