<?php
namespace WallLib\common;
//namespace UM_Activity\WallLib\common;

use DOMDocument;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Post
 *
 * @package WallLib\common
 */
class Posts {

	private $wall;

	/**
	 * Post constructor.
	 */
	public function __construct( $wall ) {
		$this->wall = $wall;
	}

	public function get_allowed_html() {
		// Adds iframe and onclick to allowed tags and attributes only for activity wall.
		add_filter( 'um_late_escaping_allowed_tags', array( &$this, 'add_extra_kses_allowed_tags' ), 10, 2 );
		$allowed_html = UM()->get_allowed_html( 'templates' );
		remove_filter( 'um_late_escaping_allowed_tags', array( &$this, 'add_extra_kses_allowed_tags' ) );

		return $allowed_html;
	}

	public function add_extra_kses_allowed_tags( $allowed_html, $context ) {
		if ( 'templates' === $context ) {
			$allowed_html['iframe'] = array(
				'allow'          => true,
				'frameborder'    => true,
				'loading'        => true,
				'name'           => true,
				'referrerpolicy' => true,
				'sandbox'        => true,
				'src'            => true,
				'srcdoc'         => true,
				'title'          => true,
				'width'          => true,
				'height'         => true,
			);

			$allowed_html['figure'] = array(
				'class' => true,
			);

			$allowed_html['strong']['onclick'] = true;

			$allowed_html['u'] = true;
			$allowed_html['i'] = true;
			$allowed_html['b'] = true;
		}

		return $allowed_html;
	}

	/**
	 * Gets post permalink
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	public function get_permalink( $post_id ) {
		$url = apply_filters( $this->wall->prefix . 'wall_get_core_page', '' );
		return add_query_arg( 'wall_post', $post_id, $url );
	}

	/**
	 *
	 * @param int|WP_Post $post Post ID or Post WP_Post object.
	 *
	 * @return bool
	 */
	public function exists( $post ) {
		$status = get_post_status( $post );
		return false !== $status;
	}

	/**
	 * Gets post wall ID
	 *
	 * @param int $post_id
	 *
	 * @return int
	 */
	public function get_wall( $post_id ) {
		$wall = absint( get_post_meta( $post_id, '_wall_id', true ) );
		return ( $wall ) ? $wall : 0;
	}

	/**
	 * Gets post author
	 *
	 * @param int $post_id
	 *
	 * @return int
	 */
	public function get_author( $post_id ) {
		$author = get_post_meta( $post_id, '_user_id', true );
		if ( empty( $author ) ) {
			$post = get_post( $post_id );
			if ( empty( $post ) ) {
				return 0;
			}
			$author = $post->post_author;
		}
		return ! empty( $author ) ? absint( $author ) : 0;
	}

	/**
	 * Gets activity in nice time format.
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	public function get_post_time( $post_id ) {
		$unix_published_date = get_post_datetime( $post_id, 'date', 'gmt' );
		$time                = UM()->datetime()->time_diff( $unix_published_date->getTimestamp() );

		return apply_filters( $this->wall->prefix . 'human_post_time', $time, $post_id );
	}

	/**
	 * @Checks if post is reported
	 **/
	public function reported( $post_id, $reporter_id = null ) {
		$reported = get_post_meta( $post_id, '_reported', true );
		if ( $reporter_id ) {
			$reported_by = get_post_meta( $post_id, '_reported_by', true );
			if ( isset( $reported_by[ $reporter_id ] ) ) {
				return 1;
			}

			return 0;
		}

		return ( $reported ) ? 1 : 0;
	}

	/**
	 * Get a possible video
	 *
	 * @param int $post_id
	 * @param array $args
	 *
	 * @return false|string
	 */
	public function get_video( $post_id = 0, $args = array() ) {
		$uri = get_post_meta( $post_id, '_video_url', true );
		if ( ! $uri ) {
			return '';
		}

		$content = wp_oembed_get( $uri, $args );
		$content = apply_filters( $this->wall->prefix . 'get_video', $content, $post_id, $args );

		return $content;
	}

	/**
	 * Get a possible photo
	 *
	 * @param int $post_id
	 * @param string $class
	 * @param null|int $author_id
	 *
	 * @return string
	 */
	public function get_photo( $post_id = 0, $class = '', $author_id = null ) {
		$attachments = get_children(
			array(
				'post_parent'    => $post_id,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'numberposts'    => -1,
			)
		);
		$title     = esc_html__( 'Post image', $this->wall->textdomain ); // phpcs:ignore WordPress.WP.I18n

		if ( ! empty( $attachments ) ) {
			$content = '';
			foreach ( $attachments as $attachment ) {
				$url       = wp_get_attachment_url( $attachment->ID );
				$photo_url = $this->get_download_link( $post_id, $author_id, $url, $attachment->ID );

				if ( empty( $photo_url ) ) {
					return '';
				}
				$photo_url = esc_attr( $photo_url );

				if ( 'backend' === $class ) {
					$uri = get_post_meta( $post_id, '_photo', true );
					if ( ! $uri ) {
						return '';
					}
					$uri           = wp_basename( $uri );
					$user_base_dir = UM()->common()->filesystem()->get_user_uploads_dir( $author_id );

					if ( file_exists( $user_base_dir . DIRECTORY_SEPARATOR . $uri ) ) {
						$content .= "<img src=\"{$photo_url}\" title=\"{$title}\" alt=\"\" style=\"width: 100%;\" />";
					}
				} else {
					$content .= "<div class=\"um-image-lazyload-wrapper\"><div class=\"um-skeleton-box\"></div><img class=\"um-image-lazyload\" loading=\"lazy\" src=\"{$photo_url}\" title=\"{$title}\" alt=\"\" /></div>";
				}
			}
		} else {
			$content = $this->get_photo_v2( $post_id, $class, $author_id );
		}

		return apply_filters( $this->wall->prefix . 'get_photo_content', $content, $post_id, $class, $author_id );
	}

	public function get_photo_v2( $post_id = 0, $class = '', $author_id = null ) {
		$photo_url = $this->get_download_link( $post_id, $author_id );
		if ( empty( $photo_url ) ) {
			return '';
		}
		$photo_url = esc_attr( $photo_url );
		$title     = esc_html__( 'Post image', $this->wall->textdomain ); // phpcs:ignore WordPress.WP.I18n

		$content = '';
		if ( 'backend' === $class ) {
			$uri = get_post_meta( $post_id, '_photo', true );
			if ( ! $uri ) {
				return '';
			}
			$uri           = wp_basename( $uri );
			$user_base_dir = UM()->common()->filesystem()->get_user_uploads_dir( $author_id );

			if ( file_exists( $user_base_dir . DIRECTORY_SEPARATOR . $uri ) ) {
				$content = "<img src=\"{$photo_url}\" title=\"{$title}\" alt=\"\" style=\"width: 100%;\" />";
			}
		} else {
			$content = "<img src=\"{$photo_url}\" title=\"{$title}\" alt=\"\" />";
		}

		return $content;
	}

	/**
	 * @param int $post_id
	 * @param int $author_id
	 *
	 * @return string
	 */
	public function get_download_link( $post_id, $author_id, $iamge_url = '', $attachment_id = 0 ) {
		if ( empty( $iamge_url ) ) {
			$uri = get_post_meta( $post_id, '_photo', true );
		} else {
			$uri = $iamge_url;
		}

		if ( ! $uri ) {
			return '';
		}

		$uri      = wp_basename( $uri );
		$userdir  = UM()->common()->filesystem()->get_user_uploads_dir( $author_id );
		$filename = wp_normalize_path( "$userdir/$uri" );

		if ( ! file_exists( $filename ) ) {
			return '';
		}

		$filetype = wp_check_filetype( $filename );
		$filetime = filemtime( $filename );

		$nonce = wp_create_nonce( $author_id . $post_id . 'um-download-nonce' );

		if ( UM()->is_permalinks ) {
			if ( '' !== $iamge_url ) {
				$url = home_url( "/um-wall-download/{$post_id}/{$author_id}/{$nonce}/{$attachment_id}.{$filetype['ext']}" );
			} else {
				$url = home_url( "/um-wall-download/{$post_id}/{$author_id}/{$nonce}/{$filetime}.{$filetype['ext']}" );
			}
		} else {
			if ( '' !== $iamge_url ) {
				$url = add_query_arg(
					array(
						'um_action'        => 'um-wall-download',
						'um_post'          => $post_id,
						'um_author'        => $author_id,
						'um_verify'        => $nonce,
						'um_attachment_id' => $attachment_id,
					),
					home_url()
				);
			} else {
				$url = add_query_arg(
					array(
						'um_action'   => 'um-wall-download',
						'um_post'     => $post_id,
						'um_author'   => $author_id,
						'um_verify'   => $nonce,
						'um_filename' => $filetime . '.' . $filetype['ext'],
					),
					home_url()
				);
			}
		}

		$url = apply_filters( $this->wall->prefix . 'get_download_link', $url, $post_id, $author_id );

		return $url;
	}

	/**
	 * Add hashtags to activity wall post based on post or comment ID.
	 *
	 * @param int    $post_id
	 * @param string $content
	 * @param bool   $append
	 */
	public function hashtagit( $post_id, $content, $append = false ) {
		// hashtag must have space or start line before and space or end line after. Hashtag can contain digits, letters, underscore. Not space or dash "-".
		preg_match_all( '/(^|\s)#([\p{Pc}\p{N}\p{L}\p{Mn}]+)/um', $content, $matches, PREG_SET_ORDER, 0 );

		$terms = array();
		if ( isset( $matches[0] ) && is_array( $matches[0] ) ) {
			foreach ( $matches as $match ) {
				if ( isset( $match[2] ) ) {
					$terms[] = $match[2];
				}
			}
		}

		wp_set_post_terms( $post_id, $terms, 'um_hashtag', $append );
	}

	/**
	 * Get post image URL - thumbnail, first image, first cover
	 *
	 * @param int|array|null|WP_Post $post Optional. Post ID or WP_Post object.
	 *
	 * @return string URL
	 */
	public function get_post_image_url( $post = null ) {
		$image_url = '';

		if ( has_post_thumbnail( $post ) ) {
			$image_urls = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'large' );
			$image_url  = current( $image_urls );
		} else {
			if ( is_numeric( $post ) ) {
				$post = get_post( $post );
			}
			if ( is_a( $post, 'WP_Post' ) ) {
				preg_match( '/[^"]+\.(jpeg|jpg|png)/im', $post->post_content, $matches );
				if ( isset( $matches[0] ) ) {
					$image_url = esc_url_raw( $matches[0] );
				}
			}
		}

		return (string) $image_url;
	}

	/**
	 * @Gets action name
	 **/
	public function get_action( $post_id ) {
		$action = (string) get_post_meta( $post_id, '_action', true );
		$action = ( $action ) ? $action : 'status';

		return isset( $this->global_actions[ $action ] ) ? $this->global_actions[ $action ] : '';
	}

	/**
	 * Gets action type
	 *
	 * @param $post_id
	 *
	 * @return string
	 */
	public function get_action_type( $post_id ) {
		$action = (string) get_post_meta( $post_id, '_action', true );
		$action = ( $action ) ? $action : 'status';

		return $action;
	}

	/**
	 * @Checks if image is valid
	 */
	public function is_image( $url ) {
		$allow_types = array(
			'jpeg' => 'image/jpeg',
		);

		/**
		 * UM hook
		 *
		 * @type filter
		 * @title um_allow_mime
		 * @description Extend mime types for images
		 * @input_vars
		 * [{"var":"$allow_types","type":"array","desc":"Allowed Types"}]
		 * @change_log
		 * ["Since: 2.1.8"]
		 * @usage add_filter( 'um_allow_mime', 'function_name', 10, 1 );
		 * @example
		 * <?php
		 * add_filter( 'um_allow_mime', 'my_um_allow_mime', 10, 1 );
		 * function my_um_allow_mime( $allow_types ) {
		 *     // your code here
		 *     return $allow_types;
		 * }
		 * ?>
		 */
		$allow_types = apply_filters( 'um_allow_mime', $allow_types );

		$filetype = wp_check_filetype( $url );
		if ( ! in_array( $filetype['type'], $allow_types, true ) ) {
			return 0;
		}

		$size = @getimagesize( $url );
		if ( ! is_array( $size ) ) {
			return 0;
		}

		if ( isset( $size['mime'] ) && strstr( $size['mime'], 'image' ) && in_array( $size['mime'], $allow_types, true ) && isset( $size[0] ) && absint( $size[0] ) > 100 && isset( $size[1] ) && ( $size[0] / $size[1] >= 1 ) && ( $size[0] / $size[1] <= 3 ) ) {
			return $size;
		}

		return 0;
	}

	/**
	 * Strip video URLs as we need to convert them.
	 *
	 * @param string $content
	 * @param int    $post_id
	 */
	public function setup_video( $content, $post_id ) {
		$urls = wp_extract_urls( $content );

		if ( ! empty( $urls ) ) {
			foreach ( $urls as $url ) {
				$oembed        = new \WP_oEmbed();
				$provider_data = $oembed->get_data( $url );
				if ( ! empty( $provider_data ) && in_array( $provider_data->provider_name, array( 'YouTube', 'Vimeo' ), true ) ) {
					$videos[]['url']         = trim( $url );
					$videos[]['oembed_data'] = wp_json_encode( $provider_data );
				}
			}
		}

		if ( isset( $videos ) ) {
			$content = str_replace( $videos[0]['url'], '', $content );
			update_post_meta( $post_id, '_video_url', $videos[0]['url'] );
			if ( isset( $videos[0]['oembed_data'] ) ) {
				update_post_meta( $post_id, '_video_oembed_data', $videos[0]['oembed_data'] );
			}
		} else {
			delete_post_meta( $post_id, '_video_url' );
			delete_post_meta( $post_id, '_video_oembed_data' );
		}
	}

	/**
	 * Get likes count
	 *
	 * @param $post_id
	 *
	 * @return int
	 */
	public function get_likes_number( $post_id ) {
		$likes = get_post_meta( $post_id, '_liked', true );
		if ( empty( $likes ) ) {
			return 0;
		}

		foreach ( $likes as $key => $user_id ) {
			if ( ! UM()->common()->users()->can_view_user( $user_id ) ) {
				unset( $likes[ $key ] );
			}
		}

		return count( $likes );
	}

	/**
	 * @Checks if user liked specific wall post
	 */
	public function user_liked( $post_id ) {
		$res   = '';
		$users = get_post_meta( $post_id, '_liked', true );
		if ( $users && is_array( $users ) && in_array( get_current_user_id(), $users, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return to activity post after login
	 *
	 * @param $post_id
	 *
	 * @return string
	 */
	public function login_to_interact( $post_id = null ) {
		if ( UM()->is_request( 'ajax' ) ) {
			$curr_page = wp_get_referer();
		} else {
			$curr_page = UM()->permalinks()->get_current_url();
		}
		if ( ! empty( $post_id ) ) {
			$curr_page = add_query_arg( 'wall_post', $post_id, $curr_page );
		}

		$pattern = __( 'Please <a href="{register_page}" class="um-link um-link-secondary um-link-underline">sign up</a> or <a href="{login_page}" class="um-link um-link-secondary um-link-underline">sign in</a> to like or comment on this post.', $this->wall->textdomain ); // phpcs:ignore WordPress.WP.I18n

		return str_replace(
			array(
				'{current_page}',
				'{login_page}',
				'{register_page}',
			),
			array(
				$curr_page,
				add_query_arg( 'redirect_to', $curr_page, um_get_core_page( 'login' ) ),
				add_query_arg( 'redirect_to', $curr_page, um_get_core_page( 'register' ) ),
			),
			$pattern
		);
	}

	public function get_wall_posts( $args ) {
		$query = array(
			'fields'      => 'ids',
			'post_type'   => $this->wall->post_type,
			'post_status' => 'publish',
			'meta_query'  => array(),
		);

		$query = apply_filters( $this->wall->prefix . 'wall_posts_args', $query, $args );

		// Get posts.
		$query_obj = new \WP_Query( $query );

		return array(
			'result'      => $query_obj->get_posts(),
			'total_posts' => $query_obj->found_posts,
		);
	}

	/**
	 * Change #hashtags in the text to links to the hashtag archive page
	 *
	 * @param string $content original text
	 *
	 * @return string new text with links
	 */
	public function linkify_hashtags_in_content( $content ) {
		return preg_replace_callback(
			'/(?<!\w)#([\p{Pc}\p{N}\p{L}\p{Mn}]+)/um',
			function ( $m ) {
				$tag_name = $m[1];

				$term = get_term_by( 'name', $tag_name, 'um_hashtag' );
				if ( ! $term || is_wp_error( $term ) ) {
					$term = get_term_by( 'slug', sanitize_title( $tag_name ), 'um_hashtag' );
				}

				if ( $term && ! is_wp_error( $term ) ) {
					$link = add_query_arg( 'hashtag', $term->slug, um_get_core_page( 'activity' ) );
					if ( $link ) {
						return '<a class="um-hashtag um-link" href="' . esc_url( $link ) . '">#' . $tag_name . '</a>';
					}
				}

				return '#' . $tag_name;
			},
			$content
		);
	}

	/**
	 * @param string $content Content string
	 * @param string $context Content entity post||comment
	 * @param int    $id      Entity ID.
	 *
	 * @return string
	 */
	public function maybe_linkify_mentions( $content, $context, $id ) {
		if ( empty( $content ) ) {
			return $content;
		}

		$run = apply_filters( $this->wall->prefix . 'maybe_linkify_mentions_condition', true, $content, $context, $id );
		if ( true !== $run ) {
			return $content;
		}

		$mentioned = array();
		if ( 'post' === $context ) {
			$mentioned = get_post_meta( $id, '_mentioned', true );
		} elseif ( 'comment' === $context ) {
			$mentioned = get_comment_meta( $id, '_mentioned', true );
		}

		if ( empty( $mentioned ) ) {
			return $content;
		}

		$user_names = array();
		foreach ( $mentioned as $user_id1 ) {
			um_fetch_user( $user_id1 );
			$display_name = um_user( 'display_name' );
			if ( empty( $display_name ) ) {
				continue;
			}
			$user_names[ $user_id1 ] = $display_name;
		}

		uasort(
			$user_names,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		foreach ( $user_names as $user_id1 => $name ) {
			preg_match( '/(^|\s)(@' . preg_quote( $name, '/' ) . ')($|\s)/um', $content, $matches );

			if ( ! empty( $matches[2] ) ) {
				$content = preg_replace( '/(?<=^|\s)@' . preg_quote( $name, '/' ) . '(?=$|\s)/um', '<a href="' . esc_url( um_user_profile_url( $user_id1 ) ) . '" class="um-link">' . esc_html( $name ) . '</a>', $content );
			}
		}

		return $content;
	}
}
