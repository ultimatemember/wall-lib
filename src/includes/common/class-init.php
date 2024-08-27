<?php
namespace WallLib\common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Init
 *
 * @package WallLib\common
 */
class Init {

	public function __construct() {
	}
	/**
	 * Create classes' instances where __construct isn't empty for hooks init
	 */
	public function includes() {
		$this->user();
	}

	/**
	 * @return User
	 */
	public function user() {
		if ( empty( UM()->classes['WallLib\common\user'] ) ) {
			UM()->classes['WallLib\common\user'] = new User();
		}
		return UM()->classes['WallLib\common\user'];
	}
}
