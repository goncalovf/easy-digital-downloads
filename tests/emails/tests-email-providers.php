<?php
namespace EDD\Tests\Emails;

use EDD\Emails\Providers\Provider;
use EDD\Emails\Providers\SendGrid;
use EDD\Emails\Providers\Mailgun;
use EDD\Emails\Providers\SES;
use EDD\Emails\Providers\SendLayer;
use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * Email Providers Tests
 *
 * @group edd_emails
 * @group edd_email_providers
 */
class EmailProviders extends EDD_UnitTestCase {

	/**
	 * Reset the provider cache before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		Provider::reset();
	}

	/**
	 * Reset the provider cache after each test.
	 */
	public function tearDown(): void {
		Provider::reset();
		parent::tearDown();
	}

	/**
	 * Test get_available_providers returns all 4 default providers.
	 *
	 * @covers \EDD\Emails\Providers\Provider::get_available_providers
	 */
	public function test_get_available_providers_returns_all_defaults() {
		$providers = Provider::get_available_providers();

		$this->assertCount( 4, $providers );
		$this->assertArrayHasKey( 'sendgrid', $providers );
		$this->assertArrayHasKey( 'mailgun', $providers );
		$this->assertArrayHasKey( 'ses', $providers );
		$this->assertArrayHasKey( 'sendlayer', $providers );
	}

	/**
	 * Test get_available_providers returns correct instance types.
	 *
	 * @covers \EDD\Emails\Providers\Provider::get_available_providers
	 */
	public function test_get_available_providers_returns_correct_types() {
		$providers = Provider::get_available_providers();

		$this->assertInstanceOf( SendGrid::class, $providers['sendgrid'] );
		$this->assertInstanceOf( Mailgun::class, $providers['mailgun'] );
		$this->assertInstanceOf( SES::class, $providers['ses'] );
		$this->assertInstanceOf( SendLayer::class, $providers['sendlayer'] );
	}

	/**
	 * Test get_provider_by_id returns correct provider.
	 *
	 * @covers \EDD\Emails\Providers\Provider::get_provider_by_id
	 */
	public function test_get_provider_by_id() {
		$provider = Provider::get_provider_by_id( 'sendgrid' );

		$this->assertInstanceOf( SendGrid::class, $provider );
		$this->assertSame( 'sendgrid', $provider->get_id() );
		$this->assertSame( 'SendGrid', $provider->get_name() );
	}

	/**
	 * Test get_provider_by_id returns null for unknown provider.
	 *
	 * @covers \EDD\Emails\Providers\Provider::get_provider_by_id
	 */
	public function test_get_provider_by_id_unknown_returns_null() {
		$provider = Provider::get_provider_by_id( 'nonexistent' );

		$this->assertNull( $provider );
	}

	/**
	 * Test edd_email_providers filter can add a provider.
	 *
	 * @covers \EDD\Emails\Providers\Provider::get_available_providers
	 */
	public function test_edd_email_providers_filter_add() {
		add_filter(
			'edd_email_providers',
			function ( $providers ) {
				$providers['custom'] = new SendGrid(); // Reuse for simplicity.
				return $providers;
			}
		);

		$providers = Provider::get_available_providers();

		$this->assertCount( 5, $providers );
		$this->assertArrayHasKey( 'custom', $providers );
	}

	/**
	 * Test edd_email_providers filter can remove a provider.
	 *
	 * @covers \EDD\Emails\Providers\Provider::get_available_providers
	 */
	public function test_edd_email_providers_filter_remove() {
		add_filter(
			'edd_email_providers',
			function ( $providers ) {
				unset( $providers['mailgun'] );
				return $providers;
			}
		);

		$providers = Provider::get_available_providers();

		$this->assertCount( 3, $providers );
		$this->assertArrayNotHasKey( 'mailgun', $providers );
	}

	/**
	 * Test SendGrid can_handle_bounce detects correct payload.
	 *
	 * @covers \EDD\Emails\Providers\SendGrid::can_handle_bounce
	 */
	public function test_sendgrid_can_handle_bounce_correct_payload() {
		$provider = new SendGrid();

		$this->assertTrue(
			$provider->can_handle_bounce(
				array(
					'event'  => 'bounce',
					'email'  => 'test@example.com',
					'reason' => '550 User not found',
				)
			)
		);
	}

	/**
	 * Test SendGrid can_handle_bounce rejects incorrect payload.
	 *
	 * @covers \EDD\Emails\Providers\SendGrid::can_handle_bounce
	 */
	public function test_sendgrid_can_handle_bounce_incorrect_payload() {
		$provider = new SendGrid();

		$this->assertFalse(
			$provider->can_handle_bounce(
				array(
					'event'     => 'failed',
					'recipient' => 'test@example.com',
				)
			)
		);
	}

	/**
	 * Test Mailgun can_handle_bounce detects correct payload.
	 *
	 * @covers \EDD\Emails\Providers\Mailgun::can_handle_bounce
	 */
	public function test_mailgun_can_handle_bounce_correct_payload() {
		$provider = new Mailgun();

		$this->assertTrue(
			$provider->can_handle_bounce(
				array(
					'event'     => 'failed',
					'recipient' => 'test@example.com',
					'error'     => '550 Mailbox not found',
				)
			)
		);
	}

	/**
	 * Test Mailgun can_handle_bounce rejects incorrect payload.
	 *
	 * @covers \EDD\Emails\Providers\Mailgun::can_handle_bounce
	 */
	public function test_mailgun_can_handle_bounce_incorrect_payload() {
		$provider = new Mailgun();

		$this->assertFalse(
			$provider->can_handle_bounce(
				array(
					'event' => 'bounce',
					'email' => 'test@example.com',
				)
			)
		);
	}

	/**
	 * Test SES can_handle_bounce detects correct payload.
	 *
	 * @covers \EDD\Emails\Providers\SES::can_handle_bounce
	 */
	public function test_ses_can_handle_bounce_correct_payload() {
		$provider = new SES();

		$this->assertTrue(
			$provider->can_handle_bounce(
				array(
					'Type'    => 'Notification',
					'Message' => '{}',
				)
			)
		);
	}

	/**
	 * Test SES can_handle_bounce rejects incorrect payload.
	 *
	 * @covers \EDD\Emails\Providers\SES::can_handle_bounce
	 */
	public function test_ses_can_handle_bounce_incorrect_payload() {
		$provider = new SES();

		$this->assertFalse(
			$provider->can_handle_bounce(
				array(
					'event' => 'bounce',
					'email' => 'test@example.com',
				)
			)
		);
	}

	/**
	 * Test SendLayer can_handle_bounce detects correct payload.
	 *
	 * @covers \EDD\Emails\Providers\SendLayer::can_handle_bounce
	 */
	public function test_sendlayer_can_handle_bounce_correct_payload() {
		$provider = new SendLayer();

		$this->assertTrue(
			$provider->can_handle_bounce(
				array(
					'EventData' => array(
						'Event' => 'bounced',
					),
				)
			)
		);
	}

	/**
	 * Test SendLayer can_handle_bounce rejects incorrect payload.
	 *
	 * @covers \EDD\Emails\Providers\SendLayer::can_handle_bounce
	 */
	public function test_sendlayer_can_handle_bounce_incorrect_payload() {
		$provider = new SendLayer();

		$this->assertFalse(
			$provider->can_handle_bounce(
				array(
					'event' => 'bounce',
					'email' => 'test@example.com',
				)
			)
		);
	}

	/**
	 * Test get_provider_for_bounce selects SendGrid.
	 *
	 * @covers \EDD\Emails\Providers\Provider::get_provider_for_bounce
	 */
	public function test_get_provider_for_bounce_sendgrid() {
		$provider = Provider::get_provider_for_bounce(
			array(
				'event'  => 'bounce',
				'email'  => 'test@example.com',
				'reason' => 'User not found',
			)
		);

		$this->assertInstanceOf( SendGrid::class, $provider );
	}

	/**
	 * Test get_provider_for_bounce selects Mailgun.
	 *
	 * @covers \EDD\Emails\Providers\Provider::get_provider_for_bounce
	 */
	public function test_get_provider_for_bounce_mailgun() {
		$provider = Provider::get_provider_for_bounce(
			array(
				'event'     => 'failed',
				'recipient' => 'test@example.com',
				'error'     => 'Mailbox not found',
			)
		);

		$this->assertInstanceOf( Mailgun::class, $provider );
	}

	/**
	 * Test get_provider_for_bounce selects SES.
	 *
	 * @covers \EDD\Emails\Providers\Provider::get_provider_for_bounce
	 */
	public function test_get_provider_for_bounce_ses() {
		$provider = Provider::get_provider_for_bounce(
			array(
				'Type'    => 'Notification',
				'Message' => '{}',
			)
		);

		$this->assertInstanceOf( SES::class, $provider );
	}

	/**
	 * Test get_provider_for_bounce selects SendLayer.
	 *
	 * @covers \EDD\Emails\Providers\Provider::get_provider_for_bounce
	 */
	public function test_get_provider_for_bounce_sendlayer() {
		$provider = Provider::get_provider_for_bounce(
			array(
				'EventData' => array(
					'Event' => 'bounced',
				),
			)
		);

		$this->assertInstanceOf( SendLayer::class, $provider );
	}

	/**
	 * Test get_provider_for_bounce returns null for unknown payload.
	 *
	 * @covers \EDD\Emails\Providers\Provider::get_provider_for_bounce
	 */
	public function test_get_provider_for_bounce_unknown_returns_null() {
		$provider = Provider::get_provider_for_bounce(
			array(
				'unknown_field' => 'unknown_value',
			)
		);

		$this->assertNull( $provider );
	}

	/*
	|--------------------------------------------------------------------------
	| parse_bounce Tests
	|--------------------------------------------------------------------------
	*/

	/**
	 * Test SES parse_bounce returns null when Message key is missing.
	 *
	 * @covers \EDD\Emails\Providers\SES::parse_bounce
	 */
	public function test_ses_parse_bounce_missing_message_returns_null() {
		$provider = new SES();

		$this->assertNull(
			$provider->parse_bounce(
				array(
					'Type' => 'Notification',
				)
			)
		);
	}

	/**
	 * Test SES parse_bounce returns null when Message is not valid JSON.
	 *
	 * @covers \EDD\Emails\Providers\SES::parse_bounce
	 */
	public function test_ses_parse_bounce_invalid_json_returns_null() {
		$provider = new SES();

		$this->assertNull(
			$provider->parse_bounce(
				array(
					'Type'    => 'Notification',
					'Message' => 'not-json',
				)
			)
		);
	}

	/**
	 * Test SES parse_bounce returns null when decoded message has no bounce key.
	 *
	 * @covers \EDD\Emails\Providers\SES::parse_bounce
	 */
	public function test_ses_parse_bounce_no_bounce_key_returns_null() {
		$provider = new SES();

		$this->assertNull(
			$provider->parse_bounce(
				array(
					'Type'    => 'Notification',
					'Message' => wp_json_encode( array( 'notificationType' => 'Complaint' ) ),
				)
			)
		);
	}

	/**
	 * Test SES parse_bounce returns null when bouncedRecipients is empty.
	 *
	 * @covers \EDD\Emails\Providers\SES::parse_bounce
	 */
	public function test_ses_parse_bounce_empty_recipients_returns_null() {
		$provider = new SES();

		$this->assertNull(
			$provider->parse_bounce(
				array(
					'Type'    => 'Notification',
					'Message' => wp_json_encode(
						array(
							'bounce' => array(
								'bounceType'       => 'Permanent',
								'bouncedRecipients' => array(),
							),
						)
					),
				)
			)
		);
	}

	/**
	 * Test SES parse_bounce returns null when no matching email log exists.
	 *
	 * @covers \EDD\Emails\Providers\SES::parse_bounce
	 */
	public function test_ses_parse_bounce_no_matching_email_returns_null() {
		$provider = new SES();

		$this->assertNull(
			$provider->parse_bounce(
				array(
					'Type'    => 'Notification',
					'Message' => wp_json_encode(
						array(
							'bounce' => array(
								'bounceType'       => 'Permanent',
								'bouncedRecipients' => array(
									array( 'emailAddress' => 'nonexistent@example.com' ),
								),
							),
						)
					),
				)
			)
		);
	}

	/**
	 * Test SES parse_bounce returns correct data for a valid bounce.
	 *
	 * @covers \EDD\Emails\Providers\SES::parse_bounce
	 */
	public function test_ses_parse_bounce_valid_bounce() {
		$email    = 'ses-bounce@example.com';
		$email_id = self::edd()->email_logs->create(
			array(
				'email' => $email,
			)
		);

		$provider = new SES();
		$result   = $provider->parse_bounce(
			array(
				'Type'    => 'Notification',
				'Message' => wp_json_encode(
					array(
						'bounce' => array(
							'bounceType'       => 'Permanent',
							'bouncedRecipients' => array(
								array( 'emailAddress' => $email ),
							),
						),
					)
				),
			)
		);

		$this->assertNotNull( $result );
		$this->assertSame( $email_id, $result['email_id'] );
		$this->assertSame( 'Permanent', $result['reason'] );
	}

	/**
	 * Test SendGrid parse_bounce returns null when no matching email log exists.
	 *
	 * @covers \EDD\Emails\Providers\SendGrid::parse_bounce
	 */
	public function test_sendgrid_parse_bounce_no_matching_email_returns_null() {
		$provider = new SendGrid();

		$this->assertNull(
			$provider->parse_bounce(
				array(
					'event'  => 'bounce',
					'email'  => 'nonexistent@example.com',
					'reason' => '550 User not found',
				)
			)
		);
	}

	/**
	 * Test SendGrid parse_bounce returns correct data for a valid bounce.
	 *
	 * @covers \EDD\Emails\Providers\SendGrid::parse_bounce
	 */
	public function test_sendgrid_parse_bounce_valid_bounce() {
		$email    = 'sendgrid-bounce@example.com';
		$email_id = self::edd()->email_logs->create(
			array(
				'email' => $email,
			)
		);

		$provider = new SendGrid();
		$result   = $provider->parse_bounce(
			array(
				'event'  => 'bounce',
				'email'  => $email,
				'reason' => '550 User not found',
			)
		);

		$this->assertNotNull( $result );
		$this->assertSame( $email_id, $result['email_id'] );
		$this->assertSame( '550 User not found', $result['reason'] );
	}

	/**
	 * Test Mailgun parse_bounce returns null when no matching email log exists.
	 *
	 * @covers \EDD\Emails\Providers\Mailgun::parse_bounce
	 */
	public function test_mailgun_parse_bounce_no_matching_email_returns_null() {
		$provider = new Mailgun();

		$this->assertNull(
			$provider->parse_bounce(
				array(
					'event'     => 'failed',
					'recipient' => 'nonexistent@example.com',
					'error'     => '550 Mailbox not found',
				)
			)
		);
	}

	/**
	 * Test Mailgun parse_bounce returns correct data for a valid bounce.
	 *
	 * @covers \EDD\Emails\Providers\Mailgun::parse_bounce
	 */
	public function test_mailgun_parse_bounce_valid_bounce() {
		$email    = 'mailgun-bounce@example.com';
		$email_id = self::edd()->email_logs->create(
			array(
				'email' => $email,
			)
		);

		$provider = new Mailgun();
		$result   = $provider->parse_bounce(
			array(
				'event'     => 'failed',
				'recipient' => $email,
				'error'     => '550 Mailbox not found',
			)
		);

		$this->assertNotNull( $result );
		$this->assertSame( $email_id, $result['email_id'] );
		$this->assertSame( '550 Mailbox not found', $result['reason'] );
	}

	/**
	 * Test SendLayer parse_bounce returns null when no matching email log exists.
	 *
	 * @covers \EDD\Emails\Providers\SendLayer::parse_bounce
	 */
	public function test_sendlayer_parse_bounce_no_matching_email_returns_null() {
		$provider = new SendLayer();

		$this->assertNull(
			$provider->parse_bounce(
				array(
					'EventData' => array(
						'Event'                => 'bounced',
						'BouncedEmailAddress'  => array(
							'EmailAddress' => 'nonexistent@example.com',
						),
						'Reason'               => 'Mailbox full',
					),
				)
			)
		);
	}

	/**
	 * Test SendLayer parse_bounce returns correct data for a valid bounce.
	 *
	 * @covers \EDD\Emails\Providers\SendLayer::parse_bounce
	 */
	public function test_sendlayer_parse_bounce_valid_bounce() {
		$email    = 'sendlayer-bounce@example.com';
		$email_id = self::edd()->email_logs->create(
			array(
				'email' => $email,
			)
		);

		$provider = new SendLayer();
		$result   = $provider->parse_bounce(
			array(
				'EventData' => array(
					'Event'                => 'bounced',
					'BouncedEmailAddress'  => array(
						'EmailAddress' => $email,
					),
					'Reason'               => 'Mailbox full',
				),
			)
		);

		$this->assertNotNull( $result );
		$this->assertSame( $email_id, $result['email_id'] );
		$this->assertSame( 'Mailbox full', $result['reason'] );
	}

	/**
	 * Test parse_bounce uses default reason when reason key is missing.
	 *
	 * @covers \EDD\Emails\Providers\SendGrid::parse_bounce
	 */
	public function test_parse_bounce_default_reason_when_missing() {
		$email    = 'default-reason@example.com';
		$email_id = self::edd()->email_logs->create(
			array(
				'email' => $email,
			)
		);

		$provider = new SendGrid();
		$result   = $provider->parse_bounce(
			array(
				'event' => 'bounce',
				'email' => $email,
			)
		);

		$this->assertNotNull( $result );
		$this->assertSame( 'Unknown bounce reason', $result['reason'] );
	}

	/**
	 * Test provider IDs and names are correct.
	 *
	 * @covers \EDD\Emails\Providers\SendGrid::get_id
	 * @covers \EDD\Emails\Providers\SendGrid::get_name
	 * @covers \EDD\Emails\Providers\Mailgun::get_id
	 * @covers \EDD\Emails\Providers\Mailgun::get_name
	 * @covers \EDD\Emails\Providers\SES::get_id
	 * @covers \EDD\Emails\Providers\SES::get_name
	 * @covers \EDD\Emails\Providers\SendLayer::get_id
	 * @covers \EDD\Emails\Providers\SendLayer::get_name
	 */
	public function test_provider_ids_and_names() {
		$providers = Provider::get_available_providers();

		$this->assertSame( 'sendgrid', $providers['sendgrid']->get_id() );
		$this->assertSame( 'SendGrid', $providers['sendgrid']->get_name() );

		$this->assertSame( 'mailgun', $providers['mailgun']->get_id() );
		$this->assertSame( 'Mailgun', $providers['mailgun']->get_name() );

		$this->assertSame( 'ses', $providers['ses']->get_id() );
		$this->assertSame( 'Amazon SES', $providers['ses']->get_name() );

		$this->assertSame( 'sendlayer', $providers['sendlayer']->get_id() );
		$this->assertSame( 'SendLayer', $providers['sendlayer']->get_name() );
	}

	/**
	 * Test reset clears the provider cache.
	 *
	 * @covers \EDD\Emails\Providers\Provider::reset
	 */
	public function test_reset_clears_cache() {
		// Load providers.
		$providers1 = Provider::get_available_providers();
		$this->assertCount( 4, $providers1 );

		// Reset and add filter to modify.
		Provider::reset();

		add_filter(
			'edd_email_providers',
			function ( $providers ) {
				unset( $providers['ses'] );
				return $providers;
			}
		);

		$providers2 = Provider::get_available_providers();
		$this->assertCount( 3, $providers2 );
		$this->assertArrayNotHasKey( 'ses', $providers2 );
	}
}
