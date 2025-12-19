<?php
namespace WallLib\common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rewrite
 *
 * @package WallLib\common
 */
class Rewrite {

	private $wall;

	/**
	 * Rewrite constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;
		add_filter( 'query_vars', array( &$this, 'query_vars' ) );
		add_filter( 'rewrite_rules_array', array( &$this, 'add_rewrite_rules' ) );
		add_action( 'template_redirect', array( &$this, 'download_routing' ), 8 );
	}

	/**
	 * Modify global query vars
	 *
	 * @param $public_query_vars
	 *
	 * @return array
	 */
	public function query_vars( $public_query_vars ) {
		$public_query_vars[] = 'um_post';
		$public_query_vars[] = 'um_author';
		$public_query_vars[] = 'um_filename';
		$public_query_vars[] = 'um_verify';
		$public_query_vars[] = 'um_attachment_id';

		return $public_query_vars;
	}

	/**
	 * Add UM rewrite rules
	 *
	 * @param $rules
	 *
	 * @return array
	 */
	public function add_rewrite_rules( $rules ) {
		$new_rules = array();

		$allowed_image_types = array(
			'gif',
			'png',
			'jpeg',
			'jpg',
		);
		$allowed_image_types = implode( '|', $allowed_image_types );

		// NGINX-config `rewrite ^/um-wall-download/([^/]+)/([^/]+)/([^/]+)/\d{1,10}\.(gif|png|jpeg|jpg)$ /index.php?um_action=um-wall-download&um_post=$1&um_author=$2&um_verify=$3 last;`
		$new_rules[ 'um-wall-download/([^/]+)/([^/]+)/([^/]+)/(\d+)\.(' . $allowed_image_types . ')$' ] = 'index.php?um_action=um-wall-download&um_post=$matches[1]&um_author=$matches[2]&um_verify=$matches[3]&um_attachment_id=$matches[4]';

		return $new_rules + $rules;
	}

	/**
	 * @return bool
	 */
	public function download_routing() {
		if ( 'um-wall-download' !== get_query_var( 'um_action' ) ) {
			return false;
		}

		$post_id = get_query_var( 'um_post' );
		if ( empty( $post_id ) ) {
			return false;
		}

		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( empty( $post ) || is_wp_error( $post ) ) {
			return false;
		}

		if ( ! $this->wall->common()->user()->can_view_post( $post_id ) ) {
			return false;
		}

		$attachment_id = absint( get_query_var( 'um_attachment_id' ) );
		$is_attachment = false;
		if ( $attachment_id && get_post_type( $attachment_id ) === 'attachment' && wp_attachment_is_image( $attachment_id ) ) {
			$is_attachment = true;
			$uri           = wp_get_attachment_url( $attachment_id );
		} else {
			$uri = get_post_meta( $post_id, '_photo', true );
		}

		if ( ! $uri ) {
			return false;
		}

		$author_id = get_query_var( 'um_author' );
		if ( empty( $author_id ) ) {
			return false;
		}
		$author_id = absint( $author_id );

		$user = get_userdata( $author_id );
		if ( empty( $user ) || is_wp_error( $user ) ) {
			return false;
		}

		$verify = get_query_var( 'um_verify' );

		if ( empty( $verify ) ||
			! wp_verify_nonce( $verify, $author_id . $post_id . 'um-download-nonce' ) ) {
			return false;
		}

		$uri           = wp_basename( $uri );
		$user_base_dir = UM()->common()->filesystem()->get_user_uploads_dir( $author_id );
		$file_path     = wp_normalize_path( "$user_base_dir/$uri" );
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$size = filesize( $file_path );
		if ( true === $is_attachment ) {
			$originalname = get_the_title( $attachment_id );
			$type         = get_post_mime_type( $attachment_id );
		} else {
			$file_info    = get_post_meta( $post_id, '_photo_metadata', true );
			$originalname = $file_info['original_name'];
			$type         = $file_info['type'];
		}

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: ' . $type );
		header( 'Content-Disposition: inline; filename="' . $originalname . '"' );
		header( 'Content-Transfer-Encoding: binary' );

		header( 'Content-Length: ' . $size );

		do_action( $this->wall->prefix . 'wall_download_file_header' );

		$levels = ob_get_level();
		for ( $i = 0; $i < $levels; $i++ ) {
			@ob_end_clean();
		}

		readfile( $file_path );
		exit;
	}
}
