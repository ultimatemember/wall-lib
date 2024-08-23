<?php
namespace WallLib\frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Enqueue
 * @package WallLib\core
 */
class Enqueue {

	/**
	 * Enqueue constructor.
	 */
	public function __construct() {
	}

	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts' ), 1 );
	}

	/**
	 * Enqueue scripts
	 */
	public function wp_enqueue_scripts() {
		$suffix = UM()->frontend()->enqueue()::get_suffix();
		wp_register_script( 'um_wall', um_activity_url . 'assets/libs/autosize/autosize' . $suffix . '.js', array( 'jquery' ), '6.0.1', true );
	}
}
