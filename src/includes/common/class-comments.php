<?php
namespace WallLib\common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Post
 *
 * @package um_ext\um_social_activity\common
 */
class Comments {

	private $wall;

	/**
	 * Post constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;
	}

	/**
	 * Remove comment with all replies
	 *
	 * @param $comment_id
	 */
//	public function delete_comment( $comment_id ) {
//		global $wpdb;
//
//		$comment = get_comment( $comment_id );
//
//		// Remove comment replies
//		if ( 0 === absint( $comment->comment_parent ) ) {
//			$replies = get_comments(
//				array(
//					'post_id' => $comment->comment_post_ID,
//					'parent'  => $comment_id,
//					'number'  => 10000,
//					'offset'  => 0,
//					'fields'  => 'ids',
//				)
//			);
//
//			if ( ! empty( $replies ) && ! is_wp_error( $replies ) ) {
//				foreach ( $replies as $reply_id ) {
//					$this->delete_comment( $reply_id );
//				}
//			}
//		}
//
//		// remove comment
//		wp_delete_comment( $comment_id, true );
//
//		// Remove hashtag(s) from the trending list if it's totally remove from posts / comments.
//		$content = $comment->comment_content;
//		$post_id = $comment->comment_post_ID;
//		preg_match_all( '/(?<!\&)#([^\s\<]+)/', $content, $matches );
//		if ( isset( $matches[1] ) && is_array( $matches[1] ) ) {
//			foreach ( $matches[1] as $hashtag ) {
//				$post_count    = $wpdb->get_var(
//					$wpdb->prepare(
//						"SELECT COUNT(*)
//						FROM {$wpdb->posts}
//						WHERE ID = %d AND
//							  post_content LIKE %s",
//						$post_id,
//						"%>#{$hashtag}<%"
//					)
//				);
//				$comment_count = $wpdb->get_var(
//					$wpdb->prepare(
//						"SELECT COUNT(*)
//						FROM {$wpdb->comments}
//						WHERE comment_post_ID = %d AND
//							  comment_content LIKE %s",
//						$post_id,
//						"%>#{$hashtag}<%"
//					)
//				);
//
//				if ( empty( $post_count ) && empty( $comment_count ) ) {
//					$term = get_term_by( 'name', $hashtag, 'um_hashtag' );
//					wp_remove_object_terms( $post_id, $term->term_id, 'um_hashtag' );
//				}
//			}
//		}
//	}
//
//	/**
//	 * Unhide a comment for user
//	 *
//	 * @param $comment_id
//	 */
//	public function user_unhide_comment( $comment_id ) {
//		$users = get_comment_meta( $comment_id, '_hidden_from', true );
//
//		$user_id = get_current_user_id();
//
//		if ( isset( $users[ $user_id ] ) ) {
//			unset( $users[ $user_id ] );
//		}
//
//		if ( ! $users ) {
//			delete_comment_meta( $comment_id, '_hidden_from' );
//		} else {
//			update_comment_meta( $comment_id, '_hidden_from', $users );
//		}
//	}
//
//
//	/**
//	 * Checks if user hidden comment
//	 *
//	 * @param $comment_id
//	 *
//	 * @return int
//	 */
//	public function user_hidden_comment( $comment_id ) {
//		$users   = get_comment_meta( $comment_id, '_hidden_from', true );
//		$user_id = get_current_user_id();
//
//		if ( $users && is_array( $users ) && isset( $users[ $user_id ] ) ) {
//			return 1;
//		}
//
//		return 0;
//	}
//
//
//	/***
//	 ***    @Checks if user liked specific wall comment
//	 ***/
//	public function user_liked_comment( $comment_id ) {
//		$res   = '';
//		$users = get_comment_meta( $comment_id, '_liked', true );
//		if ( $users && is_array( $users ) && in_array( get_current_user_id(), $users, true ) ) {
//			return true;
//		}
//
//		return false;
//	}
//
//	/**
//	 * Get comment time.
//	 *
//	 * @param string $time
//	 *
//	 * @return string
//	 */
//	public function get_comment_time( $time ) {
//		return UM()->datetime()->time_diff( strtotime( $time ) );
//	}
//
//	/**
//	 * Get comment link
//	 *
//	 * @param string $post_link
//	 * @param int $comment_id
//	 *
//	 * @return string
//	 */
//	public function get_comment_link( $post_link, $comment_id ) {
//		$link = add_query_arg( 'wall_comment_id', $comment_id, $post_link );
//		return $link;
//	}
//
//	/**
//	 * Get comment count
//	 *
//	 * @param int $post_id
//	 *
//	 * @return int
//	 */
//	public function get_comments_number( $post_id ) {
//		$comments_all = get_comments(
//			array(
//				'post_id' => $post_id,
//				'parent'  => 0,
//				'number'  => 10000,
//				'offset'  => 0,
//			)
//		);
//		return count( $comments_all );
//	}
//
//	/**
//	 * Get replies count
//	 *
//	 * @param int $post_id
//	 * @param int $comment_id
//	 *
//	 * @return int
//	 */
//	public function get_replies_number( $post_id, $comment_id ) {
//		$replies_all = get_comments(
//			array(
//				'post_id' => $post_id,
//				'parent'  => $comment_id,
//				'number'  => 10000,
//				'offset'  => 0,
//			)
//		);
//		return count( $replies_all );
//	}
//
//	/**
//	 * Hide a comment for user
//	 *
//	 * @param $comment_id
//	 */
//	public function user_hide_comment( $comment_id ) {
//		$user_id = get_current_user_id();
//
//		//hide comment replies
//		$comment_data = get_comment( $comment_id );
//		if ( 0 === absint( $comment_data->comment_parent ) ) {
//			$replies = get_comments(
//				array(
//					'post_id' => $comment_data->comment_post_ID,
//					'parent'  => $comment_id,
//					'number'  => 10000,
//					'offset'  => 0,
//					'fields'  => 'ids',
//				)
//			);
//
//			if ( ! empty( $replies ) && ! is_wp_error( $replies ) ) {
//				foreach ( $replies as $reply_id ) {
//					$this->user_hide_comment( $reply_id );
//				}
//			}
//		}
//
//		$users = get_comment_meta( $comment_id, '_hidden_from', true );
//
//		if ( empty( $users ) || ! is_array( $users ) ) {
//			$users = array();
//		}
//
//		$users[ $user_id ] = current_time( 'timestamp' );
//
//		update_comment_meta( $comment_id, '_hidden_from', $users );
//	}
//
//	/***
//	 ***    @get comment content
//	 ***/
//	public function commentcontent( $content ) {
//		$content = convert_smilies( $content );
//		//$content = preg_replace('$(\s|^)(https?://[a-z0-9_./?=&-]+)(?![^<>]*>)$i', ' <a class="um-link" href="$2" target="_blank" rel="nofollow">$2</a> ', $content." ");
//		//$content = preg_replace('$(\s|^)(www\.[a-z0-9_./?=&-]+)(?![^<>]*>)$i', '<a class="um-link" target="_blank" href="http://$2"  target="_blank" rel="nofollow">$2</a> ', $content." ");
//		$content = UM()->Activity_API()->common()->post()->make_links_clickable( $content );
//		$content = UM()->Activity_API()->common()->post()->hashtag_links( $content );
//
//		return $content;
//	}

	/**
	 *
	 * @param int|WP_Comment $comment Post ID or Post WP_Comment object.
	 *
	 * @return bool
	 */
	public function exists( $comment ) {
		$status = wp_get_comment_status( $comment );
		return false !== $status;
	}
}
