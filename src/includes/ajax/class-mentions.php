<?php
//namespace WallLib\ajax;
namespace Dev\UM_Activity\WallLib\ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_User_Query;

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

		add_action( 'wp_ajax_um_activity_get_user_suggestions', array( $this, 'get_user_suggestions' ) );
	}

	/**
	 * Get user suggestions
	 */
	public function get_user_suggestions() {
		check_ajax_referer( 'um_wall_mentions', 'nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_POST['term'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post name', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$current_user = get_current_user_id();

		$term = sanitize_text_field( $_POST['term'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$data = apply_filters( 'um_activity_ajax_get_user_suggestions', array(), $term );
		$data = array_filter(
			$data,
			function ( $v ) use ( $current_user ) {
				static $user_ids;
				if ( empty( $user_ids ) ) {
					$user_ids = array();
				}
				if ( empty ( $v['user_id'] ) ) {
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
}
