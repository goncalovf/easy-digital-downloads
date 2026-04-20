<?php

namespace EDD\Tests\Users;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;
use EDD\Users\LoginLink\Token;
use EDD\Users\LoginLink\Utility;

class Functions extends EDD_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		edd_delete_option( 'login_link' );
		edd_delete_option( 'logged_in_only' );
		edd_delete_option( 'show_register_form' );
		wp_set_current_user( 0 );
	}

	public function tearDown(): void {
		edd_delete_option( 'login_link' );
		edd_delete_option( 'logged_in_only' );
		edd_delete_option( 'show_register_form' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_edd_connect_existing_customer_to_new_user() {
		$customer = parent::edd()->customer->create_and_get();
		$user_id  = wp_insert_user(
			array(
				'user_login' => 'test',
				'user_email' => $customer->email,
				'user_pass'  => wp_generate_password(),
			)
		);

		edd_connect_existing_customer_to_new_user( $user_id );
		$customer = edd_get_customer( $customer->id );

		$this->assertEquals( (int) $user_id, (int) $customer->user_id );
	}

	public function test_edd_connect_existing_customer_to_new_user_already_connected() {
		$customer = parent::edd()->customer->create_and_get();
		$user_id  = wp_insert_user(
			array(
				'user_login' => 'test_new',
				'user_email' => $customer->email,
				'user_pass'  => wp_generate_password(),
			)
		);

		edd_update_customer(
			$customer->id,
			array(
				'user_id' => $user_id,
			)
		);
		$customer = edd_get_customer( $customer->id );

		$this->assertEquals( (int) $user_id, (int) $customer->user_id );

		edd_add_customer_email_address(
			array(
				'customer_id' => $customer->id,
				'email'       => 'totallynewemail@edd.test',
				'type'        => 'secondary',
			)
		);

		$user_2_id = wp_insert_user(
			array(
				'user_login' => 'test_2',
				'user_email' => 'totallynewemail@edd.test',
				'user_pass'  => wp_generate_password(),
			)
		);

		edd_connect_existing_customer_to_new_user( $user_2_id );
		$customer = edd_get_customer( $customer->id );

		$this->assertNotEquals( (int) $user_2_id, (int) $customer->user_id );
	}

	/**
	 * @dataProvider link_login_policy_matrix_provider
	 */
	public function test_link_login_policy_matrix(
		$link_enabled,
		$logged_in_only,
		$show_register_form,
		$is_logged_in,
		$expected_checkout_email,
		$expected_checkout_login
	) {
		edd_update_option( 'login_link', $link_enabled );
		edd_update_option( 'logged_in_only', $logged_in_only );
		edd_update_option( 'show_register_form', $show_register_form );

		if ( $is_logged_in ) {
			$user_id = $this->factory->user->create();
			wp_set_current_user( $user_id );
		} else {
			wp_set_current_user( 0 );
		}

		$policy = Utility::get_policy();
		$this->assertIsArray( $policy );
		$this->assertArrayHasKey( 'contexts', $policy );
		$this->assertArrayHasKey( 'checkout-email', $policy['contexts'] );
		$this->assertArrayHasKey( 'checkout-login', $policy['contexts'] );

		$this->assertSame( $expected_checkout_email, (bool) $policy['contexts']['checkout-email']['enabled'] );
		$this->assertSame( $expected_checkout_login, (bool) $policy['contexts']['checkout-login']['enabled'] );

		$this->assertSame( $expected_checkout_email, Utility::context_enabled( 'checkout-email' ) );
		$this->assertSame( $expected_checkout_login, Utility::context_enabled( 'checkout-login' ) );

		$this->assertSame( (bool) $link_enabled, Utility::enabled() );
		$this->assertSame( $link_enabled && ! $is_logged_in, Utility::is_available() );
	}

	public function link_login_policy_matrix_provider() {
		return array(
			'disabled'                        => array( false, '', 'none', false, false, false ),
			'guest_allowed_no_forms'          => array( true, '', 'none', false, true, false ),
			'guest_allowed_login_form'        => array( true, '', 'login', false, true, true ),
			'guest_allowed_both_forms'        => array( true, '', 'both', false, true, true ),
			'guest_allowed_registration_only' => array( true, '', 'registration', false, true, false ),
			'required_none'                   => array( true, 'required', 'none', false, true, false ),
			'required_registration'           => array( true, 'required', 'registration', false, true, false ),
			'required_login'                  => array( true, 'required', 'login', false, true, true ),
			'required_both'                   => array( true, 'required', 'both', false, true, true ),
			'auto_none'                       => array( true, 'auto', 'none', false, true, false ),
			'auto_both'                       => array( true, 'auto', 'both', false, true, true ),
			'logged_in_user'                  => array( true, '', 'login', true, false, false ),
		);
	}

	public function test_link_login_policy_context_defaults() {
		edd_update_option( 'login_link', true );
		edd_update_option( 'logged_in_only', '' );
		edd_update_option( 'show_register_form', 'none' );

		$checkout_email = Utility::get_context_policy( 'checkout-email' );
		$this->assertSame( true, (bool) $checkout_email['default_hidden'] );
		$this->assertSame( 'existing_account', $checkout_email['visibility'] );
		$this->assertSame( 'This email is already in use. Log in to your account or request a one-time login link instead.', Utility::get_context_message( 'checkout-email' ) );

		$checkout_login = Utility::get_context_policy( 'checkout-login' );
		$this->assertSame( false, (bool) $checkout_login['default_hidden'] );
		$this->assertSame( 'always', $checkout_login['visibility'] );

		$unknown = Utility::get_context_policy( 'does-not-exist' );
		$this->assertSame( false, (bool) $unknown['enabled'] );
		$this->assertSame( '', Utility::get_context_message( 'does-not-exist' ) );
	}

	public function test_cleanup_rate_limits_clears_failure_transient_only() {
		$ip   = edd_get_ip();
		$hash = md5( $ip );

		set_transient( 'edd_login_link_fail_' . $hash, 3, HOUR_IN_SECONDS );
		set_transient( 'edd_login_link_ip_' . $hash, array( time() ), HOUR_IN_SECONDS );

		// Both transients should exist before cleanup.
		$this->assertNotFalse( get_transient( 'edd_login_link_fail_' . $hash ) );
		$this->assertNotFalse( get_transient( 'edd_login_link_ip_' . $hash ) );

		Utility::cleanup_rate_limits();

		// Failure transient should be gone after cleanup.
		$this->assertFalse( get_transient( 'edd_login_link_fail_' . $hash ) );

		// Send-rate transient should survive — a successful login must
		// not reset the send window.
		$this->assertNotFalse( get_transient( 'edd_login_link_ip_' . $hash ) );

		delete_transient( 'edd_login_link_ip_' . $hash );
	}

	public function test_cleanup_rate_limits_does_not_affect_other_ips() {
		$other_hash = md5( '203.0.113.99' );

		set_transient( 'edd_login_link_fail_' . $other_hash, 2, HOUR_IN_SECONDS );
		set_transient( 'edd_login_link_ip_' . $other_hash, array( time() ), HOUR_IN_SECONDS );

		Utility::cleanup_rate_limits();

		// Other IP's transients should still exist.
		$this->assertNotFalse( get_transient( 'edd_login_link_fail_' . $other_hash ) );
		$this->assertNotFalse( get_transient( 'edd_login_link_ip_' . $other_hash ) );

		// Clean up.
		delete_transient( 'edd_login_link_fail_' . $other_hash );
		delete_transient( 'edd_login_link_ip_' . $other_hash );
	}

	public function test_edd_get_link_login_url_returns_url() {
		$user_id = $this->factory->user->create();
		$token   = wp_generate_password( 32, false, false );

		$url = Token::generate_url( $user_id, $token );

		$this->assertIsString( $url );

		$parsed_url = wp_parse_url( $url );
		wp_parse_str( $parsed_url['query'], $params );
		$this->assertSame( 'login_link_verify', $params['edd_action'] );
		$this->assertSame( $token, rawurldecode( $params['token'] ) );
	}
}
