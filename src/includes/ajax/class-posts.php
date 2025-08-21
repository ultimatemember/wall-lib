<?php
//namespace WallLib\ajax;
namespace Dev\UM_Activity\WallLib\ajax;

use WP_Filesystem_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Post
 *
 * @package WallLib\ajax
 */
class Posts {

	private $wall;

	/**
	 * Post constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;

		add_action( 'wp_ajax_um_wall_load_posts', array( $this, 'ajax_load_wall' ) );
		add_action( 'wp_ajax_nopriv_um_wall_load_posts', array( $this, 'ajax_load_wall' ) );

		add_action( 'wp_ajax_um_wall_publish', array( $this, 'wall_publish' ) );

		add_action( 'wp_ajax_um_wall_like_post', array( $this, 'like_post' ) );
		add_action( 'wp_ajax_um_wall_unlike_post', array( $this, 'unlike_post' ) );

		add_action( 'wp_ajax_um_wall_get_post_likes', array( $this, 'get_post_likes' ) );
		add_action( 'wp_ajax_nopriv_um_wall_get_post_likes', array( $this, 'get_post_likes' ) );

		add_action( 'wp_ajax_um_wall_remove_post', array( $this, 'remove_post' ) );
	}

	/**
	 * Load wall posts
	 */
	public function ajax_load_wall() {
		check_ajax_referer( 'um_activity_wall', 'nonce' );

		$user_id = empty( $_POST['user_id'] ) ? 0 : absint( $_POST['user_id'] );

		$can_view = $this->wall->common()->user()->can_view_wall( $user_id );

		if ( true !== $can_view ) {
			wp_send_json_error( array( 'message' => __( 'You can\'t view wall', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$hashtag_id = '';
		if ( ! empty( $_POST['hashtag'] ) ) {
			$hashtag    = str_replace( '#', '', sanitize_text_field( $_POST['hashtag'] ) );
			$term       = get_term_by( 'name', $hashtag, 'um_hashtag' );
			$hashtag_id = isset( $term->term_id ) ? $term->term_id : '';
		}

		$data = array(
			'hashtag_id' => $hashtag_id,
			'user_wall'  => false,
			'offset'     => empty( $_POST['offset'] ) ? 0 : absint( $_POST['offset'] ),
		);

		if ( ! empty( $_POST['user_wall'] ) ) {
			$data['user_wall'] = true;
			$data['user_id']   = empty( $_POST['user_id'] ) ? 0 : absint( $_POST['user_id'] );
		}
		if ( isset( $_POST['post_id'] ) && ! empty( $_POST['post_id'] ) && is_numeric( $_POST['post_id'] ) ) {
			$data['post_id'] = absint( $_POST['post_id'] );
		}
		if ( isset( $_POST['core_page'] ) && ! empty( $_POST['core_page'] ) ) {
			$data['core_page'] = sanitize_key( $_POST['core_page'] );
		}
		if ( isset( $_POST['show_pending'] ) && ! empty( $_POST['show_pending'] ) ) {
			$data['show_pending'] = sanitize_key( $_POST['show_pending'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification

		$args = array(
			'fields'      => 'ids',
			'post_type'   => $this->wall->post_type,
			'post_status' => 'publish',
			'meta_query'  => array(),
		);

		$args = apply_filters( $this->wall->prefix . 'wall_posts_args', $args, $data );

		$query = new \WP_Query( $args );

		$t_args = array(
			'wall_posts' => $query->get_posts(),
		);

		$t_args = apply_filters( $this->wall->prefix . 'wall_template_args', $t_args, $args, $query );

//		add_filter( 'safe_style_css', array( &$this, 'add_extra_safe_style_css' ) );
		$output = UM()->get_template( 'v3/posts-loop.php', $this->wall->plugin_basename, $t_args );
//		remove_filter( 'safe_style_css', array( &$this, 'add_extra_safe_style_css' ) );

		wp_send_json_success( $output );
	}

	/**
	 * Add a new wall post via AJAX
	 */
	public function wall_publish() {
		check_ajax_referer( 'um-wall-post-publish', 'nonce' );

		// When '_post_id' === 0 then insert, else edit.
		if ( ! isset( $_POST['_post_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please specify the post ID. It\'s required.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$post_id = absint( $_POST['_post_id'] );

		$_post_content = '';
		if ( ! empty( $_POST['_post_content'] ) ) {
			$_post_content = wp_kses_post( trim( wp_unslash( $_POST['_post_content'] ) ) );
		}

		$_post_images = array();
		if ( ! empty( $_POST['activity_post_photo'] ) ) {
			foreach ( $_POST['activity_post_photo'] as $post_photo ) {
				if ( ! array_key_exists( 'hash', $post_photo ) ) {
					continue;
				}
				if ( ! UM()->common()->filesystem()->is_file_author( $post_photo['hash'] ) ) {
					continue;
				}

				$_post_images[] = $post_photo;
			}
		}

		if ( empty( $_post_content ) && empty( $_post_images ) ) {
			wp_send_json_error( __( 'You should type something first.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$wall_id = 0;
		if ( ! empty( $_POST['_wall_id'] ) ) {
			$wall_id = absint( $_POST['_wall_id'] );
		}

		um_maybe_unset_time_limit();

		if ( 0 === $post_id ) {
			$post_id     = $this->handle_post_insert( $_post_content, $_post_images, $wall_id );
			$wall_exists = ! empty( $_POST['wall_exists'] );
			if ( ! $wall_exists ) {
				// When there is only posting form on the page then we don't need return post data. Just a result success or not and post URL.
				// translators: %s - activity post URL

				$permalink = apply_filters( $this->wall->prefix . 'wall_publish_permalink', '', $post_id );
				$output    = wp_kses_post( sprintf( __( 'Post is submitted successfully. To view post <a href="%s" class="um-link">click here</a>.', 'um-activity' ), $permalink ) );
			} else {
				$output = $this->prepare_response( $post_id );
			}
		} else {
			$post_id = $this->handle_post_update( $_post_content, $_post_images, $wall_id );
			$output  = $this->prepare_response( $post_id );
		}

		/**
		 * Filter change AJAX post content.
		 *
		 * @since 2.3.6
		 *
		 * @hook um_wall_ajax_publish_output
		 *
		 * @param {string}  $output   output content.
		 *
		 * @example <caption>Change post content on AJAX.</caption>
		 * function my_um_wall_ajax_publish_output( $url, $content ) {
		 *     // your code here
		 *    $output['post_content'] = 'post content';
		 *    return $output;
		 * }
		 * add_filter( 'um_wall_ajax_publish_output', 'my_um_wall_ajax_publish_output' );
		 */
		$output = apply_filters( 'um_wall_ajax_publish_output', $output );

		if ( ! empty( $output ) ) {
			wp_send_json_success( $output );
		}

		wp_send_json_error( __( 'Something went wrong.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
	}

	private function prepare_response( $post_id ) {
		$t_args = array(
			'allowed_html' => UM()->Activity_API()->common()->post()->get_allowed_html(),
			'wall_post'    => $post_id,
			'wall_posts'   => array( $post_id ),
			'single_post'  => true,
		);
		return UM()->ajax()->esc_html_spaces( UM()->get_template( 'v3/posts-loop.php', um_activity_plugin, $t_args ) );
	}

	private function handle_post_insert( $_post_content, $_post_images, $wall_id ) {
		$args = array(
			'post_title'   => '',
			'post_type'    => $this->wall->post_type,
			'post_status'  => 'publish',
			'post_author'  => get_current_user_id(),
			'post_content' => '',
			'meta_input'   => array(
				'_wall_id'          => $wall_id,
				'_user_id'          => get_current_user_id(),
				'_likes'            => 0,
				'_comments'         => 0,
				'_oembed'           => false,
				'_action'           => 'status',
				'_original_content' => '',
				'_shared_link'      => '',
			),
		);

		global $wp_filesystem;
		if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';

			$credentials = request_filesystem_credentials( site_url() );
			WP_Filesystem( $credentials );
		}

		if ( trim( $_post_content ) ) {
			$orig_content = wp_kses(
				trim( $_post_content ),
				array(
					'br' => array(),
				)
			);

			$safe_content = apply_filters( 'um_wall_new_post', $orig_content, 0 );

			// shared a link
			$shared_link = $this->wall->common()->posts()->get_content_link( $safe_content );
			$has_oembed  = $this->wall->common()->posts()->is_oembed( $shared_link );

			if ( isset( $shared_link ) && $shared_link && empty( $_post_images ) && ! $has_oembed ) {
				$safe_content = str_replace( $shared_link, '', $safe_content );
			}

			$args['post_content'] = $safe_content;
		}

		$args = apply_filters( 'um_wall_insert_post_args', $args );

		$post_id = wp_insert_post( $args );

		// shared a link
		if ( isset( $shared_link ) && $shared_link && empty( $_post_images ) && ! $has_oembed ) {
			$this->wall->common()->posts()->set_url_meta( $shared_link, $post_id );
		} else {
			delete_post_meta( $post_id, '_shared_link' );
		}

		$args['post_content'] = apply_filters( 'um_wall_insert_post_content_filter', $args['post_content'], get_current_user_id(), $post_id, 'new' );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => $post_id,
				'post_name'    => $post_id,
				'post_content' => $args['post_content'],
			)
		);

		if ( isset( $safe_content ) ) {
			$this->wall->common()->posts()->hashtagit( $post_id, $safe_content );
			$this->wall->common()->posts()->setup_video( $orig_content, $post_id );
			update_post_meta( $post_id, '_original_content', $orig_content );
		}

		if ( ! empty( $_post_images ) ) {
			$allowed = UM()->common()->filesystem()::image_mimes( 'allowed' );
			$allowed = apply_filters( $this->wall->prefix . 'wall_allowed_mime_types', $allowed );
			foreach ( $_post_images as $photo ) {
				$path       = sanitize_file_name( $photo['path'] );
				$filename   = sanitize_file_name( 'stream_photo_' . $photo['hash'] . '_' . $photo['filename'] );// Make the file name unique in the (new) upload directory.
				$image_type = wp_check_filetype( $path, $allowed ); // Don't need checking empty condition below, because had validation above.

				$old_path = wp_normalize_path( UM()->common()->filesystem()->get_file_by_hash( $photo['hash'] ) );
				$new_path = wp_normalize_path( UM()->common()->filesystem()->get_user_uploads_dir( get_current_user_id() ) . DIRECTORY_SEPARATOR . $filename );

				$move_result = $wp_filesystem->move( $old_path, $new_path, true );
				if ( ! $move_result ) {
					continue;
				}

				$attachment = array(
					'guid'           => $new_path,
					'post_mime_type' => $image_type['type'],
					'post_title'     => sanitize_text_field( $filename ),
					'post_content'   => '',
					'post_parent'    => $post_id,
					'post_author'    => get_current_user_id(),
					'post_status'    => 'inherit',
				);

				$attach_id = wp_insert_attachment( $attachment, $new_path );
				add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
				$attach_data = wp_generate_attachment_metadata( $attach_id, $new_path );
				wp_update_attachment_metadata( $attach_id, $attach_data );
				remove_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
			}
		}

//		$output['postid']  = $post_id;
//		$output['content'] = $this->get_content( $post_id );
//		$output['video']   = $this->get_video( $post_id );

		do_action( 'um_wall_after_wall_post_published', $post_id, get_current_user_id(), $wall_id );

		return $post_id;
	}

	private function handle_post_update( $_post_content, $_post_images, $wall_id ) {
		$output['link'] = '';

		if ( trim( $_post_content ) ) {
			$orig_content = wp_kses(
				trim( $_post_content ),
				array(
					'br' => array(),
				)
			);

			$safe_content = apply_filters( 'um_wall_edit_post', $orig_content, 0 );

			// shared a link
			$shared_link = $this->wall->common()->posts()->get_content_link( $safe_content );
			$has_oembed  = $this->wall->common()->posts()->is_oembed( $shared_link );

			if ( isset( $shared_link ) && $shared_link && ! $_post_img && ! $has_oembed ) {
				$safe_content   = str_replace( $shared_link, '', $safe_content );
				$output['link'] = $this->wall->common()->posts()->set_url_meta( $shared_link, $post_id );
			} else {
				delete_post_meta( $post_id, '_shared_link' );
			}

			$safe_content = apply_filters( 'um_wall_update_post_content_filter', $safe_content, $this->wall->common()->posts()->get_author( $post_id ), $post_id, 'save' );

			$args['post_content'] = $safe_content;
		}

		$args['ID'] = $post_id;
		$args       = apply_filters( 'um_wall_update_post_args', $args );

		// Hash tag replies.
		// $args['post_content'] = apply_filters( 'um_wall_insert_post_content_filter', $args['post_content'], get_current_user_id(), $post_id, 'new' );

		wp_update_post( $args );

		if ( isset( $safe_content ) ) {
			$this->wall->common()->posts()->hashtagit( $post_id, $safe_content );
			$this->wall->common()->posts()->setup_video( $orig_content, $post_id );
			update_post_meta( $post_id, '_original_content', $orig_content );
			$output['orig_content'] = stripslashes_deep( $orig_content );
		}

		if ( '' !== $_post_img ) {

			if ( um_is_temp_file( $_post_img ) ) {
				$photo_uri = um_is_file_owner( $_post_img, get_current_user_id() ) ? $_post_img : false;

				UM()->uploader()->replace_upload_dir = true;
				UM()->uploader()->move_temporary_files( get_current_user_id(), array( '_photo' => $photo_uri ), true );
				UM()->uploader()->replace_upload_dir = false;

				update_post_meta( $post_id, '_photo', $photo_uri );
				$filename       = wp_basename( $photo_uri );
				$photo_metadata = get_transient( "um_{$filename}" );
				update_post_meta( $post_id, '_photo_metadata', $photo_metadata );
				delete_transient( "um_{$filename}" );
			} else {
				$filename = wp_basename( $_post_img );
			}

			if ( ! isset( $photo_metadata ) ) {
				$photo_metadata = get_post_meta( $post_id, '_photo_metadata', true );
			}

			$output['photo']           = $this->wall->common()->posts()->get_download_link( $post_id, get_current_user_id() );
			$output['photo_base']      = $photo_metadata['original_name'];
			$output['photo_orig_url']  = UM()->uploader()->get_upload_base_url() . get_current_user_id() . '/' . $filename;
			$output['photo_orig_base'] = wp_basename( $output['photo_orig_url'] );

		} else {

			$photo_uri = get_post_meta( $post_id, '_photo', true );

			UM()->uploader()->replace_upload_dir = true;
			UM()->uploader()->delete_existing_file( $photo_uri );
			UM()->uploader()->replace_upload_dir = false;

			delete_post_meta( $post_id, '_photo' );
			delete_post_meta( $post_id, '_photo_metadata' );

			$filename = wp_basename( $photo_uri );
			delete_transient( "um_{$filename}" );

		}

		$output['postid']  = $post_id;
		$output['content'] = $this->wall->common()->posts()->get_content( $post_id );
		$output['video']   = $this->wall->common()->posts()->get_video( $post_id );

		do_action( 'um_wall_after_wall_post_updated', $post_id, get_current_user_id(), $wall_id );

		return $post_id;
	}

	/**
	 * Like wall post.
	 *
	 */
	public function like_post() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['post_id'] ) || ! $this->wall->common()->posts()->exists( absint( $_POST['post_id'] ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$post_id = absint( $_POST['post_id'] );

		check_ajax_referer( 'um_wall_like_post' . $post_id, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must login to like', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		// phpcs:enable WordPress.Security.NonceVerification
		if ( ! $this->wall->common()->user()->can_like( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to like this post.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$liked = get_post_meta( $post_id, '_liked', true );
		if ( is_array( $liked ) && in_array( get_current_user_id(), $liked, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You already liked this post', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$increase_likes = false;
		$likes          = get_post_meta( $post_id, '_likes', true );
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
			update_post_meta( $post_id, '_liked', $liked );
			$likes ++;
			update_post_meta( $post_id, '_likes', $likes );
		}

		do_action( $this->wall->prefix . 'after_wall_post_liked', $post_id, get_current_user_id() );

		$content = UM()->frontend()::layouts()::avatars_list(
			$liked,
			array(
				'wrapper' => 'span',
				'size'    => 's',
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
	 * Unlike wall post
	 *
	 */
	public function unlike_post() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['post_id'] ) || ! $this->wall->common()->posts()->exists( absint( $_POST['post_id'] ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$post_id = absint( $_POST['post_id'] );

		check_ajax_referer( 'um_wall_unlike_post' . $post_id, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must login to unlike', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! $this->wall->common()->user()->can_unlike( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to unlike this post.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$liked = get_post_meta( $post_id, '_liked', true );
		if ( empty( $liked ) || ! is_array( $liked ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post data', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! in_array( get_current_user_id(), $liked, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You didn\'t like this post', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$likes = get_post_meta( $post_id, '_likes', true );
		$likes = absint( $likes );

		$liked = array_diff( $liked, array( get_current_user_id() ) );
		update_post_meta( $post_id, '_liked', $liked );

		--$likes;
		$likes = 0 < $likes ? $likes : 0;
		update_post_meta( $post_id, '_likes', $likes );

		do_action( $this->wall->prefix . 'after_wall_post_unliked', $post_id, get_current_user_id() );

		$content = UM()->frontend()::layouts()::avatars_list(
			$liked,
			array(
				'wrapper' => 'span',
				'size'    => 's',
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
	 * Load post likes via AJAX
	 */
	public function get_post_likes() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['post_id'] ) || ! $this->wall->common()->posts()->exists( absint( $_POST['post_id'] ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$post_id = absint( $_POST['post_id'] );

		// phpcs:enable WordPress.Security.NonceVerification
		check_ajax_referer( 'um_wall_show_likes' . $post_id, 'nonce' );

		if ( ! $this->wall->common()->user()->can_view_likes( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to see likes.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$likes = get_post_meta( $post_id, '_liked', true );
		if ( empty( $likes ) ) {
			$likes = array();
		}

		$template = apply_filters( $this->wall->prefix . 'wall_likes_template', 'modal/likes.php' );

		$content = UM()->get_template(
			$template,
			$this->wall->plugin_basename,
			array(
				'likes'   => $likes,
				'context' => 'post',
			)
		);

		wp_send_json_success(
			array(
				'content' => UM()->ajax()->esc_html_spaces( $content ),
				'context' => 'post',
			)
		);
	}

	/**
	 * Removes a wall post
	 */
	public function remove_post() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['post_id'] ) || ! $this->wall->common()->posts()->exists( absint( $_POST['post_id'] ) ) ) {
			wp_send_json_error( __( 'Wrong post ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$post_id = absint( $_POST['post_id'] );

		if ( ! wp_verify_nonce( $_POST['nonce'], 'um_wall_delete_post' . $post_id ) ) {
			wp_send_json_error( __( 'Wrong nonce.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! $this->wall->common()->user()->can_remove_post( $post_id ) ) {
			wp_send_json_error( __( 'You are not authorized to remove this post.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		wp_delete_post( $post_id, true );

		wp_send_json_success();
	}


}
