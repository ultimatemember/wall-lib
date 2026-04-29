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

		$admin_url = admin_url( 'edit.php?post_type=' . $this->wall->post_type );

		if ( empty( $_REQUEST['post_id'] ) ) {
			wp_safe_redirect( $admin_url );
			exit;
		}

		check_admin_referer( 'wall_report' . $_REQUEST['post_id'] );

		if ( ! is_numeric( $_REQUEST['post_id'] ) ) {
			wp_safe_redirect( $admin_url );
			exit;
		}

		$post_id = absint( $_REQUEST['post_id'] );

		if ( ! $this->wall->common()->posts()->reported( $post_id ) ) {
			wp_safe_redirect( $admin_url );
			exit;
		}

		delete_post_meta( $post_id, '_reported' );
		delete_post_meta( $post_id, '_reported_by' );

		$option = $this->wall->prefix . 'flagged';
		$count  = get_option( $option, 1 );
		if ( ! is_numeric( $count ) || $count < 1 ) {
			$count = 1;
		}
		update_option( $option, absint( $count - 1 ) );

		wp_safe_redirect( $admin_url );
		exit;
	}

	public function um_social_activity_adm_action_individual_nonce_actions( $actions ) {
		$actions[] = 'wall_report';
		return $actions;
	}
}
