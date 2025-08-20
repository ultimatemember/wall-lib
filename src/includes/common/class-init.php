<?php
//namespace WallLib\common;
namespace Dev\UM_Activity\WallLib\common;

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
		$this->uploader();
		$this->rewrite();
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
	 * @return Uploader
	 */
	public function uploader() {
		if ( empty( UM()->classes['WallLib\common\uploader'] ) ) {
			UM()->classes['WallLib\common\uploader'] = new Uploader( $this->wall );
		}
		return UM()->classes['WallLib\common\uploader'];
	}

	/**
	 * @return Rewrite
	 */
	public function rewrite() {
		if ( empty( UM()->classes['WallLib\common\rewrite'] ) ) {
			UM()->classes['WallLib\common\rewrite'] = new Rewrite( $this->wall );
		}
		return UM()->classes['WallLib\common\rewrite'];
	}
}
