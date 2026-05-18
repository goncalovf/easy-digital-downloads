<?php
/**
 * Phone HTML Element Tests
 *
 * @package     EDD\HTML
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.8
 */

namespace EDD\Tests\HTML;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * Phone element tests.
 *
 * @group edd_html
 *
 * @coversDefaultClass EDD\HTML\Phone
 */
class Phone extends EDD_UnitTestCase {

	public function tearDown(): void {
		wp_dequeue_script( 'intl-tel-input' );
		wp_dequeue_script( 'edd-intl-tel-input' );
		wp_dequeue_style( 'intl-tel-input' );
		parent::tearDown();
	}

	/**
	 * Returns a Phone element with the given args.
	 *
	 * @param array $args
	 * @return string
	 */
	private function get_phone( array $args = array() ): string {
		$phone = new \EDD\HTML\Phone( $args );
		return $phone->get();
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_renders_tel_input() {
		$output = $this->get_phone();

		$this->assertStringContainsString( 'type="tel"', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_default_name() {
		$output = $this->get_phone();

		$this->assertStringContainsString( 'name="phone"', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_custom_name() {
		$output = $this->get_phone( array( 'name' => 'billing_phone' ) );

		$this->assertStringContainsString( 'name="billing_phone"', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_custom_id() {
		$output = $this->get_phone( array( 'id' => 'edd-phone' ) );

		$this->assertStringContainsString( 'id="edd-phone"', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_value_is_rendered() {
		$output = $this->get_phone( array( 'value' => '555-867-5309' ) );

		$this->assertStringContainsString( 'value="555-867-5309"', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_base_css_class() {
		$output = $this->get_phone();

		$this->assertStringContainsString( 'edd-input__phone', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_custom_css_class() {
		$output = $this->get_phone( array( 'class' => 'my-custom-class' ) );

		$this->assertStringContainsString( 'my-custom-class', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_disabled_attribute() {
		$output = $this->get_phone( array( 'disabled' => true ) );

		$this->assertStringContainsString( 'disabled', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_not_disabled_by_default() {
		$output = $this->get_phone();

		$this->assertStringNotContainsString( 'disabled', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_required_attribute() {
		$output = $this->get_phone( array( 'required' => true ) );

		$this->assertStringContainsString( 'required', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_not_required_by_default() {
		$output = $this->get_phone();

		$this->assertStringNotContainsString( 'required', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_autocomplete_attribute() {
		$output = $this->get_phone( array( 'autocomplete' => 'tel' ) );

		$this->assertStringContainsString( 'autocomplete="tel"', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_no_autocomplete_by_default() {
		$output = $this->get_phone();

		$this->assertStringNotContainsString( 'autocomplete=', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_pattern_attribute() {
		$output = $this->get_phone( array( 'pattern' => '[0-9]{3}-[0-9]{3}-[0-9]{4}' ) );

		$this->assertStringContainsString( 'pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}"', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_no_pattern_by_default() {
		$output = $this->get_phone();

		$this->assertStringNotContainsString( 'pattern=', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_data_country_attribute() {
		$output = $this->get_phone();

		$this->assertStringContainsString( 'data-country=', $output );
	}

	/**
	 * @covers EDD\HTML\Phone::enqueue
	 */
	public function test_enqueue_registers_intl_tel_input_script() {
		\EDD\HTML\Phone::enqueue();

		$this->assertTrue( wp_script_is( 'intl-tel-input', 'enqueued' ) );
	}

	/**
	 * @covers EDD\HTML\Phone::enqueue
	 */
	public function test_enqueue_registers_edd_intl_tel_input_script() {
		\EDD\HTML\Phone::enqueue();

		$this->assertTrue( wp_script_is( 'edd-intl-tel-input', 'enqueued' ) );
	}

	/**
	 * @covers EDD\HTML\Phone::enqueue
	 */
	public function test_enqueue_registers_intl_tel_input_style() {
		\EDD\HTML\Phone::enqueue();

		$this->assertTrue( wp_style_is( 'intl-tel-input', 'enqueued' ) );
	}

	/**
	 * @covers EDD\HTML\Phone::enqueue
	 */
	public function test_enqueue_skipped_when_filter_disabled() {
		add_filter( 'edd_intl_tel_input', '__return_false' );

		try {
			\EDD\HTML\Phone::enqueue();
			$this->assertFalse( wp_script_is( 'intl-tel-input', 'enqueued' ) );
		} finally {
			remove_filter( 'edd_intl_tel_input', '__return_false' );
		}
	}

	/**
	 * @covers EDD\HTML\Phone::get
	 */
	public function test_placeholder_not_rendered_when_intl_tel_input_enqueued() {
		\EDD\HTML\Phone::enqueue();
		$output = $this->get_phone( array( 'placeholder' => 'Enter phone number' ) );

		$this->assertStringNotContainsString( ' placeholder=', $output );
	}

	/**
	 * Verifies the intlTelInput vendor files exist.
	 */
	public function test_intl_tel_input_js_vendor_files_exist() {
		$files = array(
			EDD_PLUGIN_DIR . 'assets/vendor/intl-tel-input/js/intlTelInput.min.js',
			EDD_PLUGIN_DIR . 'assets/vendor/intl-tel-input/css/intlTelInput.min.css',
			EDD_PLUGIN_DIR . 'assets/vendor/intl-tel-input/img/flags.webp',
			EDD_PLUGIN_DIR . 'assets/vendor/intl-tel-input/img/flags@2x.webp',
			EDD_PLUGIN_DIR . 'assets/vendor/intl-tel-input/img/globe.webp',
			EDD_PLUGIN_DIR . 'assets/vendor/intl-tel-input/img/globe@2x.webp',
		);

		foreach ( $files as $file ) {
			$this->assertFileExists( $file );
		}
	}
}
