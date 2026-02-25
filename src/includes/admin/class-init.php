<?php
namespace WallLib\admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Init
 *
 * @package WallLib\admin
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
		$this->posts();
	}

	/**
	 * @return Posts
	 */
	public function posts() {
		if ( empty( UM()->classes[ $this->wall->prefix . 'WallLib\admin\posts' ] ) ) {
			UM()->classes[ $this->wall->prefix . 'WallLib\admin\posts' ] = new Posts( $this->wall );
		}
		return UM()->classes[ $this->wall->prefix . 'WallLib\admin\posts' ];
	}
}
