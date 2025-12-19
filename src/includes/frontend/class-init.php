<?php
namespace WallLib\frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Init
 *
 * @package WallLib\frontend
 */
class Init {

	public function __construct() {
	}
	/**
	 * Create classes' instances where __construct isn't empty for hooks init
	 */
	public function includes() {
		$this->enqueue()->hooks();
	}

	/**
	 * @return Enqueue
	 */
	public function enqueue() {
		if ( empty( UM()->classes['WallLib\frontend\enqueue'] ) ) {
			UM()->classes['WallLib\frontend\enqueue'] = new Enqueue();
		}
		return UM()->classes['WallLib\frontend\enqueue'];
	}
}
