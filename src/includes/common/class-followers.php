<?php
namespace WallLib\common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Followers
 *
 * @package WallLib\common
 */
class Followers {

	private $wall;

	/**
	 * GDPR constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;
	}
	public function sss() {
		return 2;
	}
	/**
	 * Grab followed user IDs
	 *
	 * @return array|null
	 */
	public function followed_ids() {
		$array = array();

		if ( ! $this->followed_activity() ) {
			return null;
		}

		if ( ! is_user_logged_in() ) {
			return array( 0 );
		}

		$array[] = get_current_user_id();

		$following = UM()->Followers_API()->api()->following( get_current_user_id() );
		if ( $following ) {
			$array = array_merge( $array, $following );
		}

		if ( isset( $array ) ) {
			return $array;
		}

		return null;
	}

	/***
	 ***    @Check if enabled followed activity only
	 ***/
	public function followed_activity() {
		$option = apply_filters( $this->wall->prefix . 'wall_followed_users', true );
		if ( class_exists( 'UM_Followers_API' ) && $option ) {
			return true;
		}

		return false;
	}
}
