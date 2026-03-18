<?php

namespace EDD\Tests\SiteHealth;

use EDD\Tests\PHPUnit\EDD_UnitTestCase;
use EDD\Admin\SiteHealth\Pages as PagesTest;

class Pages extends EDD_UnitTestCase {

	/**
	 * The Pages class instance.
	 *
	 * @var PagesTest
	 */
	private static $test;

	/**
	 * The data for the test.
	 *
	 * @var array
	 */
	private static $data;

	/**
	 * Setup the test class.
	 */
	public static function wpSetUpBeforeClass() {
		self::$test = new PagesTest();
		self::$data = self::$test->get();
	}

	public function test_data_has_checkout_type() {
		$this->assertArrayHasKey( 'checkout_type', self::$data['fields'] );
		$this->assertEquals( 'Checkout Type', self::$data['fields']['checkout_type']['label'] );
	}
}
