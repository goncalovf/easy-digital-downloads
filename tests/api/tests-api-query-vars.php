<?php
/**
 * Tests for EDD_API::query_vars() scoping behavior.
 *
 * Verifies that API-specific query vars are only registered for actual API
 * requests, preventing WordPress from reading them out of $_POST bodies on
 * non-API requests (e.g. third-party form submissions on the front page).
 *
 * @package     EDD\Tests\API
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Tests\API;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;

/**
 * @group edd_api
 */
class QueryVars extends EDD_UnitTestCase {

	/**
	 * @var \EDD_API
	 */
	protected static $api;

	/**
	 * @var string Original REQUEST_URI.
	 */
	protected $original_request_uri;

	public static function wpSetUpBeforeClass() {
		self::$api = new \EDD_API();
	}

	public function set_up() {
		parent::set_up();
		$this->original_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	}

	public function tear_down() {
		$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		parent::tear_down();
	}

	/**
	 * No API vars — including 'discount' — should be registered for non-API
	 * requests. Discount URLs read from $_REQUEST directly via
	 * edd_listen_for_cart_discount() and do not need a registered query var.
	 */
	public function test_no_vars_registered_for_non_api_request() {
		$_SERVER['REQUEST_URI'] = '/';
		$vars                   = self::$api->query_vars( array() );
		$this->assertEmpty( $vars );
	}

	public function test_discount_var_registered_for_api_request() {
		$_SERVER['REQUEST_URI'] = '/edd-api/v2/products';
		$vars                   = self::$api->query_vars( array() );
		$this->assertContains( 'discount', $vars );
	}

	/**
	 * API-specific vars must NOT be registered for non-API requests.
	 * This prevents WordPress from reading matching $_POST fields on form
	 * submissions and corrupting WP_Query's front-page detection.
	 */
	public function test_token_not_registered_for_non_api_request() {
		$_SERVER['REQUEST_URI'] = '/';
		$vars                   = self::$api->query_vars( array() );
		$this->assertNotContains( 'token', $vars );
	}

	public function test_api_vars_not_registered_for_non_api_request() {
		$_SERVER['REQUEST_URI'] = '/contact/';

		$vars = self::$api->query_vars( array() );

		$api_only_vars = array( 'token', 'key', 'query', 'type', 'product', 'purchasekey', 'email', 'info', 'include_tax' );
		foreach ( $api_only_vars as $var ) {
			$this->assertNotContains( $var, $vars, "Query var '{$var}' should not be registered for non-API requests." );
		}
	}

	/**
	 * API-specific vars must be registered for actual API requests so the
	 * API can read them from the URL query string via WP_Query.
	 */
	public function test_api_vars_registered_for_pretty_permalink_api_request() {
		$_SERVER['REQUEST_URI'] = '/edd-api/v2/products';

		$vars = self::$api->query_vars( array() );

		$api_only_vars = array( 'token', 'key', 'query', 'type', 'product', 'purchasekey', 'email', 'info', 'include_tax' );
		foreach ( $api_only_vars as $var ) {
			$this->assertContains( $var, $vars, "Query var '{$var}' should be registered for API requests." );
		}
	}

	public function test_api_vars_registered_for_plain_permalink_api_request() {
		$_SERVER['REQUEST_URI'] = '/?edd-api=v2/products&token=abc&key=def';

		$vars = self::$api->query_vars( array() );

		$this->assertContains( 'token', $vars );
		$this->assertContains( 'key', $vars );
	}

	/**
	 * Existing vars passed into the filter must be preserved regardless of
	 * request type.
	 */
	public function test_existing_vars_preserved_for_non_api_request() {
		$_SERVER['REQUEST_URI'] = '/';
		$vars                   = self::$api->query_vars( array( 'paged', 's' ) );
		$this->assertContains( 'paged', $vars );
		$this->assertContains( 's', $vars );
	}
}
