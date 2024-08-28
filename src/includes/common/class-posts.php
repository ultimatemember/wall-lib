<?php
namespace WallLib\common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Post
 *
 * @package WallLib\common
 */
class Posts {

	private $wall;

	/**
	 * Post constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;
	}

	/**
	 *
	 * @param int|WP_Post $post Post ID or Post WP_Post object.
	 *
	 * @return bool
	 */
	public function exists( $post ) {
		$status = get_post_status( $post );
		return false !== $status;
	}

	/**
	 * Gets post wall ID
	 *
	 * @param int $post_id
	 *
	 * @return int
	 */
	public function get_wall( $post_id ) {
		$wall = absint( get_post_meta( $post_id, '_wall_id', true ) );
		return ( $wall ) ? $wall : 0;
	}

	/**
	 * Gets post author
	 *
	 * @param int $post_id
	 *
	 * @return int
	 */
	public function get_author( $post_id ) {
		$author = get_post_meta( $post_id, '_user_id', true );
		if ( empty( $author ) ) {
			$post = get_post( $post_id );
			if ( empty( $post ) ) {
				return 0;
			}
			$author = $post->post_author;
		}
		return ! empty( $author ) ? absint( $author ) : 0;
	}
}
