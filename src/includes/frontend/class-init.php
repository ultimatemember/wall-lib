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

	private $wall;

	public function __construct( $wall ) {
		$this->wall = $wall;
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
		if ( empty( UM()->classes[ $this->wall->prefix . 'WallLib\frontend\enqueue' ] ) ) {
			UM()->classes[ $this->wall->prefix . 'WallLib\frontend\enqueue' ] = new Enqueue( $this->wall );
		}
		return UM()->classes[ $this->wall->prefix . 'WallLib\frontend\enqueue' ];
	}
}
