<?php
namespace WallLib\admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Post
 *
 * @package WallLib\admin
 */
class Posts {

	private $wall;

	/**
	 * Post constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;

		// Add clear report action for wall posts.
		add_action( 'um_admin_do_action__wall_report', array( $this, 'um_admin_do_action__wall_report' ) );
		add_filter( 'um_adm_action_individual_nonce_actions', array( $this, 'um_social_activity_adm_action_individual_nonce_actions' ) );
	}

	/**
	 * Clear a wall post report.
	 */
	public function um_admin_do_action__wall_report() {
		if ( $this->wall->post_type !== $_REQUEST['post_type'] ) {
			return;
		}
		if ( empty( $_REQUEST['post_id'] ) || empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], "wall_report{$_REQUEST['post_id']}" ) ) {
			wp_die( esc_html__( 'Security check', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! is_numeric( $_REQUEST['post_id'] ) ) {
			die();
		}

		$post_id = absint( $_REQUEST['post_id'] );

		if ( ! $this->wall->common()->posts()->reported( $post_id ) ) {
			die();
		}

		delete_post_meta( $post_id, '_reported' );
		delete_post_meta( $post_id, '_reported_by' );

		$option = $this->wall->prefix . 'flagged';
		$count  = absint( get_option( $option ) );
		if ( $count < 1 ) {
			$count = 1;
		}
		update_option( $option, absint( $count - 1 ) );

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . $this->wall->post_type ) );
		exit;
	}

	public function um_social_activity_adm_action_individual_nonce_actions( $actions ) {
		$actions[] = 'wall_report';
		return $actions;
	}
}
