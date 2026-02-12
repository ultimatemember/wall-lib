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

	/**
	 * @var
	 */
	private $wall;

	/**
	 * @param $wall
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;
	}

	/**
	 * Create classes' instances where __construct isn't empty for hooks init
	 */
	public function includes() {
		$this->comments();
		$this->posts();
		$this->rewrite();
		$this->uploader();
		$this->user();
	}

	/**
	 * @return Comments
	 */
	public function comments() {
		if ( empty( UM()->classes[ $this->wall->prefix . 'WallLib\common\comments' ] ) ) {
			UM()->classes[ $this->wall->prefix . 'WallLib\common\comments' ] = new Comments( $this->wall );
		}
		return UM()->classes[ $this->wall->prefix . 'WallLib\common\comments' ];
	}

	/**
	 * @return Posts
	 */
	public function posts() {
		if ( empty( UM()->classes[ $this->wall->prefix . 'WallLib\common\posts' ] ) ) {
			UM()->classes[ $this->wall->prefix . 'WallLib\common\posts' ] = new Posts( $this->wall );
		}
		return UM()->classes[ $this->wall->prefix . 'WallLib\common\posts' ];
	}

	/**
	 * @return Rewrite
	 */
	public function rewrite() {
		if ( empty( UM()->classes[ $this->wall->prefix . 'WallLib\common\rewrite' ] ) ) {
			UM()->classes[ $this->wall->prefix . 'WallLib\common\rewrite' ] = new Rewrite( $this->wall );
		}
		return UM()->classes[ $this->wall->prefix . 'WallLib\common\rewrite' ];
	}

	/**
	 * @return Uploader
	 */
	public function uploader() {
		if ( empty( UM()->classes[ $this->wall->prefix . 'WallLib\common\uploader' ] ) ) {
			UM()->classes[ $this->wall->prefix . 'WallLib\common\uploader' ] = new Uploader( $this->wall );
		}
		return UM()->classes[ $this->wall->prefix . 'WallLib\common\uploader' ];
	}

	/**
	 * @return User
	 */
	public function user() {
		if ( empty( UM()->classes[ $this->wall->prefix . 'WallLib\common\user' ] ) ) {
			UM()->classes[ $this->wall->prefix . 'WallLib\common\user' ] = new User( $this->wall );
		}
		return UM()->classes[ $this->wall->prefix . 'WallLib\common\user' ];
	}
}
