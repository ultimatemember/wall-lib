<?php
//namespace WallLib\common;
namespace Dev\UM_Activity\WallLib\common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Uploader
 *
 * @package WallLib\common
 */
class Uploader {

	private $wall;

	/**
	 * Uploader constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;
	}

	/**
	 * Gets post permalink
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	public function get_permalink( $post_id ) {
		$url = apply_filters( $this->wall->prefix . 'wall_get_core_page', '' );
		return add_query_arg( 'wall_post', $post_id, $url );
	}


}
