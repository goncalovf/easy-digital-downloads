<?php
/**
 * Elementor stubs for testing.
 *
 * Provides minimal stubs of Elementor classes so that
 * EDD's Elementor integration code can be exercised in unit tests
 * without the full Elementor plugin.
 */

namespace Elementor;

class Document {

	/**
	 * Whether this document was built with Elementor.
	 *
	 * @var bool
	 */
	private $built_with_elementor;

	/**
	 * The elements data for this document.
	 *
	 * @var array
	 */
	private $elements_data;

	public function __construct( $built_with_elementor = true, $elements_data = array() ) {
		$this->built_with_elementor = $built_with_elementor;
		$this->elements_data        = $elements_data;
	}

	public function is_built_with_elementor() {
		return $this->built_with_elementor;
	}

	public function get_elements_data() {
		return $this->elements_data;
	}
}

class Documents_Manager {

	/**
	 * Map of post ID => Document.
	 *
	 * @var array
	 */
	private $documents = array();

	/**
	 * Register a document for a post ID.
	 *
	 * @param int      $post_id  The post ID.
	 * @param Document $document The document instance.
	 */
	public function register( $post_id, $document ) {
		$this->documents[ $post_id ] = $document;
	}

	/**
	 * Get a document by post ID.
	 *
	 * @param int $post_id The post ID.
	 * @return Document|false
	 */
	public function get( $post_id ) {
		return isset( $this->documents[ $post_id ] ) ? $this->documents[ $post_id ] : false;
	}
}

class Plugin {

	/**
	 * The singleton instance.
	 *
	 * @var Plugin
	 */
	public static $instance;

	/**
	 * The documents manager.
	 *
	 * @var Documents_Manager
	 */
	public $documents;

	public function __construct() {
		$this->documents = new Documents_Manager();
	}

	/**
	 * Initialize the stub singleton.
	 *
	 * @return Plugin
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Reset the stub singleton (for test isolation).
	 */
	public static function reset() {
		self::$instance = null;
	}
}
