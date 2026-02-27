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

	private $wall;

	/**
	 * Enqueue constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;

		add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts' ), 1 );
	}

	/**
	 * Enqueue scripts
	 */
	public function wp_enqueue_scripts() {
		$suffix = UM()->frontend()->enqueue()::get_suffix();

		wp_register_style( 'um_wall', plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/wall' . $suffix . '.css', array(), UM()->classes['WallLib\Init']->plugin_version );
		wp_register_script( 'um_wall', plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/wall' . $suffix . '.js', array( 'jquery', 'um_modal', 'um_new_design' ), UM()->classes['WallLib\Init']->plugin_version, true );
	}
}
