<?php
/**
 * PayPal Vault Tests
 *
 * Tests the edd_payment_tokens table, CRUD operations, customer meta storage,
 * and Vault class methods.
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;
use EDD\Database\Queries\PaymentToken as PaymentTokenQuery;
use EDD\Gateways\PayPal\V3\Customer;
use EDD\Gateways\PayPal\V3\Vault;
use EDD\Database\Rows\PaymentToken;

/**
 * Tests for PayPal Vault and the edd_payment_tokens table.
 *
 * @group gateways
 * @group paypal
 * @group paypal-vault
 */
class VaultTest extends EDD_UnitTestCase {

	/**
	 * Test customer ID.
	 *
	 * @var int
	 */
	private static $customer_id;

	/**
	 * Set up test fixtures.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		// Create a test customer.
		self::$customer_id = edd_add_customer(
			array(
				'email' => 'vault-test@example.com',
				'name'  => 'Vault Test User',
			)
		);
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		// Clean up any tokens we created.
		$query  = new PaymentTokenQuery(
			array(
				'customer_id' => self::$customer_id,
				'number'      => 100,
			)
		);

		if ( ! empty( $query->items ) ) {
			foreach ( $query->items as $token ) {
				$query->delete_item( $token->id );
			}
		}

		// Clean up customer meta.
		edd_delete_customer_meta( self::$customer_id, Customer::META_KEY_LIVE );
		edd_delete_customer_meta( self::$customer_id, Customer::META_KEY_TEST );

		parent::tearDown();
	}

	/**
	 * The edd_payment_tokens table should exist after EDD installation.
	 */
	public function test_payment_tokens_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'edd_payment_tokens';
		$result     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		$this->assertSame( $table_name, $result );
	}

	/**
	 * Inserting a payment token should succeed.
	 */
	public function test_insert_payment_token() {
		$query = new PaymentTokenQuery();
		$id    = $query->add_item(
			array(
				'customer_id'         => self::$customer_id,
				'gateway'             => 'paypal',
				'token_id'            => 'PT-TEST-INSERT-001',
				'gateway_customer_id' => 'CUST-PP-001',
				'type'                => 'paypal',
				'label'               => 'PayPal - user@example.com',
				'payment_source'      => '{"paypal":{"email_address":"user@example.com"}}',
				'mode'                => 'live',
				'status'              => 'active',
			)
		);

		$this->assertNotFalse( $id );
		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Querying by customer ID should return matching tokens.
	 */
	public function test_query_by_customer_id() {
		$query = new PaymentTokenQuery();
		$query->add_item(
			array(
				'customer_id'         => self::$customer_id,
				'gateway'             => 'paypal',
				'token_id'            => 'PT-TEST-QUERY-001',
				'gateway_customer_id' => 'CUST-PP-002',
				'type'                => 'paypal',
				'label'               => 'PayPal - test@example.com',
				'mode'                => 'live',
				'status'              => 'active',
			)
		);

		$results = new PaymentTokenQuery(
			array(
				'customer_id' => self::$customer_id,
				'status'      => 'active',
			)
		);

		$this->assertNotEmpty( $results->items );
		$this->assertInstanceOf( PaymentToken::class, $results->items[0] );
		$this->assertEquals( self::$customer_id, $results->items[0]->customer_id );
	}

	/**
	 * Querying by token_id should return the matching token.
	 */
	public function test_query_by_token_id() {
		$query = new PaymentTokenQuery();
		$query->add_item(
			array(
				'customer_id'         => self::$customer_id,
				'gateway'             => 'paypal',
				'token_id'            => 'PT-TEST-BYTOKEN-001',
				'gateway_customer_id' => 'CUST-PP-003',
				'type'                => 'paypal',
				'label'               => 'PayPal - bytoken@example.com',
				'mode'                => 'live',
				'status'              => 'active',
			)
		);

		$results = new PaymentTokenQuery(
			array(
				'token_id' => 'PT-TEST-BYTOKEN-001',
				'number'   => 1,
			)
		);

		$this->assertNotEmpty( $results->items );
		$this->assertSame( 'PT-TEST-BYTOKEN-001', $results->items[0]->token_id );
	}

	/**
	 * Soft-deleting a token should set status to 'deleted'.
	 */
	public function test_soft_delete_token() {
		$query = new PaymentTokenQuery();
		$id    = $query->add_item(
			array(
				'customer_id'         => self::$customer_id,
				'gateway'             => 'paypal',
				'token_id'            => 'PT-TEST-DELETE-001',
				'gateway_customer_id' => 'CUST-PP-004',
				'type'                => 'paypal',
				'label'               => 'PayPal - delete@example.com',
				'mode'                => 'live',
				'status'              => 'active',
			)
		);

		$query->update_item(
			$id,
			array(
				'status' => 'deleted',
			)
		);

		$token = $query->get_item( $id );
		$this->assertSame( 'deleted', $token->status );
	}

	/**
	 * Active status filter should exclude deleted tokens.
	 */
	public function test_active_filter_excludes_deleted() {
		$query = new PaymentTokenQuery();

		$query->add_item(
			array(
				'customer_id'         => self::$customer_id,
				'gateway'             => 'paypal',
				'token_id'            => 'PT-TEST-ACTIVE-001',
				'gateway_customer_id' => 'CUST-PP-005',
				'type'                => 'paypal',
				'label'               => 'Active token',
				'mode'                => 'live',
				'status'              => 'active',
			)
		);

		$deleted_id = $query->add_item(
			array(
				'customer_id'         => self::$customer_id,
				'gateway'             => 'paypal',
				'token_id'            => 'PT-TEST-DELETED-001',
				'gateway_customer_id' => 'CUST-PP-005',
				'type'                => 'paypal',
				'label'               => 'Deleted token',
				'mode'                => 'live',
				'status'              => 'deleted',
			)
		);

		$active = new PaymentTokenQuery(
			array(
				'customer_id' => self::$customer_id,
				'status'      => 'active',
			)
		);

		foreach ( $active->items as $item ) {
			$this->assertNotEquals( $deleted_id, $item->id );
			$this->assertSame( 'active', $item->status );
		}
	}

	/**
	 * Gateway customer ID should be stored in and retrieved from customer meta.
	 */
	public function test_save_and_get_paypal_customer_id() {
		add_filter( 'edd_is_test_mode', '__return_false' );

		Customer::save_id( self::$customer_id, 'PP-CUST-LIVE-001' );

		$retrieved = Customer::get_id( self::$customer_id );
		$this->assertSame( 'PP-CUST-LIVE-001', $retrieved );

		remove_filter( 'edd_is_test_mode', '__return_false' );
	}

	/**
	 * Sandbox customer ID should use the test meta key.
	 */
	public function test_sandbox_customer_id_uses_test_meta_key() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		Customer::save_id( self::$customer_id, 'PP-CUST-SANDBOX-001' );

		$retrieved = Customer::get_id( self::$customer_id );
		$this->assertSame( 'PP-CUST-SANDBOX-001', $retrieved );

		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * Customer::save_id should not overwrite an existing value.
	 */
	public function test_maybe_save_does_not_overwrite() {
		add_filter( 'edd_is_test_mode', '__return_false' );

		Customer::save_id( self::$customer_id, 'PP-CUST-FIRST' );
		Customer::save_id( self::$customer_id, 'PP-CUST-SECOND' );

		$retrieved = Customer::get_id( self::$customer_id );
		$this->assertSame( 'PP-CUST-FIRST', $retrieved );

		remove_filter( 'edd_is_test_mode', '__return_false' );
	}

	/**
	 * save_token_from_response should create a local token with correct fields.
	 */
	public function test_save_token_from_response() {
		add_filter( 'edd_is_test_mode', '__return_false' );

		$response = array(
			'id'             => 'PT-FROM-RESPONSE-001',
			'status'         => 'VAULTED',
			'customer'       => array(
				'id' => 'PP-CUST-RESP-001',
			),
			'payment_source' => array(
				'paypal' => array(
					'email_address' => 'vault@example.com',
				),
			),
		);

		$local_id = Vault::save_token_from_response( $response, self::$customer_id );

		$this->assertNotFalse( $local_id );

		$query = new PaymentTokenQuery();
		$token = $query->get_item( $local_id );

		$this->assertSame( 'PT-FROM-RESPONSE-001', $token->token_id );
		$this->assertSame( 'PP-CUST-RESP-001', $token->gateway_customer_id );
		$this->assertSame( 'PayPal - vault@example.com', $token->label );
		$this->assertSame( 'paypal', $token->type );
		$this->assertSame( 'paypal', $token->gateway );
		$this->assertSame( 'active', $token->status );
		$this->assertSame( 'live', $token->mode );

		remove_filter( 'edd_is_test_mode', '__return_false' );
	}

	/**
	 * save_token_from_response with card source should detect 'card' type.
	 */
	public function test_save_token_from_response_card_type() {
		$response = array(
			'id'             => 'PT-CARD-001',
			'status'         => 'VAULTED',
			'customer'       => array(
				'id' => 'PP-CUST-CARD-001',
			),
			'payment_source' => array(
				'card' => array(
					'brand'       => 'VISA',
					'last_digits' => '1234',
				),
			),
		);

		$local_id = Vault::save_token_from_response( $response, self::$customer_id );

		$query = new PaymentTokenQuery();
		$token = $query->get_item( $local_id );

		$this->assertSame( 'card', $token->type );
		$this->assertSame( 'Visa ending in 1234', $token->label );
	}

	/**
	 * save_token_from_response should return false when the token ID is missing.
	 *
	 * The customer.id guard was relaxed: PayPal can legitimately return a
	 * vault token without a customer ID (the token itself is still usable
	 * for renewals via /v3/paypal/orders/recurring). We log a warning and
	 * persist the row regardless. Only an empty `id` bails.
	 */
	public function test_save_token_from_response_invalid() {
		$result = Vault::save_token_from_response( array(), self::$customer_id );
		$this->assertFalse( $result );
	}

	/**
	 * save_token_from_response persists the row even when customer.id is missing.
	 */
	public function test_save_token_from_response_without_customer_id() {
		$result = Vault::save_token_from_response(
			array(
				'id'             => 'PT-NO-CUSTOMER',
				'payment_source' => array(
					'paypal' => array( 'email_address' => 'noCustomer@example.com' ),
				),
			),
			self::$customer_id
		);

		$this->assertNotFalse( $result );
		$this->assertIsNumeric( $result );
	}

	/**
	 * get_tokens_for_customer should return tokens via the Vault helper.
	 */
	public function test_get_tokens_for_customer() {
		add_filter( 'edd_is_test_mode', '__return_false' );

		$query = new PaymentTokenQuery();
		$query->add_item(
			array(
				'customer_id'         => self::$customer_id,
				'gateway'             => 'paypal',
				'token_id'            => 'PT-HELPER-001',
				'gateway_customer_id' => 'CUST-PP-HELPER',
				'type'                => 'paypal',
				'label'               => 'PayPal - helper@example.com',
				'mode'                => 'live',
				'status'              => 'active',
			)
		);

		$tokens = Vault::get_tokens_for_customer( self::$customer_id );

		$this->assertNotEmpty( $tokens );
		$this->assertSame( 'PT-HELPER-001', $tokens[0]->token_id );

		remove_filter( 'edd_is_test_mode', '__return_false' );
	}

	/**
	 * get_token_by_token_id should find the correct token.
	 */
	public function test_get_token_by_token_id() {
		$query = new PaymentTokenQuery();
		$query->add_item(
			array(
				'customer_id'         => self::$customer_id,
				'gateway'             => 'paypal',
				'token_id'            => 'PT-LOOKUP-001',
				'gateway_customer_id' => 'CUST-PP-LOOKUP',
				'type'                => 'paypal',
				'label'               => 'PayPal - lookup@example.com',
				'mode'                => 'live',
				'status'              => 'active',
			)
		);

		$token = Vault::get_token_by_token_id( 'PT-LOOKUP-001' );

		$this->assertInstanceOf( PaymentToken::class, $token );
		$this->assertSame( 'PT-LOOKUP-001', $token->token_id );
	}

	/**
	 * get_token_by_token_id should return false when not found.
	 */
	public function test_get_token_by_token_id_not_found() {
		$token = Vault::get_token_by_token_id( 'PT-NONEXISTENT' );
		$this->assertFalse( $token );
	}

	/**
	 * The PaymentToken object should decode payment source JSON.
	 */
	public function test_payment_token_get_payment_source_data() {
		$query = new PaymentTokenQuery();
		$id    = $query->add_item(
			array(
				'customer_id'         => self::$customer_id,
				'gateway'             => 'paypal',
				'token_id'            => 'PT-SOURCE-DATA-001',
				'gateway_customer_id' => 'CUST-PP-SOURCE',
				'type'                => 'paypal',
				'label'               => 'PayPal',
				'payment_source'      => '{"paypal":{"email_address":"source@example.com"}}',
				'mode'                => 'live',
				'status'              => 'active',
			)
		);

		$token = $query->get_item( $id );
		$data  = $token->get_payment_source_data();

		$this->assertIsArray( $data );
		$this->assertSame( 'source@example.com', $data['paypal']['email_address'] );
	}

	/**
	 * PaymentToken with empty payment_source should return null.
	 */
	public function test_payment_token_empty_payment_source() {
		$query = new PaymentTokenQuery();
		$id    = $query->add_item(
			array(
				'customer_id'         => self::$customer_id,
				'gateway'             => 'paypal',
				'token_id'            => 'PT-EMPTY-SOURCE-001',
				'gateway_customer_id' => 'CUST-PP-EMPTY',
				'type'                => 'paypal',
				'label'               => 'PayPal',
				'payment_source'      => '',
				'mode'                => 'live',
				'status'              => 'active',
			)
		);

		$token = $query->get_item( $id );
		$this->assertNull( $token->get_payment_source_data() );
	}

	/**
	 * Mode filtering should isolate sandbox from live tokens.
	 */
	public function test_mode_filtering() {
		$query = new PaymentTokenQuery();

		$query->add_item(
			array(
				'customer_id'         => self::$customer_id,
				'gateway'             => 'paypal',
				'token_id'            => 'PT-LIVE-001',
				'gateway_customer_id' => 'CUST-PP-MODE',
				'type'                => 'paypal',
				'label'               => 'Live token',
				'mode'                => 'live',
				'status'              => 'active',
			)
		);

		$query->add_item(
			array(
				'customer_id'         => self::$customer_id,
				'gateway'             => 'paypal',
				'token_id'            => 'PT-SANDBOX-001',
				'gateway_customer_id' => 'CUST-PP-MODE',
				'type'                => 'paypal',
				'label'               => 'Sandbox token',
				'mode'                => 'sandbox',
				'status'              => 'active',
			)
		);

		$live_results = new PaymentTokenQuery(
			array(
				'customer_id' => self::$customer_id,
				'mode'        => 'live',
				'status'      => 'active',
			)
		);

		$sandbox_results = new PaymentTokenQuery(
			array(
				'customer_id' => self::$customer_id,
				'mode'        => 'sandbox',
				'status'      => 'active',
			)
		);

		// Each mode should only have its own tokens.
		foreach ( $live_results->items as $item ) {
			$this->assertSame( 'live', $item->mode );
		}
		foreach ( $sandbox_results->items as $item ) {
			$this->assertSame( 'sandbox', $item->mode );
		}
	}

	/**
	 * charge_vault should return a WP_Error when vault_id is missing.
	 */
	public function test_charge_vault_missing_vault_id() {
		$result = Vault::charge_vault(
			array(
				'amount' => '29.99',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_arguments', $result->get_error_code() );
	}

	/**
	 * charge_vault should return a WP_Error when amount is missing.
	 */
	public function test_charge_vault_missing_amount() {
		$result = Vault::charge_vault(
			array(
				'vault_id' => 'PT-TEST',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_arguments', $result->get_error_code() );
	}
}
