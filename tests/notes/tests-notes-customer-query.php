<?php
namespace EDD\Tests\Notes;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * Tests for the customer_id query parameter in the Note query class.
 *
 * @group edd_notes
 * @group edd_notes_db
 * @group database
 *
 * @coversDefaultClass \EDD\Database\Queries\Note
 */
class NotesCustomerQuery extends EDD_UnitTestCase {

	/**
	 * Customer fixture.
	 *
	 * @var \EDD_Customer
	 */
	protected static $customer;

	/**
	 * Order fixture.
	 *
	 * @var \EDD\Orders\Order
	 */
	protected static $order;

	/**
	 * Second order fixture.
	 *
	 * @var \EDD\Orders\Order
	 */
	protected static $order_2;

	/**
	 * Customer note IDs.
	 *
	 * @var array
	 */
	protected static $customer_note_ids = array();

	/**
	 * Manual order note IDs.
	 *
	 * @var array
	 */
	protected static $manual_order_note_ids = array();

	/**
	 * System order note IDs.
	 *
	 * @var array
	 */
	protected static $system_order_note_ids = array();

	/**
	 * Unrelated note ID.
	 *
	 * @var int
	 */
	protected static $unrelated_note_id;

	/**
	 * Set up fixtures once.
	 */
	public static function wpSetUpBeforeClass() {
		// Create a customer.
		self::$customer = parent::edd()->customer->create_and_get(
			array(
				'email' => 'testnotes@example.com',
			)
		);

		// Create two orders for this customer.
		self::$order = parent::edd()->order->create_and_get(
			array(
				'customer_id' => self::$customer->id,
				'status'      => 'complete',
			)
		);

		self::$order_2 = parent::edd()->order->create_and_get(
			array(
				'customer_id' => self::$customer->id,
				'status'      => 'complete',
			)
		);

		// Create customer notes.
		self::$customer_note_ids[] = edd_add_note(
			array(
				'object_id'   => self::$customer->id,
				'object_type' => 'customer',
				'content'     => 'Customer note 1',
				'user_id'     => 1,
			)
		);

		self::$customer_note_ids[] = edd_add_note(
			array(
				'object_id'   => self::$customer->id,
				'object_type' => 'customer',
				'content'     => 'Customer note 2',
				'user_id'     => 1,
			)
		);

		// Create manual order notes (user_id > 0).
		self::$manual_order_note_ids[] = edd_add_note(
			array(
				'object_id'   => self::$order->id,
				'object_type' => 'order',
				'content'     => 'Manual order note on order 1',
				'user_id'     => 1,
			)
		);

		self::$manual_order_note_ids[] = edd_add_note(
			array(
				'object_id'   => self::$order_2->id,
				'object_type' => 'order',
				'content'     => 'Manual order note on order 2',
				'user_id'     => 1,
			)
		);

		// Create system order notes (user_id = 0).
		self::$system_order_note_ids[] = edd_add_note(
			array(
				'object_id'   => self::$order->id,
				'object_type' => 'order',
				'content'     => 'Status changed to complete',
				'user_id'     => 0,
			)
		);

		self::$system_order_note_ids[] = edd_add_note(
			array(
				'object_id'   => self::$order_2->id,
				'object_type' => 'order',
				'content'     => 'PayPal Transaction ID: ABC123',
				'user_id'     => 0,
			)
		);

		// Create an unrelated note on a different object.
		self::$unrelated_note_id = edd_add_note(
			array(
				'object_id'   => 99999,
				'object_type' => 'order',
				'content'     => 'Note on a different order',
				'user_id'     => 1,
			)
		);
	}

	/**
	 * @covers ::query_by_customer
	 */
	public function test_customer_id_returns_customer_notes() {
		$notes = edd_get_notes(
			array(
				'customer_id' => self::$customer->id,
				'number'      => 100,
			)
		);

		$note_ids = array_map( 'intval', wp_list_pluck( $notes, 'id' ) );

		foreach ( self::$customer_note_ids as $id ) {
			$this->assertContains( $id, $note_ids, "Customer note {$id} should be included." );
		}
	}

	/**
	 * @covers ::query_by_customer
	 */
	public function test_customer_id_returns_manual_order_notes() {
		$notes = edd_get_notes(
			array(
				'customer_id' => self::$customer->id,
				'number'      => 100,
			)
		);

		$note_ids = array_map( 'intval', wp_list_pluck( $notes, 'id' ) );

		foreach ( self::$manual_order_note_ids as $id ) {
			$this->assertContains( $id, $note_ids, "Manual order note {$id} should be included." );
		}
	}

	/**
	 * @covers ::query_by_customer
	 */
	public function test_customer_id_excludes_system_order_notes() {
		$notes = edd_get_notes(
			array(
				'customer_id' => self::$customer->id,
				'number'      => 100,
			)
		);

		$note_ids = array_map( 'intval', wp_list_pluck( $notes, 'id' ) );

		foreach ( self::$system_order_note_ids as $id ) {
			$this->assertNotContains( $id, $note_ids, "System order note {$id} should be excluded." );
		}
	}

	/**
	 * @covers ::query_by_customer
	 */
	public function test_customer_id_excludes_unrelated_notes() {
		$notes = edd_get_notes(
			array(
				'customer_id' => self::$customer->id,
				'number'      => 100,
			)
		);

		$note_ids = array_map( 'intval', wp_list_pluck( $notes, 'id' ) );

		$this->assertNotContains( self::$unrelated_note_id, $note_ids, 'Unrelated note should be excluded.' );
	}

	/**
	 * @covers ::query_by_customer
	 */
	public function test_customer_id_returns_correct_total_count() {
		$notes = edd_get_notes(
			array(
				'customer_id' => self::$customer->id,
				'number'      => 100,
			)
		);

		// 2 customer notes + 2 manual order notes = 4.
		$this->assertCount( 4, $notes );
	}

	/**
	 * @covers ::query_by_customer
	 */
	public function test_customer_id_count_query() {
		$count = edd_count_notes(
			array(
				'customer_id' => self::$customer->id,
			)
		);

		// 2 customer notes + 2 manual order notes = 4.
		$this->assertSame( 4, $count );
	}

	/**
	 * @covers ::query_by_customer
	 */
	public function test_customer_id_with_pagination() {
		$page_1 = edd_get_notes(
			array(
				'customer_id' => self::$customer->id,
				'number'      => 2,
				'offset'      => 0,
				'order'       => 'desc',
			)
		);

		$page_2 = edd_get_notes(
			array(
				'customer_id' => self::$customer->id,
				'number'      => 2,
				'offset'      => 2,
				'order'       => 'desc',
			)
		);

		$this->assertCount( 2, $page_1 );
		$this->assertCount( 2, $page_2 );

		// Pages should not overlap.
		$page_1_ids = wp_list_pluck( $page_1, 'id' );
		$page_2_ids = wp_list_pluck( $page_2, 'id' );
		$this->assertEmpty( array_intersect( $page_1_ids, $page_2_ids ), 'Pages should not contain overlapping notes.' );
	}

	/**
	 * @covers ::query_by_customer
	 */
	public function test_customer_id_with_no_orders_returns_only_customer_notes() {
		// Create a customer with no orders.
		$customer = parent::edd()->customer->create_and_get(
			array(
				'email' => 'noorders@example.com',
			)
		);

		$note_id = edd_add_note(
			array(
				'object_id'   => $customer->id,
				'object_type' => 'customer',
				'content'     => 'Only customer note',
				'user_id'     => 1,
			)
		);

		$notes = edd_get_notes(
			array(
				'customer_id' => $customer->id,
				'number'      => 100,
			)
		);

		$this->assertCount( 1, $notes );
		$this->assertEquals( $note_id, $notes[0]->id );
	}

	/**
	 * @covers ::query_by_customer
	 */
	public function test_customer_id_with_no_notes_returns_empty() {
		$customer = parent::edd()->customer->create_and_get(
			array(
				'email' => 'nonotes@example.com',
			)
		);

		$notes = edd_get_notes(
			array(
				'customer_id' => $customer->id,
				'number'      => 100,
			)
		);

		$this->assertCount( 0, $notes );
	}

	/**
	 * @covers ::query_by_customer
	 */
	public function test_customer_id_notes_have_correct_object_types() {
		$notes = edd_get_notes(
			array(
				'customer_id' => self::$customer->id,
				'number'      => 100,
			)
		);

		$object_types = array_unique( wp_list_pluck( $notes, 'object_type' ) );
		sort( $object_types );

		$this->assertSame( array( 'customer', 'order' ), $object_types );
	}

	/**
	 * Verify that refund orders are excluded from the customer notes query.
	 *
	 * @covers ::query_by_customer
	 */
	public function test_customer_id_excludes_refund_order_notes() {
		// Create a refund order for the customer.
		$refund = parent::edd()->order->create_and_get(
			array(
				'customer_id' => self::$customer->id,
				'status'      => 'complete',
				'type'        => 'refund',
			)
		);

		$refund_note_id = edd_add_note(
			array(
				'object_id'   => $refund->id,
				'object_type' => 'order',
				'content'     => 'Refund note',
				'user_id'     => 1,
			)
		);

		$notes    = edd_get_notes(
			array(
				'customer_id' => self::$customer->id,
				'number'      => 100,
			)
		);
		$note_ids = array_map( 'intval', wp_list_pluck( $notes, 'id' ) );

		$this->assertNotContains( $refund_note_id, $note_ids, 'Refund order notes should be excluded.' );
	}
}
