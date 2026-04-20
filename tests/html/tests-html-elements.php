<?php
namespace EDD\Tests\HTML;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * EDD HTML Elements Tests
 *
 * @group edd_html
 *
 * @coversDefaultClass EDD_HTML_Elements
 */
class Elements extends EDD_UnitTestCase {

	public function test_class_instance() {
		$this->assertInstanceOf( '\\EDD\\HTML\\Elements', EDD()->html );
	}

	public function test_legacy_class_alias() {
		$legacy_class = new \EDD_HTML_Elements();

		$this->assertInstanceOf( '\\EDD\\HTML\\Elements', $legacy_class );
	}

	/**
	 * @covers EDD_HTML_Elements::select
	 */
	public function test_select_is_required() {
		$select = EDD()->html->select(
			array(
				'required' => true,
				'options'  => array(
					1 => '1',
					2 => '2',
					3 => '3',
				),
			)
		);

		$this->assertStringContainsString( 'required', $select );
	}

	/**
	 * @covers EDD_HTML_Elements::select
	 */
	public function test_select_is_not_required() {
		$select = EDD()->html->select(
			array(
				'options' => array(
					1 => '1',
					2 => '2',
					3 => '3',
				)
			)
		);

		$this->assertStringNotContainsString( 'required', $select );
	}

	/**
	 * @covers EDD_HTML_Elements::text
	 */
	public function test_text_is_required() {
		$this->assertStringContainsString( 'required', EDD()->html->text( array( 'required' => true ) ) );
	}

	/**
	 * @covers EDD_HTML_Elements::text
	 */
	public function test_text_is_not_required() {
		$this->assertStringNotContainsString( 'required', EDD()->html->text() );
	}

	public function test_checkbox() {
		$checkbox = EDD()->html->checkbox(
			array(
				'name'  => 'edd-checkbox',
				'label' => 'Checkbox',
			)
		);

		$this->assertStringContainsString( 'name="edd-checkbox"', $checkbox );
		$this->assertStringContainsString( '<label for="edd-checkbox">Checkbox</label>', $checkbox );
	}

	public function test_textarea() {
		$textarea = EDD()->html->textarea(
			array(
				'name'  => 'edd-textarea',
				'label' => 'Textarea',
			)
		);

		$this->assertStringContainsString( 'name="edd-textarea"', $textarea );
		$this->assertStringContainsString( '<label class="edd-label" for="edd-textarea">', $textarea );
	}

	public function test_text() {
		$text = EDD()->html->text(
			array(
				'id'    => 'edd-text',
				'name'  => 'edd-text',
				'label' => 'Text',
			)
		);

		$this->assertStringContainsString( 'name="edd-text"', $text );
		$this->assertStringContainsString( '<label class="edd-label" for="edd-text">', $text );
	}

	public function test_date_field() {
		$date_field = EDD()->html->date_field(
			array(
				'id'    => 'edd-date-field',
				'name'  => 'edd-date-field',
				'label' => 'Date Field',
			)
		);

		$this->assertStringContainsString( 'name="edd-date-field"', $date_field );
		$this->assertStringContainsString( 'edd_datepicker', $date_field );
		$this->assertStringContainsString( 'data-format="yyyy-mm-dd"', $date_field );
	}

	public function test_date_field_custom_class() {
		$date_field = EDD()->html->date_field(
			array(
				'id'    => 'edd-date-field',
				'name'  => 'edd-date-field',
				'label' => 'Date Field',
				'class' => 'custom-class',
			)
		);

		$this->assertStringContainsString( 'name="edd-date-field"', $date_field );
		$this->assertStringContainsString( 'edd_datepicker', $date_field );
		$this->assertStringContainsString( 'custom-class', $date_field );
		$this->assertStringContainsString( 'data-format="yyyy-mm-dd"', $date_field );
	}

	public function test_ajax_user_search() {
		$user_search = EDD()->html->ajax_user_search();

		$this->assertStringContainsString( 'name="user_id"', $user_search );
		$this->assertStringContainsString( 'data-placeholder="Select a User"', $user_search );
		$this->assertStringContainsString( 'edd-user-select', $user_search );
	}

	public function test_checkbox_toggle() {
		$toggle = new \EDD\HTML\CheckboxToggle(
			array(
				'name'  => 'once_per_customer',
				'label' => __( 'Prevent customers from using this discount more than once.', 'easy-digital-downloads' ),
			)
		);
		$output = $toggle->get();

		$this->assertStringContainsString( 'name="once_per_customer"', $output );
		$this->assertStringContainsString( 'edd-toggle', $output );
	}

	public function test_checkbox_toggle_disabled_readonly() {
		$toggle = new \EDD\HTML\CheckboxToggle(
			array(
				'name'    => 'once_per_customer',
				'label'   => __( 'Prevent customers from using this discount more than once.', 'easy-digital-downloads' ),
				'options' => array(
					'disabled' => true,
					'readonly' => true,
				),
			)
		);
		$output = $toggle->get();

		$this->assertStringContainsString( 'name="once_per_customer"', $output );
		$this->assertStringContainsString( 'edd-toggle', $output );
		$this->assertStringContainsString( 'disabled', $output );
		$this->assertStringContainsString( 'readonly', $output );
	}

	public function test_upload() {
		$upload = new \EDD\HTML\Upload(
			array(
				'id'   => 'edd-upload',
				'name' => 'edd-upload',
				'value' => 'http://example.com/image.jpg',
				'desc' => 'Upload or choose a logo to be displayed at the top of sales receipt emails. Displayed on HTML emails only.',
			)
		);
		$output = $upload->get();

		$this->assertStringContainsString( 'name="edd-upload"', $output );
		$this->assertStringContainsString( 'http://example.com/image.jpg', $output );
		$this->assertStringContainsString( 'Attach File', $output );
		$this->assertStringContainsString( 'Upload or choose a logo', $output );
	}

	/**
	 * Test multicheck with missing label.
	 * This covers the bug fix for issue #2305 where missing labels caused errors.
	 */
	public function test_multicheck_missing_label() {
		$multicheck = new \EDD\HTML\Multicheck(
			array(
				'name'    => 'test-options',
				'options' => array(
					'option1' => array(
						'label'   => 'Option 1',
						'checked' => true,
					),
					'option2' => array(
						// Missing label - should fallback to empty string
						'checked' => false,
					),
				),
			)
		);
		$output = $multicheck->get();

		$this->assertStringContainsString( 'name="test-options[option1]"', $output );
		$this->assertStringContainsString( 'Option 1', $output );
		// option2 has no label — verify the label tag renders with empty content.
		$this->assertMatchesRegularExpression( '/<label\b[^>]*for="test-options\[option2\]"[^>]*>\s*<\/label>/', $output );
	}

	/**
	 * Test multicheck with array label (invalid).
	 * This also covers issue #2305 - should not crash with array label.
	 */
	public function test_multicheck_array_label() {
		$multicheck = new \EDD\HTML\Multicheck(
			array(
				'name'    => 'test-options',
				'options' => array(
					'option1' => array(
						'label' => array( 'invalid', 'label' ), // Invalid: array instead of string
						'checked' => false,
					),
				),
			)
		);
		$output = $multicheck->get();

		$this->assertStringContainsString( 'name="test-options[option1]"', $output );
		// Array label should render as empty string, not crash.
		$this->assertMatchesRegularExpression( '/<label\b[^>]*for="test-options\[option1\]"[^>]*>\s*<\/label>/', $output );
	}
}
