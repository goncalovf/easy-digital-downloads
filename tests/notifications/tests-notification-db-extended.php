<?php
/**
 * Extended Notification Database Tests
 *
 * Tests for the getNotifications(), getDismissedNotifications(), and
 * countActiveNotifications() methods added as part of the notification
 * system usability improvements (Issue #2242).
 *
 * @package   EDD\Tests\Notifications
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     3.6.6
 */
namespace EDD\Tests\Notifications;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;
use EDD\Models\Notification;

/**
 * @coversDefaultClass \EDD\Database\NotificationsDB
 * @group edd_notifications
 */
class NotificationDBExtendedTests extends EDD_UnitTestCase {

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

		// Clear any notification caches.
		wp_cache_delete( 'edd_active_notification_count', 'edd_notifications' );
	}

	/**
	 * Tests that getNotifications returns active notifications by default.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_returns_active_by_default() {
		EDD()->notifications->insert( array(
			'title'     => 'Active',
			'content'   => 'Active content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		EDD()->notifications->insert( array(
			'title'     => 'Dismissed',
			'content'   => 'Dismissed content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 1,
		) );

		$notifications = EDD()->notifications->getNotifications();

		$this->assertCount( 1, $notifications );
		$this->assertEquals( 'Active', $notifications[0]->title );
	}

	/**
	 * Tests that getNotifications with dismissed=1 returns only dismissed notifications.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_returns_dismissed_when_requested() {
		EDD()->notifications->insert( array(
			'title'     => 'Active',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		EDD()->notifications->insert( array(
			'title'     => 'Dismissed',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 1,
		) );

		$notifications = EDD()->notifications->getNotifications( array( 'dismissed' => 1 ) );

		$this->assertCount( 1, $notifications );
		$this->assertEquals( 'Dismissed', $notifications[0]->title );
	}

	/**
	 * Tests that getNotifications filters by source.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_filters_by_source() {
		EDD()->notifications->insert( array(
			'title'     => 'API Notification',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		EDD()->notifications->insert( array(
			'title'     => 'Local Notification',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'local',
			'dismissed' => 0,
		) );

		$notifications = EDD()->notifications->getNotifications( array( 'source' => 'local' ) );

		$this->assertCount( 1, $notifications );
		$this->assertEquals( 'Local Notification', $notifications[0]->title );
		$this->assertEquals( 'local', $notifications[0]->source );
	}

	/**
	 * Tests that getNotifications filters by type.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_filters_by_type() {
		EDD()->notifications->insert( array(
			'title'     => 'Warning',
			'content'   => 'Content',
			'type'      => 'warning',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		EDD()->notifications->insert( array(
			'title'     => 'Success',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		$notifications = EDD()->notifications->getNotifications( array( 'type' => 'warning' ) );

		$this->assertCount( 1, $notifications );
		$this->assertEquals( 'Warning', $notifications[0]->title );
		$this->assertEquals( 'warning', $notifications[0]->type );
	}

	/**
	 * Tests that getNotifications can combine source and type filters.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_combined_source_and_type() {
		EDD()->notifications->insert( array(
			'title'   => 'API Warning',
			'content' => 'Content',
			'type'    => 'warning',
			'source'  => 'api',
		) );

		EDD()->notifications->insert( array(
			'title'   => 'Local Warning',
			'content' => 'Content',
			'type'    => 'warning',
			'source'  => 'local',
		) );

		EDD()->notifications->insert( array(
			'title'   => 'Local Info',
			'content' => 'Content',
			'type'    => 'info',
			'source'  => 'local',
		) );

		$notifications = EDD()->notifications->getNotifications( array(
			'source' => 'local',
			'type'   => 'warning',
		) );

		$this->assertCount( 1, $notifications );
		$this->assertEquals( 'Local Warning', $notifications[0]->title );
	}

	/**
	 * Tests that getNotifications enforces date range for active notifications.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_active_enforces_start_date() {
		EDD()->notifications->insert( array(
			'title'     => 'Future Notification',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
			'start'     => gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) ),
		) );

		$notifications = EDD()->notifications->getNotifications();

		$this->assertCount( 0, $notifications );
	}

	/**
	 * Tests that getNotifications enforces end date for active notifications.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_active_enforces_end_date() {
		EDD()->notifications->insert( array(
			'title'     => 'Expired Notification',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
			'end'       => gmdate( 'Y-m-d H:i:s', strtotime( '-1 week' ) ),
		) );

		$notifications = EDD()->notifications->getNotifications();

		$this->assertCount( 0, $notifications );
	}

	/**
	 * Tests that getNotifications skips date checks for dismissed notifications.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_dismissed_skips_date_checks() {
		// Insert expired notification that was dismissed.
		EDD()->notifications->insert( array(
			'title'     => 'Expired Dismissed',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 1,
			'end'       => gmdate( 'Y-m-d H:i:s', strtotime( '-1 week' ) ),
		) );

		// Insert future start notification that was dismissed.
		EDD()->notifications->insert( array(
			'title'     => 'Future Dismissed',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 1,
			'start'     => gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) ),
		) );

		$notifications = EDD()->notifications->getNotifications( array( 'dismissed' => 1 ) );

		$this->assertCount( 2, $notifications );
	}

	/**
	 * Tests that getNotifications returns Notification model instances.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_returns_notification_models() {
		EDD()->notifications->insert( array(
			'title'     => 'Model Test',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		$notifications = EDD()->notifications->getNotifications();

		$this->assertCount( 1, $notifications );
		$this->assertInstanceOf( Notification::class, $notifications[0] );
	}

	/**
	 * Tests that getNotifications returns empty array when no matches.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_returns_empty_for_no_matches() {
		EDD()->notifications->insert( array(
			'title'     => 'Success Notification',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		$notifications = EDD()->notifications->getNotifications( array( 'type' => 'error' ) );

		$this->assertIsArray( $notifications );
		$this->assertCount( 0, $notifications );
	}

	/**
	 * Tests getDismissedNotifications convenience method.
	 *
	 * @covers \EDD\Database\NotificationsDB::getDismissedNotifications
	 */
	public function test_getDismissedNotifications_returns_dismissed_only() {
		EDD()->notifications->insert( array(
			'title'     => 'Active',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		EDD()->notifications->insert( array(
			'title'     => 'Dismissed 1',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 1,
		) );

		EDD()->notifications->insert( array(
			'title'     => 'Dismissed 2',
			'content'   => 'Content',
			'type'      => 'warning',
			'source'    => 'local',
			'dismissed' => 1,
		) );

		$dismissed = EDD()->notifications->getDismissedNotifications();

		$this->assertCount( 2, $dismissed );
		foreach ( $dismissed as $notification ) {
			$this->assertTrue( $notification->dismissed );
		}
	}

	/**
	 * Tests getDismissedNotifications returns empty when none dismissed.
	 *
	 * @covers \EDD\Database\NotificationsDB::getDismissedNotifications
	 */
	public function test_getDismissedNotifications_empty_when_none_dismissed() {
		EDD()->notifications->insert( array(
			'title'     => 'Active Only',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		$dismissed = EDD()->notifications->getDismissedNotifications();

		$this->assertIsArray( $dismissed );
		$this->assertCount( 0, $dismissed );
	}

	/**
	 * Tests that countActiveNotifications uses cache.
	 *
	 * @covers \EDD\Database\NotificationsDB::countActiveNotifications
	 */
	public function test_countActiveNotifications_caches_result() {
		EDD()->notifications->insert( array(
			'title'     => 'Count Test 1',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		// First call sets cache.
		$count = EDD()->notifications->countActiveNotifications();
		$this->assertEquals( 1, $count );

		// Verify cache is set.
		$cached = wp_cache_get( 'edd_active_notification_count', 'edd_notifications' );
		$this->assertEquals( 1, $cached );
	}

	/**
	 * Tests that inserting a notification clears the count cache.
	 *
	 * @covers \EDD\Database\NotificationsDB::insert
	 * @covers \EDD\Database\NotificationsDB::countActiveNotifications
	 */
	public function test_insert_clears_count_cache() {
		EDD()->notifications->insert( array(
			'title'     => 'First',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		// Warm the cache.
		$count = EDD()->notifications->countActiveNotifications();
		$this->assertEquals( 1, $count );

		// Insert another notification (this should clear cache).
		EDD()->notifications->insert( array(
			'title'     => 'Second',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		// Count should reflect the new total.
		$new_count = EDD()->notifications->countActiveNotifications();
		$this->assertEquals( 2, $new_count );
	}

	/**
	 * Tests that countActiveNotifications excludes dismissed.
	 *
	 * @covers \EDD\Database\NotificationsDB::countActiveNotifications
	 */
	public function test_countActiveNotifications_excludes_dismissed() {
		EDD()->notifications->insert( array(
			'title'     => 'Active',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		EDD()->notifications->insert( array(
			'title'     => 'Dismissed',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 1,
		) );

		$count = EDD()->notifications->countActiveNotifications();
		$this->assertEquals( 1, $count );
	}

	/**
	 * Tests that getNotifications with all valid types returns correctly.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_all_notification_types() {
		$types = array( 'success', 'warning', 'error', 'info' );

		foreach ( $types as $type ) {
			EDD()->notifications->insert( array(
				'title'     => "Type {$type}",
				'content'   => 'Content',
				'type'      => $type,
				'source'    => 'api',
				'dismissed' => 0,
			) );
		}

		foreach ( $types as $type ) {
			$notifications = EDD()->notifications->getNotifications( array( 'type' => $type ) );
			$this->assertCount( 1, $notifications, "Expected 1 notification of type {$type}" );
			$this->assertEquals( $type, $notifications[0]->type );
		}
	}

	/**
	 * Tests that getNotifications with dismissed=0 and source filter work together.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_active_filtered_by_source() {
		EDD()->notifications->insert( array(
			'title'     => 'Active API',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		EDD()->notifications->insert( array(
			'title'     => 'Active Local',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'local',
			'dismissed' => 0,
		) );

		EDD()->notifications->insert( array(
			'title'     => 'Dismissed API',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 1,
		) );

		$notifications = EDD()->notifications->getNotifications( array(
			'dismissed' => 0,
			'source'    => 'api',
		) );

		$this->assertCount( 1, $notifications );
		$this->assertEquals( 'Active API', $notifications[0]->title );
	}

	/**
	 * Tests that multiple notifications are returned and properly typed.
	 *
	 * @covers \EDD\Database\NotificationsDB::getNotifications
	 */
	public function test_getNotifications_multiple_results_all_typed() {
		for ( $i = 1; $i <= 5; $i++ ) {
			EDD()->notifications->insert( array(
				'title'     => "Notification {$i}",
				'content'   => "Content {$i}",
				'type'      => 'success',
				'source'    => 'api',
				'dismissed' => 0,
			) );
		}

		$notifications = EDD()->notifications->getNotifications();

		$this->assertCount( 5, $notifications );
		foreach ( $notifications as $notification ) {
			$this->assertInstanceOf( Notification::class, $notification );
			$this->assertFalse( $notification->dismissed );
		}
	}
}
