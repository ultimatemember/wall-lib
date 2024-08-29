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

	private $wall;

	public function __construct( $wall ) {
		$this->wall = $wall;
	}
	/**
	 * Create classes' instances where __construct isn't empty for hooks init
	 */
	public function includes() {
		$this->user();
		$this->posts();
		$this->comments();
		$this->friends();
		$this->followers();
	}

	/**
	 * @return User
	 */
	public function user() {
		if ( empty( UM()->classes['WallLib\common\user'] ) ) {
			UM()->classes['WallLib\common\user'] = new User( $this->wall );
		}
		return UM()->classes['WallLib\common\user'];
	}

	/**
	 * @return Posts
	 */
	public function posts() {
		if ( empty( UM()->classes['WallLib\common\posts'] ) ) {
			UM()->classes['WallLib\common\posts'] = new Posts( $this->wall );
		}
		return UM()->classes['WallLib\common\posts'];
	}

	/**
	 * @return Comments
	 */
	public function comments() {
		if ( empty( UM()->classes['WallLib\common\comments'] ) ) {
			UM()->classes['WallLib\common\comments'] = new Comments( $this->wall );
		}
		return UM()->classes['WallLib\common\comments'];
	}

	/**
	 * @return Friends
	 */
	public function friends() {
		if ( empty( UM()->classes['WallLib\common\friends'] ) ) {
			UM()->classes['WallLib\common\friends'] = new Friends( $this->wall );
		}
		return UM()->classes['WallLib\common\friends'];
	}

	/**
	 * @return Followers
	 */
	public function followers() {
		if ( empty( UM()->classes['WallLib\common\followers'] ) ) {
			UM()->classes['WallLib\common\followers'] = new Followers( $this->wall );
		}
		return UM()->classes['WallLib\common\followers'];
	}
}
