<?php
namespace WallLib\common;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class User
 *
 * @package WallLib\common
 */
class User {

	/**
	 * @var int[]
	 */
	public $blocked_users = array();

	/**
	 * User constructor.
	 */
	public function __construct() {
	}

	public function hooks() {
	}

	/**
	 * Check if user can view post
	 *
	 * @param int|null $post_id
	 * @param int|null $user_id
	 *
	 * @return bool
	 */
	public function can_view( $post_id = null, $user_id = null ) {
		// return `false` if doesn't exist.
		if ( ! UM()->Activity_API()->common()->post()->exists( $post_id ) ) {
			return false;
		}

		if ( ! $user_id && is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		$profile_id = get_post_field( 'post_author', $post_id );
		if ( ! $profile_id ) {
			return false;
		}

		if ( absint( $profile_id ) === absint( $user_id ) ) {
			return true;
		}

		/**
		 * Filters the user's ability to see the activity post.
		 *
		 * @since 2.1.8
		 * @hook  um_activity_custom_privacy
		 *
		 * @param {bool}   $can_view   Can view capability.
		 * @param {int}    $user_id    User ID (viewer).
		 * @param {int}    $profile_id Album author ID.
		 *
		 * @return {bool} privacy.
		 *
		 * @example <caption>User can view a post.</caption>
		 * function my_um_activity_custom_privacy( $can_view, $user_id, $profile_id ) {
		 *     $can_view = true;
		 *     return $can_view;
		 * }
		 * add_filter( 'um_activity_custom_privacy', 'my_um_activity_custom_privacy', 10, 3 );
		 */
		return apply_filters( 'um_activity_custom_privacy', true, $user_id, $profile_id );
	}

	/**
	 * @param int $post_id
	 *
	 * @return bool
	 */
	public function can_view_post( $post_id ) {
		// return `false` if doesn't exist.
		if ( ! UM()->Activity_API()->common()->post()->exists( $post_id ) ) {
			return false;
		}

		$can_view = true;

		$activity_post = get_post( $post_id );
		if ( empty( $activity_post ) || 'publish' !== $activity_post->post_status ) {
			$can_view = false;
		}

		$wall_id       = UM()->Activity_API()->common()->post()->get_wall( $post_id );
		$can_view_wall = $this->can_view_wall( $wall_id );
		if ( true !== $can_view_wall ) {
			$can_view = false;
		}

		$in_users     = array();
		$followed_ids = UM()->Activity_API()->common()->followers()->followed_ids();
		if ( $followed_ids ) {
			$in_users = array_merge( $in_users, $followed_ids );
		}
		$friends_ids = UM()->Activity_API()->common()->friends()->friends_ids();
		if ( $friends_ids ) {
			$in_users = array_merge( $in_users, $friends_ids );
		}

		if ( ! empty( $in_users ) ) {
			$in_users[] = get_current_user_id();
			$in_users   = array_unique( $in_users );
			$in_users   = array_map( 'absint', $in_users );
			$author_id  = UM()->Activity_API()->common()->post()->get_author( $post_id );
			if ( ! in_array( $author_id, $in_users, true ) ) {
				$can_view = false;
			}
		}

		return apply_filters( 'um_activity_post_can_view', $can_view, $post_id );
	}

	public function can_like( $post_id, $user_id = null ) {
		// return `false` if doesn't exist.
		if ( ! UM()->Activity_API()->common()->post()->exists( $post_id ) ) {
			return false;
		}

		$can_like = true;

		if ( ! $user_id && is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		if ( true !== $this->can_view_post( $post_id ) ) {
			$can_like = false;
		}

		$likes = get_post_meta( $post_id, '_liked', true );
		if ( empty( $likes ) ) {
			$likes = array();
		}
		if ( in_array( $user_id, $likes, true ) ) {
			$can_like = false;
		} else {
			$can_like = $this->can_view_post( $post_id );
		}

		return apply_filters( 'um_activity_can_like_post', $can_like, $post_id, $user_id );
	}

	public function can_unlike( $post_id, $user_id = null ) {
		// return `false` if doesn't exist.
		if ( ! UM()->Activity_API()->common()->post()->exists( $post_id ) ) {
			return false;
		}

		$can_unlike = true;

		if ( ! $user_id && is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		if ( true !== $this->can_view_post( $post_id ) ) {
			$can_unlike = false;
		}

		$likes = get_post_meta( $post_id, '_liked', true );
		if ( empty( $likes ) ) {
			$likes = array();
		}
		if ( ! in_array( $user_id, $likes, true ) ) {
			$can_unlike = false;
		} else {
			$can_unlike = $this->can_view_post( $post_id );
		}

		return apply_filters( 'um_activity_can_unlike_post', $can_unlike, $post_id, $user_id );
	}

	/**
	 * Check if user can view post likes
	 *
	 * @param int|null $post_id
	 * @param int|null $user_id
	 *
	 * @return bool
	 */
	public function can_view_likes( $post_id = null, $user_id = null ) {
		// return `false` if doesn't exist.
		if ( ! UM()->Activity_API()->common()->post()->exists( $post_id ) ) {
			return false;
		}

		if ( ! $user_id && is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		if ( true !== $this->can_view_post( $post_id ) ) {
			return false;
		}

		$profile_id = get_post_field( 'post_author', $post_id );
		if ( ! $profile_id ) {
			return false;
		}

		if ( absint( $profile_id ) === absint( $user_id ) ) {
			return true;
		}

		return apply_filters( 'um_activity_custom_privacy_view_likes', true, $user_id, $profile_id );
	}

	/**
	 * Check if user can view comment likes
	 *
	 * @param int|null $post_id
	 * @param int|null $user_id
	 *
	 * @return bool
	 */
	public function can_view_comment_likes( $comment_id = null, $user_id = null ) {
		// return `false` if doesn't exist.
		if ( ! UM()->Activity_API()->common()->comments()->exists( $comment_id ) ) {
			return false;
		}

		if ( ! $user_id && is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		$post_id = get_comment( $comment_id )->comment_post_ID;
		if ( true !== $this->can_view_post( $post_id ) ) {
			return false;
		}

		$comment = get_comment( $comment_id );
		if ( $comment ) {
			$profile_id = $comment->user_id;
		}
		if ( ! $profile_id ) {
			return false;
		}

		if ( absint( $profile_id ) === absint( $user_id ) ) {
			return true;
		}

		return apply_filters( 'um_activity_custom_privacy_view_comment_likes', true, $comment_id, $user_id, $profile_id );
	}

	/**
	 * @param int $profile_id
	 *
	 * @return bool|string
	 */
	public function can_view_wall( $profile_id ) {
		$can_view = true;

		if ( ! UM()->options()->get( 'activity_enable_privacy' ) ) {
			return $can_view;
		}

		$privacy = get_user_meta( $profile_id, 'wall_privacy', true );

		if ( ! is_user_logged_in() ) {
			if ( UM()->options()->get( 'activity_require_login' ) ) {
				$can_view = __( 'You must login to view this user activity', 'um-activity' );
			} elseif ( 1 === absint( $privacy ) ) {
				$can_view = __( 'Please login to view this user\'s activity', 'um-activity' );
			} elseif ( 2 === absint( $privacy ) ) {
				$can_view = __( 'This user wall is private', 'um-activity' );
			}
		} else {
			if ( absint( $profile_id ) !== get_current_user_id() && 2 === absint( $privacy ) ) {
				$can_view = __( 'This user wall is private', 'um-activity' );
			}
		}

		return apply_filters( 'um_wall_can_view', $can_view, $profile_id );
	}

	public function can_like_comment( $comment_id, $user_id = null ) {
		// return `false` if doesn't exist.
		if ( ! UM()->Activity_API()->common()->comments()->exists( $comment_id ) ) {
			return false;
		}

		if ( ! $user_id && is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		$post_id = get_comment( $comment_id )->comment_post_ID;
		if ( true !== $this->can_view_post( $post_id ) ) {
			$can_like = false;
		}

		$likes = get_comment_meta( $comment_id, '_liked', true );
		if ( empty( $likes ) ) {
			$likes = array();
		}
		if ( in_array( $user_id, $likes, true ) ) {
			$can_like = false;
		} else {
			$can_like = $this->can_view_comment( $comment_id );
		}

		return apply_filters( 'um_activity_can_like_comment', $can_like, $comment_id, $user_id );
	}

	public function can_unlike_comment( $comment_id, $user_id = null ) {
		// return `false` if doesn't exist.
		if ( ! UM()->Activity_API()->common()->comments()->exists( $comment_id ) ) {
			return false;
		}

		$post_id = get_comment( $comment_id )->comment_post_ID;
		if ( true !== $this->can_view_post( $post_id ) ) {
			$can_unlike = false;
		}

		if ( ! $user_id && is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		$likes = get_comment_meta( $comment_id, '_liked', true );
		if ( empty( $likes ) ) {
			$likes = array();
		}
		if ( ! in_array( $user_id, $likes, true ) ) {
			$can_unlike = false;
		} else {
			$can_unlike = $this->can_view_comment( $comment_id );
		}

		return apply_filters( 'um_activity_can_unlike_comment', $can_unlike, $comment_id, $user_id );
	}

	/**
	 * @param int $post_id
	 *
	 * @return bool
	 */
	public function can_view_comment( $comment_id ) {
		// return `false` if doesn't exist.
		if ( ! UM()->Activity_API()->common()->comments()->exists( $comment_id ) ) {
			return false;
		}

		$can_view = true;

		$post_id = get_comment( $comment_id )->comment_post_ID;
		if ( true !== $this->can_view_post( $post_id ) ) {
			$can_view = false;
		}

		return apply_filters( 'um_activity_comment_can_view', $can_view, $comment_id );
	}

	/**
	 * Can edit a user comment.
	 *
	 * @param int $comment_id
	 * @param int $user_id
	 *
	 * @return bool
	 */
	public function can_edit_comment( $comment_id, $user_id ) {
		if ( ! $user_id ) {
			return false;
		}
		$comment = get_comment( $comment_id );

		return absint( $comment->user_id ) === absint( $user_id );
	}

	/**
	 * Can comment current user on wall
	 *
	 * @return bool
	 */
	public function can_comment() {
		$res = true;

		if ( UM()->roles()->um_user_can( 'activity_comments_off' ) ) {
			$res = false;
		}

		if ( ! is_user_logged_in() ) {
			$res = false;
		}

		return apply_filters( 'um_activity_can_post_comment_on_wall', $res );
	}

	/**
	 * Can post on that wall.
	 *
	 * @return mixed|null
	 */
	public function can_write() {
		$res = 1;

		if ( um_user( 'activity_wall_off' ) ) {
			$res = 0;
		}

		if ( UM()->roles()->um_user_can( 'activity_posts_off' ) ) {
			$res = 0;
		}

		if ( ! is_user_logged_in() ) {
			$res = 0;
		}

		return apply_filters( 'um_activity_can_post_on_wall', $res );
	}
}
