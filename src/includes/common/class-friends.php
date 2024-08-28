<?php
namespace WallLib\common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Friends
 *
 * @package WallLib\common
 */
class Friends {

	private $wall;

	/**
	 * GDPR constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;
	}

	/**
	 * Check if enabled friends activity only.
	 * @return bool
	 */
	public function friends_activity() {
		$option = apply_filters( $this->wall->prefix . 'wall_friends_users', true );
		if ( class_exists( 'UM_Friends_API' ) && $option ) {
			return true;
		}

		return false;
	}

	/**
	 * Grab friends user ids
	 *
	 * @return array|null
	 */
	public function friends_ids() {
		$array = array();

		if ( ! $this->friends_activity() ) {
			return null;
		}

		if ( ! is_user_logged_in() ) {
			return array( 0 );
		}

		$array[] = get_current_user_id();

		$friends = UM()->Friends_API()->api()->friends( get_current_user_id() );
		if ( $friends ) {
			foreach ( $friends as $arr ) {
				if ( absint( $arr['user_id1'] ) === get_current_user_id() ) {
					$array[] = $arr['user_id2'];
				} else {
					$array[] = $arr['user_id1'];
				}
			}
		}

		if ( isset( $array ) ) {
			return $array;
		}

		return null;
	}
}
