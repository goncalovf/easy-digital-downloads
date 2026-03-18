<?php

/**
 * Assets tests.
 *
 * @package     EDD\Tests\General
 */

namespace EDD\Tests\General;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;
use EDD\Admin\Assets\Scripts;

class Assets extends EDD_UnitTestCase {

	public function test_edd_get_assets_url() {
		$this->assertSame( EDD_PLUGIN_URL . 'assets/build/', edd_get_assets_url() );
		$this->assertSame( EDD_PLUGIN_URL . 'assets/vendor/', edd_get_assets_url( 'vendor' ) );
	}

	public function test_edd_get_assets_url_without_path() {
		$url = edd_get_assets_url();
		$this->assertStringEndsWith( 'assets/build/', $url );
	}

	public function test_edd_get_assets_url_with_js_path() {
		$url = edd_get_assets_url( 'js/admin' );
		$this->assertStringEndsWith( 'assets/build/js/admin/', $url );
	}

	public function test_edd_get_assets_url_with_vendor_path() {
		$url = edd_get_assets_url( 'vendor/jquery' );
		$this->assertStringEndsWith( 'assets/vendor/jquery/', $url );
		$this->assertStringNotContainsString( 'build', $url );
	}

	public function test_edd_get_assets_dir() {
		$this->assertSame( EDD_PLUGIN_DIR . 'assets/build/', edd_get_assets_dir() );
		$this->assertSame( EDD_PLUGIN_DIR . 'assets/vendor/', edd_get_assets_dir( 'vendor' ) );
	}

	public function test_edd_get_assets_dir_without_path() {
		$dir = edd_get_assets_dir();
		$this->assertStringEndsWith( 'assets/build/', $dir );
		$this->assertTrue( is_dir( $dir ) );
	}

	public function test_edd_get_assets_dir_with_js_path() {
		$dir = edd_get_assets_dir( 'js/admin' );
		$this->assertStringEndsWith( 'assets/build/js/admin/', $dir );
	}

	/**
	 * @dataProvider _test_includes_assets_dp
	 */
	public function test_includes_assets( $path_to_file ) {
		$this->assertFileExists( $path_to_file );
	}

	/**
	 * Data provider for test_includes_assets().
	 */
	public function _test_includes_assets_dp() {
		return array(
			array( EDD_PLUGIN_DIR . 'assets/build/css/admin/chosen.min.css' ),
			array( EDD_PLUGIN_DIR . 'assets/build/css/admin/admin.min.css' ),
			array( EDD_PLUGIN_DIR . 'assets/images/edd-cpt-2x.png' ),
			array( EDD_PLUGIN_DIR . 'assets/images/edd-cpt.png' ),
			array( EDD_PLUGIN_DIR . 'assets/images/edd-icon-2x.png' ),
			array( EDD_PLUGIN_DIR . 'assets/images/edd-icon.png' ),
			array( EDD_PLUGIN_DIR . 'assets/images/edd-icon.svg' ),
			array( EDD_PLUGIN_DIR . 'assets/images/edd-logo.png' ),
			array( EDD_PLUGIN_DIR . 'assets/images/edd-media.png' ),
			array( EDD_PLUGIN_DIR . 'assets/images/loading.gif' ),
			array( EDD_PLUGIN_DIR . 'templates/images/loading.gif' ),
			array( EDD_PLUGIN_DIR . 'assets/images/media-button.png' ),
			array( EDD_PLUGIN_DIR . 'templates/images/tick.png' ),
			array( EDD_PLUGIN_DIR . 'assets/images/xit.gif' ),
			array( EDD_PLUGIN_DIR . 'assets/build/css/frontend/edd.min.css' ),
			array( EDD_PLUGIN_DIR . 'templates/images/xit.gif' ),
			array( EDD_PLUGIN_DIR . 'assets/build/js/admin/admin.js' ),
			array( EDD_PLUGIN_DIR . 'assets/build/js/frontend/edd-ajax.js' ),
			array( EDD_PLUGIN_DIR . 'assets/build/js/frontend/checkout.js' ),
			array( EDD_PLUGIN_DIR . 'assets/vendor/js/tom-select.complete.min.js' ),
			array( EDD_PLUGIN_DIR . 'assets/build/js/admin/chosen-compat.js' ),
			array( EDD_PLUGIN_DIR . 'assets/vendor/js/jquery.creditcardvalidator.min.js' ),
			array( EDD_PLUGIN_DIR . 'assets/vendor/js/jquery.flot.min.js' ),
			array( EDD_PLUGIN_DIR . 'assets/vendor/js/jquery.validate.min.js' ),
			array( EDD_PLUGIN_DIR . 'assets/vendor/js/tom-select.complete.min.js' ),
		);
	}
}

/**
 * Tests for EDD\Admin\Assets\Scripts registration.
 *
 * @package     EDD\Tests\General
 */
class ScriptRegistration extends EDD_UnitTestCase {

	/**
	 * Run Scripts::register() once before each test, then reset after.
	 */
	public function setUp(): void {
		parent::setUp();
		Scripts::register();
	}

	public function tearDown(): void {
		wp_deregister_script( 'edd-tom-select' );
		wp_deregister_script( 'jquery-chosen' );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// edd-tom-select
	// -------------------------------------------------------------------------

	public function test_edd_tom_select_is_registered() {
		$this->assertTrue( wp_script_is( 'edd-tom-select', 'registered' ) );
	}

	public function test_edd_tom_select_src_points_to_correct_file() {
		global $wp_scripts;
		$script = $wp_scripts->query( 'edd-tom-select', 'registered' );
		$this->assertStringEndsWith( 'tom-select.complete.min.js', $script->src );
	}

	public function test_edd_tom_select_src_is_from_vendor_directory() {
		global $wp_scripts;
		$script = $wp_scripts->query( 'edd-tom-select', 'registered' );
		$this->assertStringContainsString( 'assets/vendor/js/', $script->src );
	}

	public function test_edd_tom_select_has_no_dependencies() {
		global $wp_scripts;
		$script = $wp_scripts->query( 'edd-tom-select', 'registered' );
		$this->assertEmpty( $script->deps );
	}

	public function test_edd_tom_select_loads_in_footer() {
		global $wp_scripts;
		$this->assertSame( 1, $wp_scripts->get_data( 'edd-tom-select', 'group' ) );
	}

	// -------------------------------------------------------------------------
	// jquery-chosen (chosen-compat.js)
	// -------------------------------------------------------------------------

	public function test_jquery_chosen_is_registered() {
		$this->assertTrue( wp_script_is( 'jquery-chosen', 'registered' ) );
	}

	public function test_jquery_chosen_src_points_to_correct_file() {
		global $wp_scripts;
		$script = $wp_scripts->query( 'jquery-chosen', 'registered' );
		$this->assertStringEndsWith( 'chosen-compat.js', $script->src );
	}

	public function test_jquery_chosen_src_is_from_admin_js_directory() {
		global $wp_scripts;
		$script = $wp_scripts->query( 'jquery-chosen', 'registered' );
		$this->assertStringContainsString( 'assets/build/js/admin/', $script->src );
	}

	public function test_jquery_chosen_depends_on_jquery() {
		global $wp_scripts;
		$script = $wp_scripts->query( 'jquery-chosen', 'registered' );
		$this->assertContains( 'jquery', $script->deps );
	}

	public function test_jquery_chosen_depends_on_edd_tom_select() {
		global $wp_scripts;
		$script = $wp_scripts->query( 'jquery-chosen', 'registered' );
		$this->assertContains( 'edd-tom-select', $script->deps );
	}

	public function test_jquery_chosen_has_exactly_two_dependencies() {
		global $wp_scripts;
		$script = $wp_scripts->query( 'jquery-chosen', 'registered' );
		$this->assertCount( 2, $script->deps );
	}

	public function test_jquery_chosen_loads_in_footer() {
		global $wp_scripts;
		$this->assertSame( 1, $wp_scripts->get_data( 'jquery-chosen', 'group' ) );
	}
}
