<?php
namespace WallLib\ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Comments
 *
 * @package WallLib\ajax
 */
class Comments {

	private $wall;

	/**
	 * Comments constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;

		add_action( 'wp_ajax_um_wall_get_comment_likes', array( $this, 'get_comment_likes' ) );
		add_action( 'wp_ajax_nopriv_um_wall_get_comment_likes', array( $this, 'get_comment_likes' ) );

		add_action( 'wp_ajax_um_wall_like_comment', array( $this, 'like_comment' ) );
		add_action( 'wp_ajax_um_wall_unlike_comment', array( $this, 'unlike_comment' ) );

		add_action( 'wp_ajax_um_wall_post_comment', array( $this, 'post_comment' ) );
		add_action( 'wp_ajax_um_wall_edit_comment', array( $this, 'edit_comment' ) );

		add_action( 'wp_ajax_um_wall_load_more_comments', array( $this, 'load_more_comments' ) );
		add_action( 'wp_ajax_nopriv_um_wall_load_more_comments', array( $this, 'load_more_comments' ) );

		add_action( 'wp_ajax_um_wall_load_more_replies', array( $this, 'load_more_replies' ) );
		add_action( 'wp_ajax_nopriv_um_wall_load_more_replies', array( $this, 'load_more_replies' ) );

		add_action( 'wp_ajax_um_wall_remove_comment', array( $this, 'remove_comment' ) );
	}

	/***
	 ***    @load comment likes
	 ***/
	public function get_comment_likes() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['comment_id'] ) || ! $this->wall->common()->comments()->exists( absint( $_POST['comment_id'] ) ) ) {
			wp_send_json_error( __( 'Wrong 1comment ID.', $this->wall->textdomain ) );
		}

		$comment_id = absint( $_POST['comment_id'] );

		if ( ! wp_verify_nonce( $_POST['nonce'], 'um_wall_get_comment_likes' . $comment_id ) ) {
			wp_send_json_error( __( 'Wrong nonce.', $this->wall->textdomain ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! $this->wall->common()->user()->can_view_comment_likes( $comment_id ) ) {
			wp_send_json_error( __( 'You are not authorized to see likes.', $this->wall->textdomain ) );
		}

		$likes = get_comment_meta( $comment_id, '_liked', true );
		if ( empty( $likes ) ) {
			$likes = array();
		}

		$template = apply_filters( $this->wall->prefix . 'wall_likes_template', 'modal/likes.php' );

		$content = UM()->get_template(
			$template,
			$this->wall->plugin_basename,
			array(
				'likes'   => $likes,
				'context' => 'comment',
			)
		);

		wp_send_json_success( array( 'content' => $content ) );
	}

	/**
	 * Like wall comment.
	 *
	 */
	public function like_comment() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['comment_id'] ) || ! $this->wall->common()->comments()->exists( absint( $_POST['comment_id'] ) ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) );
		}

		$comment_id = absint( $_POST['comment_id'] );

		if ( ! wp_verify_nonce( $_POST['nonce'], 'um_wall_like_comment' . $comment_id ) ) {
			wp_send_json_error( __( 'Wrong nonce.', $this->wall->textdomain ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must login to like', $this->wall->textdomain ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! $this->wall->common()->user()->can_like_comment( $comment_id ) ) {
			wp_send_json_error( __( 'You are not authorized to like this comment.', $this->wall->textdomain ) );
		}

		$liked = get_comment_meta( $comment_id, '_liked', true );
		if ( is_array( $liked ) && in_array( get_current_user_id(), $liked, true ) ) {
			wp_send_json_error( __( 'You already liked this comment', $this->wall->textdomain ) );
		}

		$increase_likes = false;
		$likes          = get_comment_meta( $comment_id, '_likes', true );
		$likes          = absint( $likes );

		if ( empty( $liked ) || ! is_array( $liked ) ) {
			$liked          = array( get_current_user_id() );
			$increase_likes = true;
		} else {
			if ( ! in_array( get_current_user_id(), $liked, true ) ) {
				$liked[]        = get_current_user_id();
				$increase_likes = true;
			}
		}

		if ( $increase_likes ) {
			update_comment_meta( $comment_id, '_liked', $liked );
			$likes ++;
			update_comment_meta( $comment_id, '_likes', $likes );
		}

		$content = UM()->frontend()::layouts()::avatars_list(
			$liked,
			array(
				'wrapper' => 'span',
				'size'    => 'xs',
				'count'   => 5,
			)
		);

		wp_send_json_success(
			array(
				'likes'   => $likes,
				'content' => UM()->ajax()->esc_html_spaces( $content ),
			)
		);
	}

	/**
	 * Unlike wall comment.
	 *
	 */
	public function unlike_comment() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['comment_id'] ) || ! $this->wall->common()->comments()->exists( absint( $_POST['comment_id'] ) ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) );
		}

		$comment_id = absint( $_POST['comment_id'] );

		if ( ! wp_verify_nonce( $_POST['nonce'], 'um_wall_unlike_comment' . $comment_id ) ) {
			wp_send_json_error( __( 'Wrong nonce.', $this->wall->textdomain ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must login to unlike', $this->wall->textdomain ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! $this->wall->common()->user()->can_unlike_comment( $comment_id ) ) {
			wp_send_json_error( __( 'You are not authorized to unlike this comment.', $this->wall->textdomain ) );
		}

		$liked = get_comment_meta( $comment_id, '_liked', true );
		if ( empty( $liked ) || ! is_array( $liked ) ) {
			wp_send_json_error( __( 'Invalid comment data', $this->wall->textdomain ) );
		}

		if ( ! in_array( get_current_user_id(), $liked, true ) ) {
			wp_send_json_error( __( 'You didn\'t like this comment', $this->wall->textdomain ) );
		}

		$likes = get_comment_meta( $comment_id, '_likes', true );
		$likes = absint( $likes );

		$liked = array_diff( $liked, array( get_current_user_id() ) );
		update_comment_meta( $comment_id, '_liked', $liked );

		$likes --;
		$likes = 0 < $likes ? $likes : 0;
		update_comment_meta( $comment_id, '_likes', $likes );

		$content = UM()->frontend()::layouts()::avatars_list(
			$liked,
			array(
				'wrapper' => 'span',
				'size'    => 'xs',
				'count'   => 5,
			)
		);

		wp_send_json_success(
			array(
				'likes'   => $likes,
				'content' => UM()->ajax()->esc_html_spaces( $content ),
			)
		);
	}

	/**
	 * Post comment.
	 *
	 */
	public function post_comment() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['post_id'] ) || ! $this->wall->common()->posts()->exists( absint( $_POST['post_id'] ) ) ) {
			$error = esc_html__( 'Wrong post ID.', $this->wall->textdomain );
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Submission error', $this->wall->textdomain ),
						array(
							'type'       => 'error',
							'underline'  => false,
							'supporting' => $error,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}
		$post_id = absint( $_POST['post_id'] );

		if ( ! wp_verify_nonce( $_POST['nonce'], 'um_wall_comment_post' . $post_id ) ) {
			$error = esc_html__( 'Wrong nonce.', $this->wall->textdomain );
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Submission error', $this->wall->textdomain ),
						array(
							'type'       => 'error',
							'underline'  => false,
							'supporting' => $error,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		if ( ! $this->wall->common()->user()->can_comment() ) {
			$error = esc_html__( 'You can\'t comment this post.', $this->wall->textdomain );
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Submission error', $this->wall->textdomain ),
						array(
							'type'       => 'error',
							'underline'  => false,
							'supporting' => $error,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		if ( empty( sanitize_textarea_field( $_POST['comment'] ) ) ) {
			$error = esc_html__( 'Empty comment.', $this->wall->textdomain );
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Submission error', $this->wall->textdomain ),
						array(
							'type'       => 'error',
							'underline'  => false,
							'supporting' => $error,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		um_fetch_user( get_current_user_id() );

		$time = current_time( 'mysql' );

		$orig_content = wp_kses(
			trim( sanitize_textarea_field( $_POST['comment'] ) ),
			array(
				'br' => array(),
			)
		);
		$comment_content = apply_filters( $this->wall->prefix . 'wall_comment_content_new', $orig_content, $post_id );
		// apply hashtag
		$this->wall->common()->posts()->hashtagit( $post_id, $comment_content, true );

		$comment_content = $this->wall->common()->posts()->hashtag_links( $comment_content );
		$comment_content = apply_filters( $this->wall->prefix . 'wall_insert_post_content_filter', $comment_content, get_current_user_id(), absint( $post_id ), 'new' );
		$comment_content = $this->wall->common()->posts()->make_links_clickable( $comment_content );
		$comment_content = stripslashes_deep( $comment_content );
		$comment_content = convert_smilies( $comment_content );

		um_fetch_user( get_current_user_id() );

		$data = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => um_user( 'display_name' ),
			'comment_author_email' => um_user( 'user_email' ),
			'comment_author_url'   => um_user_profile_url(),
			'comment_content'      => $comment_content,
			'user_id'              => get_current_user_id(),
			'comment_approved'     => 1,
			'comment_author_IP'    => um_user_ip(),
			'comment_type'         => 'um-social-activity',
			'comment_parent'       => 0,
			'comment_date'         => $time,
		);

		if ( isset( $_POST['comment_id'] ) && absint( $_POST['comment_id'] ) ) {
			if ( empty( $_POST['comment_id'] ) || ! $this->wall->common()->comments()->exists( absint( $_POST['comment_id'] ) ) ) {
				$error = esc_html__( 'Wrong comment ID.', $this->wall->textdomain );
				wp_send_json_error(
					wp_kses(
						UM()->frontend()::layouts()::alert(
							esc_html__( 'Submission error', $this->wall->textdomain ),
							array(
								'type'       => 'error',
								'underline'  => false,
								'supporting' => $error,
							)
						),
						UM()->get_allowed_html( 'templates' )
					)
				);
			}
			$data['comment_parent'] = absint( $_POST['comment_id'] );
		}

		$commentid = wp_insert_comment( $data );

		if ( isset( $_POST['reply_to'] ) && absint( $_POST['reply_to'] ) ) {
			$comment_parent = $data['comment_parent'];
			do_action( $this->wall->prefix . 'wall_after_wall_comment_reply_published', $commentid, $comment_parent, $post_id, get_current_user_id() );
		} else {
			$comment_parent = 0;
		}

		$comment_count = get_post_meta( $post_id, '_comments', true );
		update_post_meta( $post_id, '_comments', $comment_count + 1 );

		do_action( $this->wall->prefix . 'wall_after_wall_comment_published', $commentid, $comment_parent, $post_id, get_current_user_id() );
		$post_link = $this->wall->common()->posts()->get_permalink( $post_id );
		$comment   = get_comment( $commentid );
		$comments  = array( $comment );

		if ( isset( $_POST['reply_to'] ) && absint( $_POST['reply_to'] ) ) {
			$t_args = array(
				'commentc'  => $comments[0],
				'post_id'   => $post_id,
				'post_link' => $post_link,
			);
			$template = apply_filters( $this->wall->prefix . 'wall_comment_reply_template', 'comment-reply.php' );
			$output = UM()->get_template( $template, $this->wall->plugin_basename, $t_args, false );
		} else {
			$t_args = array(
				'comments'  => $comments,
				'post_id'   => $post_id,
				'post_link' => $post_link,
			);
			$template = apply_filters( $this->wall->prefix . 'wall_comment_template', 'comment.php' );
			$output = UM()->get_template( $template, $this->wall->plugin_basename, $t_args, false );
		}

		$status = wp_kses(
			UM()->frontend()::layouts()::alert(
				esc_html__( 'Submission error', $this->wall->textdomain ),
				array(
					'type'       => 'success',
					'underline'  => false,
					'supporting' => esc_html__( 'Comment posted successfully.', $this->wall->textdomain ),
				)
			),
			UM()->get_allowed_html( 'templates' )
		);
		// phpcs:enable WordPress.Security.NonceVerification
		wp_send_json_success(
			array(
				'content' => UM()->ajax()->esc_html_spaces( $output ),
				'status'  => $status,
			)
		);
	}

	/**
	 * Edit comment.
	 *
	 */
	public function edit_comment() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['post_id'] ) || ! $this->wall->common()->posts()->exists( absint( $_POST['post_id'] ) ) ) {
			$error = esc_html__( 'Wrong post ID.', $this->wall->textdomain );
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Submission error', $this->wall->textdomain ),
						array(
							'type'       => 'error',
							'underline'  => false,
							'supporting' => $error,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}
		$post_id = absint( $_POST['post_id'] );

		if ( empty( $_POST['comment_id'] ) || ! $this->wall->common()->comments()->exists( absint( $_POST['comment_id'] ) ) ) {
			$error = esc_html__( 'Wrong comment ID.', $this->wall->textdomain );
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Submission error', $this->wall->textdomain ),
						array(
							'type'       => 'error',
							'underline'  => false,
							'supporting' => $error,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}
		$commentid = absint( $_POST['comment_id'] );

		if ( ! wp_verify_nonce( $_POST['nonce'], 'um_wall_comment_edit' . $commentid ) ) {
			$error = esc_html__( 'Wrong nonce.', $this->wall->textdomain );
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Submission error', $this->wall->textdomain ),
						array(
							'type'       => 'error',
							'underline'  => false,
							'supporting' => $error,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		if ( ! $this->wall->common()->user()->can_edit_comment() ) {
			$error = esc_html__( 'You can\'t edit this comment.', $this->wall->textdomain );
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Submission error', $this->wall->textdomain ),
						array(
							'type'       => 'error',
							'underline'  => false,
							'supporting' => $error,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		um_fetch_user( get_current_user_id() );

		$orig_content = wp_kses(
			trim( sanitize_textarea_field( $_POST['comment'] ) ),
			array(
				'br' => array(),
			)
		);
		$comment_content = apply_filters( $this->wall->prefix . 'wall_comment_content_new', $orig_content, $post_id );

		// apply hashtag
		$this->wall->common()->posts()->hashtagit( $post_id, $comment_content, true );

		$comment_content = $this->wall->common()->posts()->hashtag_links( $comment_content );
		$comment_content = apply_filters( $this->wall->prefix . 'wall_insert_post_content_filter', $comment_content, get_current_user_id(), absint( $post_id ), 'new' );
		$comment_content = $this->wall->common()->posts()->make_links_clickable( $comment_content );
		$comment_content = stripslashes_deep( $comment_content );
		$comment_content = convert_smilies( $comment_content );

		um_fetch_user( get_current_user_id() );

		$data = array(
			'comment_content' => $comment_content,
			'comment_ID'      => $commentid,
		);

		$updated = wp_update_comment( $data );

		if ( ! $updated ) {
			$error = esc_html__( 'Something goes wrong.', $this->wall->textdomain );
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Submission error', $this->wall->textdomain ),
						array(
							'type'       => 'error',
							'underline'  => false,
							'supporting' => $error,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}
		$comment_parent = 0;

		do_action( $this->wall->prefix . 'wall_after_wall_comment_edited', $commentid, $comment_parent, $post_id, get_current_user_id() );

		// phpcs:enable WordPress.Security.NonceVerification
		wp_send_json_success(
			array(
				'content' => UM()->ajax()->esc_html_spaces( $comment_content ),
			)
		);
	}

	/**
	 * Load wall comments via AJAX
	 */
	public function load_more_comments() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['post_id'] ) || ! $this->wall->common()->posts()->exists( absint( $_POST['post_id'] ) ) ) {
			wp_send_json_error( __( 'Wrong post ID.', $this->wall->textdomain ) );
		}

		$post_id = absint( $_POST['post_id'] );

		if ( ! wp_verify_nonce( $_POST['nonce'], 'um_wall_comments_loadmore' . $post_id ) ) {
			wp_send_json_error( __( 'Wrong nonce.', $this->wall->textdomain ) );
		}

		if ( ! $this->wall->common()->user()->can_view_post( $post_id ) ) {
			wp_send_json_error( __( 'You are not authorized to load comments.', $this->wall->textdomain ) );
		}

		$number    = apply_filters( $this->wall->prefix . 'wall_comments_loadmore_number', 10 );
		$order     = apply_filters( $this->wall->prefix . 'wall_comments_loadmore_order', 'asc' );
		$offset    = absint( $_POST['offset'] );
		$post_link = $this->wall->common()->posts()->get_permalink( $post_id );
		// phpcs:enable WordPress.Security.NonceVerification

		$comments = get_comments(
			array(
				'post_id' => $post_id,
				'parent'  => 0,
				'number'  => absint( $number ),
				'offset'  => $offset,
				'order'   => $order,
			)
		);

		$comments_all = $this->wall->common()->comments()->get_comments_number( $post_id );

		$t_args = array(
			'comments'  => $comments,
			'post_id'   => $post_id,
			'post_link' => $post_link,
		);

		$template = apply_filters( $this->wall->prefix . 'wall_comment_template', 'comment.php' );

		$content = UM()->get_template( $template, $this->wall->plugin_basename, $t_args, false );
		if ( $comments_all > ( absint( $offset ) + absint( $number ) ) ) {
			$loadmore = true;
		} else {
			$loadmore = false;
		}

		wp_send_json_success(
			array(
				'content'  => $content,
				'loadmore' => $loadmore,
				'offset'   => absint( $offset ) + absint( $number ),
				'count'    => absint( $comments_all ) - absint( $offset ) - absint( $number ),
			)
		);
	}

	/**
	 * Load wall replies via AJAX
	 */
	public function load_more_replies() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['post_id'] ) || ! $this->wall->common()->posts()->exists( absint( $_POST['post_id'] ) ) ) {
			wp_send_json_error( __( 'Wrong post ID.', $this->wall->textdomain ) );
		}
		$post_id = absint( $_POST['post_id'] );

		if ( empty( $_POST['comment_id'] ) || ! $this->wall->common()->comments()->exists( absint( $_POST['comment_id'] ) ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) );
		}
		$comment_id = absint( $_POST['comment_id'] );

		if ( ! wp_verify_nonce( $_POST['nonce'], 'um_wall_replies_loadmore' . $comment_id ) ) {
			wp_send_json_error( __( 'Wrong nonce.', $this->wall->textdomain ) );
		}

		if ( ! $this->wall->common()->user()->can_view_comment( $comment_id ) ) {
			wp_send_json_error( __( 'You are not authorized to load comments.', $this->wall->textdomain ) );
		}

		$number    = apply_filters( $this->wall->prefix . 'wall_comments_loadmore_number', 10 );
		$order     = apply_filters( $this->wall->prefix . 'wall_comments_loadmore_order', 10 );
		$offset    = absint( $_POST['offset'] );
		$post_link = $this->wall->common()->posts()->get_permalink( $post_id );
		// phpcs:enable WordPress.Security.NonceVerification

		$child = get_comments(
			array(
				'post_id' => $post_id,
				'parent'  => $comment_id,
				'number'  => absint( $number ),
				'offset'  => $offset,
				'order'   => $order,
			)
		);

		$child_all = $this->wall->common()->comments()->get_replies_number( $post_id, $comment_id );

		$content = '';
		foreach ( $child as $commentc ) {
			um_fetch_user( $commentc->user_id );

			$t_args = array(
				'commentc'  => $commentc,
				'post_id'   => $post_id,
				'post_link' => $post_link,
			);

			$template = apply_filters( $this->wall->prefix . 'wall_comment_reply_template', 'comment-reply.php' );
			$content .= UM()->get_template( $template, $this->wall->plugin_basename, $t_args, false );
		}

		if ( absint( $child_all ) > ( absint( $offset ) + absint( $number ) ) ) {
			$loadmore = true;
		} else {
			$loadmore = false;
		}

		wp_send_json_success(
			array(
				'content'  => $content,
				'loadmore' => $loadmore,
				'offset'   => absint( $offset ) + absint( $number ),
				'count'    => absint( $child_all ) - absint( $offset ) - absint( $number ),
			)
		);
	}

	/**
	 * Removes a wall comment via AJAX
	 */
	public function remove_comment() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['comment_id'] ) || ! $this->wall->common()->comments()->exists( absint( $_POST['comment_id'] ) ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) );
		}
		$comment_id = absint( $_POST['comment_id'] );

		if ( ! wp_verify_nonce( $_POST['nonce'], 'um_wall_delete_comment' . $comment_id ) ) {
			wp_send_json_error( __( 'Wrong nonce.', $this->wall->textdomain ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification
		if ( ! $this->wall->common()->comments()->exists( $comment_id ) ) {
			wp_send_json_error( __( 'You are not authorized to delete this comment.', $this->wall->textdomain ) );
		}

		if ( $this->wall->common()->user()->can_edit_comment( $comment_id, get_current_user_id() ) ) {
			$this->wall->common()->comments()->delete_comment( $comment_id );
			wp_send_json_success();
		}

		// Post authors can delete spam and malicious comments under their posts.
		$comment   = get_comment( $comment_id );
		$author_id = $this->wall->common()->post()->get_author( $comment->comment_post_ID );
		if ( get_current_user_id() === $author_id ) {
			$this->wall->common()->comments()->delete_comment( $comment_id );
		}

		wp_send_json_success();
	}
}
