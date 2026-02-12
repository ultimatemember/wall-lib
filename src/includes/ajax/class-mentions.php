<?php
namespace WallLib\ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mentions
 *
 * @package WallLib\ajax
 */
class Mentions {

	private $wall;

	/**
	 * Mentions constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;

		add_action( 'wp_ajax_' . $this->wall->prefix . 'get_user_suggestions', array( $this, 'get_user_suggestions' ) );
	}

	/**
	 * Get user suggestions
	 */
	public function get_user_suggestions() {
		check_ajax_referer( 'um_wall_mentions', 'nonce' );

		do_action( $this->wall->prefix . 'ajax_get_user_suggestions_before' );

		if ( ! isset( $_POST['term'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post name', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$current_user = get_current_user_id();

		$term = sanitize_text_field( $_POST['term'] );
		$data = apply_filters( $this->wall->prefix . 'ajax_get_user_suggestions', array(), $term, $this );
		$data = array_filter(
			$data,
			function ( $v ) use ( $current_user ) {
				static $user_ids;
				if ( empty( $user_ids ) ) {
					$user_ids = array();
				}
				if ( empty( $v['user_id'] ) ) {
					return false;
				}
				$user_id = absint( $v['user_id'] );
				// Avoid mention yourself.
				if ( $current_user === $user_id ) {
					return false;
				}
				if ( ! in_array( $user_id, $user_ids, true ) ) {
					$user_ids[] = $user_id;
					return true;
				}
				return false;
			}
		);
		$data = array_values( $data );
		wp_send_json_success( $data );
	}

	/**
	 * Fetch mentioned user
	 *
	 * @param int $user_id The ID of the user being fetched
	 * @param string $term The search term to match in the user's display name
	 *
	 * @return array|bool Array of user data if found, false otherwise
	 */
	public function fetch_mentioned_user( $user_id, $term ) {
		um_fetch_user( $user_id );
		$display_name = um_user( 'display_name' );

		$start = mb_stripos( $display_name, $term );
		if ( false === $start ) {
			return false;
		}
		$find_length = mb_strlen( $term );

		$first_sub  = mb_substr( $display_name, 0, $start );
		$second_sub = mb_substr( $display_name, $start, $find_length );
		$third_sub  = mb_substr( $display_name, $start + $find_length );
		$name       = $first_sub . '<strong>' . $second_sub . '</strong>' . $third_sub;

		$users_data             = array();
		$users_data[ $user_id ] = array(
			'user_id'  => $user_id,
			'photo'    => get_avatar( $user_id, 80 ),
			'name'     => $name,
			'username' => $display_name,
		);

		return $users_data;
	}
}
