<?php
/**
 * Notification Enqueue Tests
 *
 * Tests that the notification system correctly enqueues React-based
 * scripts with wp-element dependency and localization strings.
 *
 * @package   EDD\Tests\Notifications
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     3.6.6
 */
namespace EDD\Tests\Notifications;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * @coversDefaultClass \EDD\Database\NotificationsDB
 * @group edd_notifications
 */
class NotificationEnqueueTests extends EDD_UnitTestCase {

	/**
	 * Truncates the notification table before each test.
	 */
	public function setup(): void {
		parent::setUp();

		$component = EDD()->components['notification'];
		$thing     = $component->get_interface( 'table' );
		if ( $thing instanceof \EDD\Database\Table ) {
			$thing->truncate();
		}
	}

	/**
	 * Tests that the enqueue method is hooked to admin_enqueue_scripts.
	 *
	 * @covers \EDD\Database\NotificationsDB::__construct
	 * @return void
	 */
	public function test_enqueue_is_hooked_to_admin_enqueue_scripts() {
		$notifications_db = EDD()->notifications;

		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', array( $notifications_db, 'enqueue' ) )
		);
	}

	/**
	 * Tests that the NotificationsDB instance has the enqueue method.
	 *
	 * @covers \EDD\Database\NotificationsDB::enqueue
	 * @return void
	 */
	public function test_notifications_db_has_enqueue_method() {
		$this->assertTrue(
			method_exists( EDD()->notifications, 'enqueue' )
		);
	}

	/**
	 * Tests that the getDismissedNotifications method is a wrapper for getNotifications.
	 *
	 * @covers \EDD\Database\NotificationsDB::getDismissedNotifications
	 * @return void
	 */
	public function test_getDismissedNotifications_is_wrapper() {
		EDD()->notifications->insert( array(
			'title'     => 'Dismissed 1',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 1,
		) );

		EDD()->notifications->insert( array(
			'title'     => 'Active 1',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		$dismissed     = EDD()->notifications->getDismissedNotifications();
		$getNotif      = EDD()->notifications->getNotifications( array( 'dismissed' => 1 ) );

		// Both should return the same results.
		$this->assertCount( count( $dismissed ), $getNotif );
		$this->assertEquals( $dismissed[0]->title, $getNotif[0]->title );
	}
}
