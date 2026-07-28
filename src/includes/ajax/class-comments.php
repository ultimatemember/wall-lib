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

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_get_comment_likes', array( $this, 'get_comment_likes' ) );
		add_action( 'wp_ajax_nopriv_' . $this->wall->prefix . 'wall_get_comment_likes', array( $this, 'get_comment_likes' ) );

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_like_comment', array( $this, 'like_comment' ) );
		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_unlike_comment', array( $this, 'unlike_comment' ) );

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_post_comment', array( $this, 'post_comment' ) );
		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_edit_comment', array( $this, 'edit_comment' ) );

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_load_more_comments', array( $this, 'load_more_comments' ) );
		add_action( 'wp_ajax_nopriv_' . $this->wall->prefix . 'wall_load_more_comments', array( $this, 'load_more_comments' ) );

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_load_more_replies', array( $this, 'load_more_replies' ) );
		add_action( 'wp_ajax_nopriv_' . $this->wall->prefix . 'wall_load_more_replies', array( $this, 'load_more_replies' ) );

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_remove_comment', array( $this, 'remove_comment' ) );
	}

	/**
	 * Load comment likes.
	 *
	 * @return void
	 */
	public function get_comment_likes() {
		if ( empty( $_POST['comment_id'] ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$comment_id = absint( $_POST['comment_id'] );

		check_ajax_referer( 'um_wall_get_comment_likes' . $comment_id, 'nonce' );

		if ( UM()->is_rate_limited( 'wall_get_comment_likes' ) ) {
			wp_send_json_error( __( 'Too many requests', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! $this->wall->common()->comments()->exists( $comment_id ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! $this->wall->common()->user()->can_view_comment_likes( $comment_id ) ) {
			wp_send_json_error( __( 'You are not authorized to see likes.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$likes = get_comment_meta( $comment_id, '_liked', true );
		if ( empty( $likes ) ) {
			$likes = array();
		}

		$template = apply_filters( $this->wall->prefix . 'wall_likes_template', 'modal/likes.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.

		$content = UM()->get_template(
			$template,
			$this->wall->plugin_basename,
			array(
				'likes'   => $likes,
				'context' => 'comment',
			)
		);

		wp_send_json_success( array( 'content' => UM()->ajax()->esc_html_spaces( $content ) ) );
	}

	/**
	 * Like wall comment.
	 *
	 */
	public function like_comment() {
		if ( empty( $_POST['comment_id'] ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$comment_id = absint( $_POST['comment_id'] );

		check_ajax_referer( 'um_wall_like_comment' . $comment_id, 'nonce' );

		if ( ! $this->wall->common()->comments()->exists( $comment_id ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! $this->wall->common()->user()->can_like_comment( $comment_id ) ) {
			wp_send_json_error( __( 'You are not authorized to like this comment.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		do_action( $this->wall->prefix . 'before_wall_comment_liked', $comment_id, get_current_user_id() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.

		$liked = get_comment_meta( $comment_id, '_liked', true );
		if ( is_array( $liked ) && in_array( get_current_user_id(), $liked, true ) ) {
			wp_send_json_error( __( 'You already liked this comment', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$liked_array = is_array( $liked ) ? $liked : array();
		if ( ! empty( $liked ) ) {
			foreach ( $liked as $key => $user_id ) {
				if ( ! UM()->common()->users()->can_view_user( $user_id ) ) {
					unset( $liked_array[ $key ] );
				}
			}
		}
		$increase_likes = false;
		$likes          = count( $liked_array );

		if ( empty( $liked ) || ! is_array( $liked ) ) {
			$liked          = array( get_current_user_id() );
			$increase_likes = true;
		} elseif ( ! in_array( get_current_user_id(), $liked, true ) ) {
			$liked[]        = get_current_user_id();
			$increase_likes = true;
		}

		if ( $increase_likes ) {
			update_comment_meta( $comment_id, '_liked', $liked );
			++$likes;
			update_comment_meta( $comment_id, '_likes', $likes );
		}

		do_action( $this->wall->prefix . 'after_wall_comment_liked', $comment_id, get_current_user_id() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.

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
		if ( empty( $_POST['comment_id'] ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$comment_id = absint( $_POST['comment_id'] );

		check_ajax_referer( 'um_wall_unlike_comment' . $comment_id, 'nonce' );

		if ( ! $this->wall->common()->comments()->exists( $comment_id ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! $this->wall->common()->user()->can_unlike_comment( $comment_id ) ) {
			wp_send_json_error( __( 'You are not authorized to unlike this comment.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		do_action( $this->wall->prefix . 'before_wall_comment_unliked', $comment_id, get_current_user_id() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.

		$liked = get_comment_meta( $comment_id, '_liked', true );
		if ( empty( $liked ) || ! is_array( $liked ) ) {
			wp_send_json_error( __( 'Invalid comment data', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! in_array( get_current_user_id(), $liked, true ) ) {
			wp_send_json_error( __( 'You didn\'t like this comment', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$liked_array = is_array( $liked ) ? $liked : array();
		if ( ! empty( $liked ) ) {
			foreach ( $liked as $key => $user_id ) {
				if ( ! UM()->common()->users()->can_view_user( $user_id ) ) {
					unset( $liked_array[ $key ] );
				}
			}
		}

		$likes = count( $liked_array );

		$liked = array_diff( $liked, array( get_current_user_id() ) );
		update_comment_meta( $comment_id, '_liked', $liked );

		--$likes;
		$likes = 0 < $likes ? $likes : 0;
		update_comment_meta( $comment_id, '_likes', $likes );

		do_action( $this->wall->prefix . 'after_wall_comment_unliked', $comment_id, get_current_user_id() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.

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
	 */
	public function post_comment() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['post_id'] ) ) {
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Wrong post ID.', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		$post_id = absint( $_POST['post_id'] );

		check_ajax_referer( 'um_wall_comment_post' . $post_id, 'nonce' );

		if ( ! $this->wall->common()->posts()->exists( $post_id ) ) {
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Wrong post ID.', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		if ( ! $this->wall->common()->user()->can_comment() ) {
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'You can\'t comment this post.', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		if ( empty( $_POST['comment'] ) ) {
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Empty comment.', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		$orig_content = sanitize_textarea_field( wp_unslash( $_POST['comment'] ) );
		if ( empty( $orig_content ) ) {
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Empty comment.', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		do_action( $this->wall->prefix . 'before_wall_comment_published', $post_id, get_current_user_id() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.

		um_fetch_user( get_current_user_id() );

		$time     = current_time( 'mysql' );
		$time_gmt = current_time( 'mysql', true );

		$orig_content    = wp_kses(
			$orig_content,
			array(
				'br' => array(),
			)
		);
		$comment_content = apply_filters( $this->wall->prefix . 'wall_comment_content_new', $orig_content, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.
		$comment_content = $this->prepare_comment_content( $comment_content );

		$data = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => um_user( 'display_name' ),
			'comment_author_email' => um_user( 'user_email' ),
			'comment_author_url'   => um_user_profile_url(),
			'comment_content'      => wp_slash( $comment_content ),
			'user_id'              => get_current_user_id(),
			'comment_approved'     => 1,
			'comment_author_IP'    => um_user_ip(),
			'comment_type'         => 'um-social-activity',
		);

		$comment_parent = ! empty( $_POST['reply_to'] ) ? absint( $_POST['reply_to'] ) : 0;

		$data['comment_parent']   = $comment_parent;
		$data['comment_date']     = $time;
		$data['comment_date_gmt'] = $time_gmt;

		$data = apply_filters( $this->wall->prefix . 'insert_comment_args', $data ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.

		$commentid = wp_insert_comment( $data );

		if ( empty( $commentid ) ) {
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Something went wrong with comment store', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		} else {
			// Apply hashtags for the post
			$this->wall->common()->posts()->hashtagit( $post_id, $orig_content, true );

			$linkified = $this->wall->common()->posts()->linkify_hashtags_in_content( $comment_content );
			if ( $comment_content !== $linkified ) {
				$data = array(
					'comment_ID'      => $commentid,
					'comment_content' => wp_slash( $linkified ),
				);
				wp_update_comment( $data );
			}

			$output['comment_content'] = nl2br( $linkified );

			if ( $comment_parent ) {
				do_action( $this->wall->prefix . 'after_wall_comment_reply_published', $commentid, $comment_parent, $post_id, get_current_user_id() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.
			} else {
				do_action( $this->wall->prefix . 'after_wall_comment_published', $commentid, $comment_parent, $post_id, get_current_user_id() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.
			}

			$comment_count = get_post_meta( $post_id, '_comments', true );
			update_post_meta( $post_id, '_comments', $comment_count + 1 );
			update_comment_meta( $commentid, 'orig_content', wp_slash( $orig_content ) );
			update_comment_meta( $commentid, '_um_comment_version', $this->wall->plugin_version );

			$post_link = $this->wall->common()->posts()->get_permalink( $post_id );
			$comment   = get_comment( $commentid );
			$comments  = array( $comment );
			$comm_num  = $this->wall->common()->comments()->get_comments_per_page();

			if ( $comment_parent ) {
				$t_args = array(
					'commentc'         => $comments[0],
					'post_id'          => $post_id,
					'post_link'        => $post_link,
					'um_activity_wall' => $this->wall,
					'comm_num'         => $comm_num,
				);

				$t_args   = apply_filters( $this->wall->prefix . 'wall_comment_reply_template_args', $t_args, $comments, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.
				$template = apply_filters( $this->wall->prefix . 'wall_comment_reply_template', 'comment-reply.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.
			} else {
				$t_args = array(
					'comments'         => $comments,
					'post_id'          => $post_id,
					'post_link'        => $post_link,
					'um_activity_wall' => $this->wall,
					'comm_num'         => $comm_num,
					'order_comment'    => $this->wall->common()->comments()->get_comments_order(),
				);

				$t_args   = apply_filters( $this->wall->prefix . 'wall_comment_template_args', $t_args, $comments, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.
				$template = apply_filters( $this->wall->prefix . 'wall_comment_template', 'comment.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.
			}

			$output = UM()->get_template( $template, $this->wall->plugin_basename, $t_args );

			// phpcs:enable WordPress.Security.NonceVerification
			wp_send_json_success(
				array(
					'content' => UM()->ajax()->esc_html_spaces( $output ),
				)
			);
		}
	}

	/**
	 * Edit comment.
	 *
	 */
	public function edit_comment() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['comment_id'] ) ) {
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Wrong comment ID.', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}
		$commentid = absint( $_POST['comment_id'] );

		check_ajax_referer( 'um_wall_comment_edit' . $commentid, 'nonce' );

		if ( ! $this->wall->common()->comments()->exists( $commentid ) ) {
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Wrong comment ID.', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		if ( ! $this->wall->common()->user()->can_edit_comment( $commentid, get_current_user_id() ) ) {
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'You can\'t edit this comment.', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		if ( empty( $_POST['comment'] ) ) {
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Empty comment.', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		$orig_content = sanitize_textarea_field( wp_unslash( $_POST['comment'] ) );
		if ( empty( $orig_content ) ) {
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Empty comment.', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		$old_data = get_comment( $commentid );
		$post_id  = $old_data->comment_post_ID;

		if ( ! $this->wall->common()->posts()->exists( $post_id ) ) {
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						esc_html__( 'Wrong post ID.', $this->wall->textdomain ), // phpcs:ignore WordPress.WP.I18n
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}

		do_action( $this->wall->prefix . 'before_wall_comment_updated', $commentid, get_current_user_id() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.

		um_fetch_user( get_current_user_id() );

		$orig_content    = wp_kses(
			$orig_content,
			array(
				'br' => array(),
			)
		);
		$comment_content = apply_filters( $this->wall->prefix . 'wall_comment_content_edit', $orig_content, $post_id, $commentid ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.

		$old_data->comment_meta = get_comment_meta( $commentid );

		$data = array(
			'comment_ID'      => $commentid,
			'comment_content' => wp_slash( $comment_content ),
		);

		$data = apply_filters( $this->wall->prefix . 'update_comment_args', $data, $old_data ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.

		$result = wp_update_comment( $data );
		if ( 1 === $result ) {
			// Apply hashtags for the post
			$this->wall->common()->posts()->hashtagit( $post_id, $orig_content, true );

			$linkified = $this->wall->common()->posts()->linkify_hashtags_in_content( $comment_content );
			$linkified = $this->wall->common()->posts()->maybe_linkify_mentions( $linkified, 'comment', $commentid );
			if ( $comment_content !== $linkified ) {
				$data = array(
					'comment_ID'      => $commentid,
					'comment_content' => wp_slash( $linkified ),
				);
				wp_update_comment( $data );
			}

			$comment_content = nl2br( $linkified );

			if ( ! empty( $old_data->comment_parent ) ) {
				do_action( $this->wall->prefix . 'after_wall_comment_reply_updated', $commentid, $old_data ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.
			} else {
				do_action( $this->wall->prefix . 'after_wall_comment_updated', $commentid, $old_data ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.
			}

			update_comment_meta( $commentid, 'orig_content', wp_slash( $orig_content ) );
			update_comment_meta( $commentid, '_um_comment_version', $this->wall->plugin_version );

			// phpcs:enable WordPress.Security.NonceVerification
			wp_send_json_success(
				array(
					'content' => UM()->ajax()->esc_html_spaces( $comment_content ),
				)
			);
		} else {
			$error_text = __( 'You can\'t edit this comment. `wp_update_comment()` issue.', $this->wall->textdomain ); // phpcs:ignore WordPress.WP.I18n
			if ( is_wp_error( $result ) ) {
				$error_text = $result->get_error_message();
			}
			wp_send_json_error(
				wp_kses(
					UM()->frontend()::layouts()::alert(
						$error_text,
						array(
							'type'      => 'error',
							'underline' => false,
						)
					),
					UM()->get_allowed_html( 'templates' )
				)
			);
		}
	}

	/**
	 * Load wall comments via AJAX
	 */
	public function load_more_comments() {
		if ( empty( $_POST['post_id'] ) ) {
			wp_send_json_error( __( 'Wrong post ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$post_id = absint( $_POST['post_id'] );

		check_ajax_referer( 'um_wall_comments_loadmore' . $post_id, 'nonce' );

		if ( ! $this->wall->common()->posts()->exists( $post_id ) ) {
			wp_send_json_error( __( 'Wrong post ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( UM()->is_rate_limited( 'wall_load_more_comments' ) ) {
			wp_send_json_error( __( 'Too many requests', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! $this->wall->common()->user()->can_view_post( $post_id ) ) {
			wp_send_json_error( __( 'You are not authorized to load comments.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$number    = $this->wall->common()->comments()->get_comments_per_page();
		$order     = $this->wall->common()->comments()->get_comments_order();
		$offset    = ! empty( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$post_link = $this->wall->common()->posts()->get_permalink( $post_id );
		// phpcs:enable WordPress.Security.NonceVerification

		$comments     = $this->wall->common()->comments()->get_comments( $post_id, absint( $number ), $order, $offset );
		$comments_all = $this->wall->common()->comments()->get_comments_number( $post_id );

		$t_args = array(
			'comments'         => $comments,
			'post_id'          => $post_id,
			'post_link'        => $post_link,
			'um_activity_wall' => $this->wall,
			'comm_num'         => $number,
			'order_comment'    => $order,
		);

		$t_args   = apply_filters( $this->wall->prefix . 'wall_comment_template_args', $t_args, $comments, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.
		$template = apply_filters( $this->wall->prefix . 'wall_comment_template', 'comment.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.

		$content = UM()->get_template( $template, $this->wall->plugin_basename, $t_args, false );
		if ( $comments_all > ( absint( $offset ) + absint( $number ) ) ) {
			$loadmore = true;
		} else {
			$loadmore = false;
		}

		wp_send_json_success(
			array(
				'content'  => UM()->ajax()->esc_html_spaces( $content ),
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
		if ( empty( $_POST['comment_id'] ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$comment_id = absint( $_POST['comment_id'] );

		check_ajax_referer( 'um_wall_replies_loadmore' . $comment_id, 'nonce' );

		if ( UM()->is_rate_limited( 'wall_load_more_replies' ) ) {
			wp_send_json_error( __( 'Too many requests', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! $this->wall->common()->comments()->exists( $comment_id ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! $this->wall->common()->user()->can_view_comment( $comment_id ) ) {
			wp_send_json_error( __( 'You are not authorized to load comments.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$comment = get_comment( $comment_id );
		$post_id = $comment->comment_post_ID;
		if ( ! $this->wall->common()->posts()->exists( $post_id ) ) {
			wp_send_json_error( __( 'Wrong post ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$number    = $this->wall->common()->comments()->get_comments_per_page();
		$order     = $this->wall->common()->comments()->get_comments_order();
		$offset    = ! empty( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$post_link = $this->wall->common()->posts()->get_permalink( $post_id );

		$child     = $this->wall->common()->comments()->get_replies( $post_id, $comment_id, absint( $number ), $order, $offset );
		$child_all = $this->wall->common()->comments()->get_replies_number( $post_id, $comment_id );

		$content = '';
		foreach ( $child as $commentc ) {
			um_fetch_user( $commentc->user_id );

			$t_args = array(
				'commentc'         => $commentc,
				'post_id'          => $post_id,
				'post_link'        => $post_link,
				'um_activity_wall' => $this->wall,
			);

			$t_args   = apply_filters( $this->wall->prefix . 'wall_comment_reply_template_args', $t_args, $commentc, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.
			$template = apply_filters( $this->wall->prefix . 'wall_comment_reply_template', 'comment-reply.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.
			$content .= UM()->get_template( $template, $this->wall->plugin_basename, $t_args, false );
		}

		if ( absint( $child_all ) > ( absint( $offset ) + absint( $number ) ) ) {
			$loadmore = true;
		} else {
			$loadmore = false;
		}

		wp_send_json_success(
			array(
				'content'  => UM()->ajax()->esc_html_spaces( $content ),
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
		if ( empty( $_POST['comment_id'] ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$comment_id = absint( $_POST['comment_id'] );

		check_ajax_referer( 'um_wall_delete_comment' . $comment_id, 'nonce' );

		if ( ! $this->wall->common()->comments()->exists( $comment_id ) ) {
			wp_send_json_error( __( 'Wrong comment ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( $this->wall->common()->user()->can_edit_comment( $comment_id, get_current_user_id() ) ) {
			$this->wall->common()->comments()->delete_comment( $comment_id );
			wp_send_json_success();
		}

		// Post authors can delete spam and malicious comments under their posts.
		$comment   = get_comment( $comment_id );
		$author_id = $this->wall->common()->posts()->get_author( $comment->comment_post_ID );
		if ( get_current_user_id() === $author_id ) {
			$this->wall->common()->comments()->delete_comment( $comment_id );
			wp_send_json_success();
		}

		wp_send_json_error( __( 'Something went wrong.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
	}

	/**
	 * Prepare comment content before saving
	 *
	 * @param string $comment_content Comment content
	 *
	 * @return string converted content
	 */
	public function prepare_comment_content( $comment_content ) {
		$comment_content = $this->linkify_content( $comment_content );
		$comment_content = UM()->shortcodes()->emotize( $comment_content, false ); // UM legacy emoji convert from the predefined list of emoji.

		return $comment_content;
	}

	/**
	 * Linkifying the string.
	 *
	 * @param string $raw_text
	 *
	 * @return string
	 */
	public function linkify_content( $raw_text ) {
		$attributes = array(
			'target' => '_blank',
			'class'  => 'um-link',
			'rel'    => 'noopener nofollow ugc',
		);
		$attributes = apply_filters( $this->wall->prefix . 'make_links_clickable_attrs', $attributes, 'comments' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- properly prefixed after installation and library init.

		$attribute_string = '';
		foreach ( $attributes as $key => $value ) {
			$attribute_string .= esc_html( $key ) . '="' . esc_attr( $value ) . '" ';
		}

		/**
		 * The pattern #(?<!href=")(https?://[^\s]+)# uses a negative lookbehind (?<!href=")
		 * to assert that what immediately precedes the URL is not the string 'href="'.
		 * This will prevent URLs that are within href attributes from matching.
		 */
		$linked_text = preg_replace_callback(
			'#(?<!href=")(?<!src=")(https?://[^\s]+)#',
			function ( $m ) use ( $attribute_string ) {
				return '<a ' . $attribute_string . ' href="' . esc_url( $m[0] ) . '">' . esc_html( $m[0] ) . '</a>';
			},
			$raw_text
		);

		return $linked_text;
	}
}
