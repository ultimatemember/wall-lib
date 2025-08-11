<?php
//namespace WallLib\integrations;
namespace Dev\UM_Activity\WallLib\integrations;

//use WallLib\integrations\Followers;
//use WallLib\integrations\Friends;
use Dev\UM_Activity\WallLib\integrations\Followers;
use Dev\UM_Activity\WallLib\integrations\Friends;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Init
 *
 * @package WallLib\inteegrations
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
		$this->friends();
		$this->followers();
	}

	/**
	 * @return Friends
	 */
	public function friends() {
		if ( empty( UM()->classes['WallLib\integrations\friends'] ) ) {
			UM()->classes['WallLib\integrations\friends'] = new Friends( $this->wall );
		}
		return UM()->classes['WallLib\integrations\friends'];
	}

	/**
	 * @return Followers
	 */
	public function followers() {
		if ( empty( UM()->classes['WallLib\integrations\followers'] ) ) {
			UM()->classes['WallLib\integrations\followers'] = new Followers( $this->wall );
		}
		return UM()->classes['WallLib\integrations\followers'];
	}
}
