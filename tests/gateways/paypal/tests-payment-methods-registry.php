<?php
/**
 * PayPal Payment Methods Registry Tests
 *
 * Characterizes the funding, component, and client-token assembly performed by
 * the PaymentMethods registry. The expected values mirror exactly what
 * `scripts.php` produces today, so these tests pin current behavior before the
 * SDK loader is wired to read the registry.
 *
 * @package   EDD\Tests\Gateways\PayPal
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   GPL2+
 * @since     3.6.9
 */

namespace EDD\Tests\Gateways\PayPal;

use EDD\Gateways\PayPal\PaymentMethods;
use EDD\Gateways\PayPal\PaymentMethods\ApplePay;
use EDD\Gateways\PayPal\PaymentMethods\Card;
use EDD\Gateways\PayPal\PaymentMethods\Fastlane;
use EDD\Gateways\PayPal\PaymentMethods\PayPal;
use EDD\Gateways\PayPal\PaymentMethods\UnbrandedCard;
use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * Tests for the PayPal payment methods registry.
 *
 * @group gateways
 * @group paypal
 * @group paypal-sdk
 */
class PaymentMethodsRegistryTest extends EDD_UnitTestCase {

	/**
	 * The registry resolves known slugs to their descriptor classes.
	 */
	public function test_get_method_resolves_known_slugs() {
		$this->assertSame( PayPal::class, PaymentMethods::get_method( 'paypal' ) );
		$this->assertSame( Card::class, PaymentMethods::get_method( 'card' ) );
		$this->assertSame( UnbrandedCard::class, PaymentMethods::get_method( 'unbranded_card' ) );
		$this->assertSame( Fastlane::class, PaymentMethods::get_method( 'fastlane' ) );
	}

	/**
	 * The registry returns null for an unknown slug.
	 */
	public function test_get_method_returns_null_for_unknown_slug() {
		$this->assertNull( PaymentMethods::get_method( 'bogus' ) );
	}

	/**
	 * The registry lists all eight methods in admin display order.
	 */
	public function test_registered_methods_are_in_display_order() {
		$this->assertSame(
			array( 'paypal', 'card', 'unbranded_card', 'pay_later', 'venmo', 'apple_pay', 'google_pay', 'fastlane' ),
			array_keys( PaymentMethods::get_registered_methods() )
		);
	}

	/**
	 * Branded card defaults on only when advanced-card vetting is NOT granted.
	 */
	public function test_card_default_state_inverts_advanced_card_grant() {
		$this->assertFalse( Card::default_state( true ) );
		$this->assertTrue( Card::default_state( false ) );
	}

	/**
	 * Unbranded card defaults on only when advanced-card vetting IS granted.
	 */
	public function test_unbranded_card_default_state_follows_advanced_card_grant() {
		$this->assertTrue( UnbrandedCard::default_state( true ) );
		$this->assertFalse( UnbrandedCard::default_state( false ) );
	}

	/**
	 * Both card methods share the `card` funding source.
	 */
	public function test_both_card_methods_share_card_funding() {
		$this->assertSame( 'card', Card::get_funding_source() );
		$this->assertSame( 'card', UnbrandedCard::get_funding_source() );
	}

	/**
	 * Fastlane and unbranded card require the buyer client token; cards do not.
	 */
	public function test_client_token_requirement_is_a_per_method_fact() {
		$this->assertTrue( Fastlane::requires_client_token() );
		$this->assertTrue( UnbrandedCard::requires_client_token() );
		$this->assertFalse( Card::requires_client_token() );
		$this->assertFalse( PayPal::requires_client_token() );
	}

	/**
	 * The shared `card` funding is disabled only when neither card method is active.
	 *
	 * This is the coupling the SDK loader and the JS gate previously re-derived
	 * separately. Adding the on-checkout fields must not disable the branded
	 * button's funding, and vice versa.
	 */
	public function test_card_funding_disabled_only_when_no_card_method_active() {
		// Neither card method active: card is disabled.
		$this->assertContains( 'card', PaymentMethods::get_disable_funding( array( 'paypal' ) ) );

		// Branded card active: card is NOT disabled.
		$this->assertNotContains( 'card', PaymentMethods::get_disable_funding( array( 'paypal', 'card' ) ) );

		// Only the on-checkout fields active: card is still NOT disabled.
		$this->assertNotContains( 'card', PaymentMethods::get_disable_funding( array( 'paypal', 'unbranded_card' ) ) );

		// Both card methods active: card is NOT disabled.
		$this->assertNotContains( 'card', PaymentMethods::get_disable_funding( array( 'paypal', 'card', 'unbranded_card' ) ) );
	}

	/**
	 * Pay Later toggles both `paylater` and its paired `credit` funding.
	 */
	public function test_pay_later_pairs_credit_with_paylater() {
		$disabled_off = PaymentMethods::get_disable_funding( array( 'paypal' ) );
		$this->assertContains( 'paylater', $disabled_off );
		$this->assertContains( 'credit', $disabled_off );

		$disabled_on = PaymentMethods::get_disable_funding( array( 'paypal', 'pay_later' ) );
		$this->assertNotContains( 'paylater', $disabled_on );
		$this->assertNotContains( 'credit', $disabled_on );
	}

	/**
	 * Disable-funding for an everything-off cart matches the SDK loader output.
	 */
	public function test_disable_funding_with_only_paypal_active() {
		$this->assertEqualsCanonicalizing(
			array( 'venmo', 'card', 'paylater', 'credit' ),
			PaymentMethods::get_disable_funding( array( 'paypal' ) )
		);
	}

	/**
	 * Disable-funding is empty when every toggleable method is active.
	 */
	public function test_disable_funding_empty_when_all_active() {
		$this->assertSame(
			array(),
			PaymentMethods::get_disable_funding( array( 'paypal', 'card', 'venmo', 'pay_later' ) )
		);
	}

	/**
	 * Only Venmo and Pay Later are explicitly enabled in `enable-funding`.
	 */
	public function test_enable_funding_only_lists_venmo_and_paylater() {
		$this->assertSame( array(), PaymentMethods::get_enable_funding( array( 'paypal', 'card' ) ) );
		$this->assertSame( array( 'venmo' ), PaymentMethods::get_enable_funding( array( 'paypal', 'venmo' ) ) );
		$this->assertEqualsCanonicalizing(
			array( 'venmo', 'paylater' ),
			PaymentMethods::get_enable_funding( array( 'paypal', 'venmo', 'pay_later' ) )
		);
	}

	/**
	 * The frontend funding-source list always includes PayPal and reflects the active buttons.
	 */
	public function test_button_funding_sources_reflect_active_buttons() {
		$this->assertSame( array( 'paypal' ), PaymentMethods::get_button_funding_sources( array( 'paypal' ) ) );

		$this->assertSame(
			array( 'paypal', 'venmo', 'paylater', 'credit', 'card' ),
			PaymentMethods::get_button_funding_sources( array( 'paypal', 'card', 'venmo', 'pay_later' ) )
		);
	}

	/**
	 * The on-checkout card fields are not reported as a button funding source.
	 */
	public function test_unbranded_card_is_not_a_button_funding_source() {
		$this->assertSame(
			array( 'paypal' ),
			PaymentMethods::get_button_funding_sources( array( 'paypal', 'unbranded_card' ) )
		);
	}

	/**
	 * The base `buttons` component always loads.
	 */
	public function test_buttons_component_always_present() {
		$this->assertSame( array( 'buttons' ), PaymentMethods::get_sdk_components( array( 'paypal' ) ) );
	}

	/**
	 * Pay Later adds the `messages` component; Google Pay adds `googlepay`.
	 */
	public function test_components_for_messages_and_googlepay() {
		$this->assertSame(
			array( 'buttons', 'messages' ),
			PaymentMethods::get_sdk_components( array( 'paypal', 'pay_later' ) )
		);

		$this->assertSame(
			array( 'buttons', 'googlepay' ),
			PaymentMethods::get_sdk_components( array( 'paypal', 'google_pay' ) )
		);
	}

	/**
	 * The card-fields and fastlane components only load when the client token is present.
	 */
	public function test_token_required_components_gated_on_client_token() {
		// Without a client token, neither token-dependent component loads.
		$this->assertSame(
			array( 'buttons' ),
			PaymentMethods::get_sdk_components( array( 'paypal', 'unbranded_card', 'fastlane' ), false )
		);

		// With a client token, both load (in active order).
		$this->assertSame(
			array( 'buttons', 'card-fields', 'fastlane' ),
			PaymentMethods::get_sdk_components( array( 'paypal', 'unbranded_card', 'fastlane' ), true )
		);
	}

	/**
	 * Apple Pay's component is suppressed in test mode regardless of toggle state.
	 */
	public function test_apple_pay_component_suppressed_in_test_mode() {
		add_filter( 'edd_is_test_mode', '__return_true' );

		$this->assertFalse( ApplePay::is_available() );
		$this->assertSame(
			array( 'buttons' ),
			PaymentMethods::get_sdk_components( array( 'paypal', 'apple_pay' ) )
		);

		remove_filter( 'edd_is_test_mode', '__return_true' );
	}

	/**
	 * The registry reports which active methods require the client token.
	 */
	public function test_methods_requiring_client_token() {
		$this->assertSame( array(), PaymentMethods::methods_requiring_client_token( array( 'paypal', 'card' ) ) );

		$this->assertSame(
			array( 'unbranded_card' ),
			PaymentMethods::methods_requiring_client_token( array( 'paypal', 'unbranded_card' ) )
		);

		$this->assertEqualsCanonicalizing(
			array( 'unbranded_card', 'fastlane' ),
			PaymentMethods::methods_requiring_client_token( array( 'paypal', 'unbranded_card', 'fastlane' ) )
		);
	}
}
