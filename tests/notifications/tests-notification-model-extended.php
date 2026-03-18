<?php
/**
 * Extended Notification Model Tests
 *
 * Tests for the Notification model's toArray(), getIcon(), and source
 * property added as part of the notification system usability
 * improvements (Issue #2242).
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
 * @coversDefaultClass \EDD\Models\Notification
 * @group edd_notifications
 */
class NotificationModelExtendedTests extends EDD_UnitTestCase {

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
	 * Helper to insert and retrieve a Notification model.
	 *
	 * @param array $data
	 * @return Notification
	 */
	private function insertAndGetNotification( $data ) {
		$notificationId = EDD()->notifications->insert( $data );

		return new Notification( EDD()->notifications->get( $notificationId ) );
	}

	/**
	 * Tests that getIcon returns 'yes-alt' for success type.
	 *
	 * @covers \EDD\Models\Notification::getIcon
	 */
	public function test_getIcon_success_returns_yes_alt() {
		$notification = new Notification( array( 'type' => 'success' ) );
		$this->assertEquals( 'yes-alt', $notification->getIcon() );
	}

	/**
	 * Tests that getIcon returns 'warning' for warning type.
	 *
	 * @covers \EDD\Models\Notification::getIcon
	 */
	public function test_getIcon_warning_returns_warning() {
		$notification = new Notification( array( 'type' => 'warning' ) );
		$this->assertEquals( 'warning', $notification->getIcon() );
	}

	/**
	 * Tests that getIcon returns 'dismiss' for error type.
	 *
	 * @covers \EDD\Models\Notification::getIcon
	 */
	public function test_getIcon_error_returns_dismiss() {
		$notification = new Notification( array( 'type' => 'error' ) );
		$this->assertEquals( 'dismiss', $notification->getIcon() );
	}

	/**
	 * Tests that getIcon returns 'admin-generic' for info type.
	 *
	 * @covers \EDD\Models\Notification::getIcon
	 */
	public function test_getIcon_info_returns_admin_generic() {
		$notification = new Notification( array( 'type' => 'info' ) );
		$this->assertEquals( 'admin-generic', $notification->getIcon() );
	}

	/**
	 * Tests that getIcon defaults to 'yes-alt' for unknown type.
	 *
	 * @covers \EDD\Models\Notification::getIcon
	 */
	public function test_getIcon_unknown_type_returns_yes_alt() {
		$notification = new Notification( array( 'type' => 'unknown' ) );
		$this->assertEquals( 'yes-alt', $notification->getIcon() );
	}

	/**
	 * Tests that getIcon defaults to 'yes-alt' when type is null.
	 *
	 * @covers \EDD\Models\Notification::getIcon
	 */
	public function test_getIcon_null_type_returns_yes_alt() {
		$notification = new Notification( array() );
		$this->assertEquals( 'yes-alt', $notification->getIcon() );
	}

	/**
	 * Tests that toArray includes icon_name field.
	 *
	 * @covers \EDD\Models\Notification::toArray
	 */
	public function test_toArray_includes_icon_name() {
		$notification = $this->insertAndGetNotification( array(
			'title'     => 'Icon Test',
			'content'   => 'Content',
			'type'      => 'warning',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		$data = $notification->toArray();

		$this->assertArrayHasKey( 'icon_name', $data );
		$this->assertEquals( 'warning', $data['icon_name'] );
	}

	/**
	 * Tests that toArray includes relative_date field.
	 *
	 * @covers \EDD\Models\Notification::toArray
	 */
	public function test_toArray_includes_relative_date() {
		$notification = $this->insertAndGetNotification( array(
			'title'     => 'Date Test',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		$data = $notification->toArray();

		$this->assertArrayHasKey( 'relative_date', $data );
		$this->assertIsString( $data['relative_date'] );
		$this->assertStringContainsString( 'ago', $data['relative_date'] );
	}

	/**
	 * Tests that toArray includes all public properties.
	 *
	 * @covers \EDD\Models\Notification::toArray
	 */
	public function test_toArray_includes_all_public_properties() {
		$notification = $this->insertAndGetNotification( array(
			'title'     => 'Full Test',
			'content'   => 'Full content',
			'type'      => 'info',
			'source'    => 'local',
			'dismissed' => 0,
		) );

		$data = $notification->toArray();

		$expected_keys = array(
			'id',
			'title',
			'content',
			'type',
			'source',
			'dismissed',
			'date_created',
			'date_updated',
			'icon_name',
			'relative_date',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $data, "Expected key '{$key}' missing from toArray() result." );
		}
	}

	/**
	 * Tests that toArray preserves the correct property values.
	 *
	 * @covers \EDD\Models\Notification::toArray
	 */
	public function test_toArray_preserves_property_values() {
		$notification = $this->insertAndGetNotification( array(
			'title'     => 'Value Preservation Test',
			'content'   => 'Specific content value',
			'type'      => 'error',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		$data = $notification->toArray();

		$this->assertEquals( 'Value Preservation Test', $data['title'] );
		$this->assertEquals( 'Specific content value', $data['content'] );
		$this->assertEquals( 'error', $data['type'] );
		$this->assertEquals( 'api', $data['source'] );
		$this->assertFalse( $data['dismissed'] );
		$this->assertEquals( 'dismiss', $data['icon_name'] );
	}

	/**
	 * Tests that the source property defaults to 'api'.
	 *
	 * @covers \EDD\Models\Notification::__construct
	 */
	public function test_source_defaults_to_api() {
		$notification = new Notification( array() );
		$this->assertEquals( 'api', $notification->source );
	}

	/**
	 * Tests that the source property can be set to 'local'.
	 *
	 * @covers \EDD\Models\Notification::__construct
	 */
	public function test_source_can_be_set_to_local() {
		$notification = $this->insertAndGetNotification( array(
			'title'     => 'Local Source',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'local',
			'dismissed' => 0,
		) );

		$this->assertEquals( 'local', $notification->source );
	}

	/**
	 * Tests that the conditions property is properly cast from JSON.
	 *
	 * @covers \EDD\Models\Notification::castAttribute
	 */
	public function test_conditions_cast_from_json() {
		$conditions = array( 'pass-any' );

		$notification = $this->insertAndGetNotification( array(
			'title'      => 'Conditions Test',
			'content'    => 'Content',
			'type'       => 'success',
			'source'     => 'api',
			'dismissed'  => 0,
			'conditions' => $conditions,
		) );

		$this->assertIsArray( $notification->conditions );
		$this->assertEquals( $conditions, $notification->conditions );
	}

	/**
	 * Tests that null conditions remain null.
	 *
	 * @covers \EDD\Models\Notification::castAttribute
	 */
	public function test_null_conditions_remain_null() {
		$notification = $this->insertAndGetNotification( array(
			'title'      => 'Null Conditions',
			'content'    => 'Content',
			'type'       => 'success',
			'source'     => 'api',
			'dismissed'  => 0,
			'conditions' => null,
		) );

		$this->assertNull( $notification->conditions );
	}

	/**
	 * Tests that dismissed=1 is cast to boolean true.
	 *
	 * @covers \EDD\Models\Notification::castAttribute
	 */
	public function test_dismissed_one_is_cast_to_true() {
		$notification = $this->insertAndGetNotification( array(
			'title'     => 'Dismissed Cast',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 1,
		) );

		$this->assertTrue( $notification->dismissed );
		$this->assertIsBool( $notification->dismissed );
	}

	/**
	 * Tests that toArray with buttons includes them correctly.
	 *
	 * @covers \EDD\Models\Notification::toArray
	 */
	public function test_toArray_with_buttons() {
		$buttons = array(
			array(
				'type' => 'primary',
				'url'  => 'https://example.com',
				'text' => 'Click Here',
			),
			array(
				'type' => 'secondary',
				'url'  => 'https://example.com/docs',
				'text' => 'Learn More',
			),
		);

		$notification = $this->insertAndGetNotification( array(
			'title'     => 'Button Test',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
			'buttons'   => $buttons,
		) );

		$data = $notification->toArray();

		$this->assertArrayHasKey( 'buttons', $data );
		$this->assertIsArray( $data['buttons'] );
		$this->assertCount( 2, $data['buttons'] );
		$this->assertEquals( 'Click Here', $data['buttons'][0]['text'] );
		$this->assertEquals( 'Learn More', $data['buttons'][1]['text'] );
	}

	/**
	 * Tests that toArray does not include protected properties.
	 *
	 * @covers \EDD\Models\Notification::toArray
	 */
	public function test_toArray_excludes_protected_properties() {
		$notification = $this->insertAndGetNotification( array(
			'title'     => 'Protected Test',
			'content'   => 'Content',
			'type'      => 'success',
			'source'    => 'api',
			'dismissed' => 0,
		) );

		$data = $notification->toArray();

		$this->assertArrayNotHasKey( 'casts', $data );
	}

	/**
	 * Tests that unknown properties in constructor data are ignored.
	 *
	 * @covers \EDD\Models\Notification::__construct
	 */
	public function test_constructor_ignores_unknown_properties() {
		$notification = new Notification( array(
			'title'           => 'Unknown Props',
			'content'         => 'Content',
			'type'            => 'success',
			'unknown_field'   => 'should be ignored',
			'another_unknown' => 123,
		) );

		$this->assertEquals( 'Unknown Props', $notification->title );
		$this->assertFalse( property_exists( $notification, 'unknown_field' ) );
	}
}
