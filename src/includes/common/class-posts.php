<?php
//namespace WallLib\common;
namespace Dev\UM_Activity\WallLib\common;
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
			);

			$allowed_html['strong']['onclick'] = true;
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
					$content .= "<img src=\"{$photo_url}\" title=\"{$title}\" alt=\"\" />";
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
	 * Add hashtags
	 *
	 * @param int $post_id
	 * @param string $content
	 * @param bool $append
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
	 * Convert hashtags
	 *
	 * @param $content
	 *
	 * @return mixed
	 */
	public function hashtag_links( $content ) {
		// hashtag must have space or start line before and space or end line after. Hashtag can contain digits, letters, underscore. Not space or dash "-".
		$content = preg_replace_callback( '/(^|\s)(#([\p{Pc}\p{N}\p{L}\p{Mn}]+))/um', array( $this, 'hashtag_replace_links_cb' ), $content );
		return $content;
	}


	/**
	 * @param array $matches
	 *
	 * @return string
	 */
	public function hashtag_replace_links_cb( $matches ) {
		$url = apply_filters( $this->wall->prefix . 'um_wall_get_core_page', '' );
		return $matches[1] . '<a href="' . add_query_arg( 'hashtag', $matches[3], $url ) . '" class="um-link um-link-secondary">' . $matches[2] . '</a>';
	}

	/**
	 * Make links clickable
	 *
	 * @param $content
	 *
	 * @return mixed|null|string|string[]
	 */
	public function make_links_clickable( $content ) {
		$shortcode  = apply_filters( $this->wall->prefix . 'wall_iframe_shortcode_links_clickable', '' );
		$has_iframe = preg_match( '/<iframe.*src=\"(.*)\".*><\/iframe>/isU', $content, $matches );

		if ( $has_iframe ) {
			$content = preg_replace( '/<iframe.*?\/iframe>/i', $shortcode, $content );
		}

		$attributes = apply_filters(
			$this->wall->prefix . 'wall_make_links_clickable_attrs',
			array(
				'target' => '_blank',
				'class'  => 'um-link',
			)
		);

		$attribute_string = '';

		foreach ( $attributes as $key => $value ) {
			$attribute_string .= esc_html( $key ) . '="' . esc_attr( $value ) . '" ';
		}

		$content = preg_replace( '/(<a\b[^><]*)>/i', '$1 ' . trim( $attribute_string ) . '>', make_clickable( $content ) );

		if ( $has_iframe && isset( $matches[0] ) ) {
			$content = str_replace( $shortcode, $matches[0], $content );
		}

		return $content;
	}

	/**
	 * Get a summarized content length
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	public function get_content( $post_id = 0 ) {
		if ( empty( $post_id ) ) {
			$loop_post_id = get_the_ID();
			if ( empty( $loop_post_id ) ) {
				return '';
			}

			$post_id = $loop_post_id;
		}

		$post = get_post( $post_id );
		if ( empty( $post ) ) {
			return '';
		}
		$content = $post->post_content;

		$has_oembed  = get_post_meta( $post_id, '_oembed', true );
		$shared_link = get_post_meta( $post_id, '_shared_link', true );
		$video_url   = get_post_meta( $post_id, '_video_url', true );

		if ( $has_oembed ) {
			$content = str_replace( $has_oembed, '', $content );
		}
		if ( $shared_link ) {
			$content = str_replace( $shared_link, '', $content );
		}
		if ( $video_url ) {
			$content = str_replace( $video_url, '', $content );
		}

		$content = trim( $content );
		if ( '' === $content ) {
			return '';
		}

		if ( 'status' === $this->get_action_type( $post_id ) ) {
			$content = $this->shorten_string( $content );
		}
		$content = $this->make_links_clickable( $content );
		$content = $this->hashtag_links( $content );

		// strip avatars
		if ( preg_match( '/\<img src=\"([^\"]+)\" class="(gr)?avatar/', $content, $matches ) ) {
			$src   = $matches[1];
			$found = @getimagesize( $src );
			if ( ! $found ) {
				$content = str_replace( $src, um_get_default_avatar_uri(), $content );
			}
		}

		$content = $this->remove_vc_from_excerpt( $content );

		if ( $has_oembed ) {
			$content .= $has_oembed;
		}

		$author_id = $this->get_author( $post_id );
		if ( $author_id ) {
			$author_data = get_userdata( $author_id );

			if ( ! empty( $author_data ) ) {
				$search = array(
					'{author_name}',
					'{author_profile}',
				);

				$replace = array(
					$author_data->display_name,
					um_user_profile_url( $author_id ),
				);

				$content = nl2br( str_replace( $search, $replace, $content ) );
			}
		}

		// Replace emojis codes
		$content = convert_smilies( $content );
		if ( isset( UM()->shortcodes()->emoji ) ) {
			$content = UM()->shortcodes()->emotize( $content );
		}

		// Add related image if no image
		if ( ! strpos( $content, '<span class="post-image">' ) ) {
			$related_id = get_post_meta( $post_id, '_related_id', true );
			if ( ! empty( $related_id ) ) {
				$post_image_url = $this->get_post_image_url( $related_id );
				if ( $post_image_url ) {
					$post_image = '<span class="post-image"><img src="' . esc_url( $post_image_url ) . '" alt="' . esc_attr( basename( $post_image_url ) ) . '" title="#' . esc_attr( get_the_title( $related_id ) ) . '" class="um-wall-featured-img" /></span>';
					$content    = str_replace( '<span class="post-title">', $post_image . '<span class="post-title">', $content );
				}
			}
		}

		return apply_filters( $this->wall->prefix . 'post_content', $content, $post );
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
	 * @shorten any string based on word count
	 **/
	public function shorten_string( $string ) {
		$retval        = $string;
		$wordsreturned = UM()->options()->get( $this->wall->prefix . 'post_truncate' );
		if ( ! $wordsreturned ) {
			return $string;
		}
		$array = explode( ' ', $string );
		if ( count( $array ) <= $wordsreturned ) {
			$retval = $string;
		} else {
			$res    = array_splice( $array, $wordsreturned );
			$retval = implode( ' ', $array ) . ' <span class="um-wall-seemore">(<a href="" class="um-link">' . esc_html__( 'See more', $this->wall->textdomain ) . '</a>)</span> <span class="um-wall-hiddentext">' . implode( ' ', $res ) . '</span>'; // phpcs:ignore WordPress.WP.I18n
		}

		return $retval;
	}

	/**
	 * Removes Visual Composer's shortcodes
	 *
	 * @param  string $excerpt
	 *
	 * @return string
	 */
	public function remove_vc_from_excerpt( $excerpt ) {
		$patterns     = '/\[[\/]?vc_[^\]]*\]|[[\/]?nectar_[^\]]*\]|[[\/]?cspm_[^\]]*\]/';
		$replacements = '';

		return preg_replace( $patterns, $replacements, $excerpt );
	}

	/**
	 * Get content link.
	 * @param $content
	 *
	 * @return mixed|null
	 */
	public function get_content_link( $content ) {
		$arr_urls = wp_extract_urls( $content );
		if ( ! empty( $arr_urls ) ) {
			foreach ( $arr_urls as $key => $url ) {
				if ( ! strstr( $url, 'vimeo' ) && ! strstr( $url, 'youtube' ) && ! strstr( $url, 'youtu.be' ) ) {
					$url = apply_filters( $this->wall->prefix . 'content_link', $url, $content );
					return $url;
				}
			}
		}

		return null;
	}

	public function ssss() {
		return 'ssss';
	}

	/**
	 * Check if URL is oEmbed supported
	 *
	 * @param $url
	 *
	 * @return bool|false|string
	 */
	public function is_oembed( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		$providers = array(
			'mixcloud.com'   => array( 'height' => 200 ),
			'soundcloud.com' => array( 'height' => 200 ),
			'instagram.com'  => array(
				'height' => 500,
				'width'  => 500,
			),
			'twitter.com'    => array(
				'height' => 500,
				'width'  => 700,
			),
			't.co'           => array(
				'height' => 500,
				'width'  => 700,
			),
		);

		$providers = apply_filters( $this->wall->prefix . 'oembed_providers', $providers );
		foreach ( $providers as $provider => $size ) {
			if ( false !== strpos( $url, $provider ) ) {
				return wp_oembed_get( $url, $size );
			}
		}

		return false;
	}

	/**
	 * Set url meta
	 *
	 * @param $url
	 * @param $post_id
	 *
	 * @return string
	 */
	public function set_url_meta( $url, $post_id ) {
		$request = wp_remote_get( $url );

		// Try to get remote page using request with headers if simple request fails
		if ( ! is_array( $request ) || empty( $request['response'] ) || empty( $request['response']['code'] ) || 200 !== $request['response']['code'] ) {
			$user_agent = empty( $_SERVER['HTTP_USER_AGENT'] ) ? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.121 Safari/537.36' : $_SERVER['HTTP_USER_AGENT'];

			$request = wp_remote_get(
				$url,
				array(
					'headers' => array(
						'accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
						'accept-encoding'           => 'gzip, deflate, br',
						'accept-language'           => 'en-US,en;q=0.5',
						'cache-control'             => 'max-age=0',
						'upgrade-insecure-requests' => 1,
						'user-agent'                => $user_agent,
					),
				)
			);
		}

		$response = wp_remote_retrieve_body( $request );

		$html   = new DOMDocument();
		$source = mb_convert_encoding( $response, 'HTML-ENTITIES', 'UTF-8' );
		if ( empty( $source ) ) {
			return '';
		}

		@$html->loadHTML( $source );
		$tags = null;

		$title         = $html->getElementsByTagName( 'title' );
		$tags['title'] = $title->item( 0 )->nodeValue;

		foreach ( $html->getElementsByTagName( 'meta' ) as $meta ) {
			if ( 'og:image' === $meta->getAttribute( 'property' ) ) {
				$tags['image'] = trim( str_replace( '\\', '/', $meta->getAttribute( 'content' ) ) );
				$src           = $tags['image'];
				$data          = $this->is_image( $src );
				if ( is_array( $data ) ) {
					$tags['image']        = $src;
					$tags['image_width']  = $data[0];
					$tags['image_height'] = $data[1];
				}
			}
			if ( 'og:image:width' === $meta->getAttribute( 'property' ) ) {
				$tags['image_width'] = trim( $meta->getAttribute( 'content' ) );
			}
			if ( 'og:image:height' === $meta->getAttribute( 'property' ) ) {
				$tags['image_height'] = trim( $meta->getAttribute( 'content' ) );
			}
			if ( 'description' === $meta->getAttribute( 'name' ) ) {
				$tags['description'] = trim( str_replace( '\\', '/', $meta->getAttribute( 'content' ) ) );
			}
		}

		if ( ! isset( $tags['image'] ) ) {
			foreach ( $html->getElementsByTagName( 'img' ) as $img ) {
				$src = esc_url( $img->getAttribute( 'src' ) );
				if ( false !== strpos( $src, '\\' ) ) {
					$src = str_replace( '\\', '/', $src );
				}
				if ( 0 === strpos( $src, '//' ) ) {
					$src = 'http:' . $src;
				}
				$tags['image'] = $src;
				$data          = $this->is_image( $src );
				if ( is_array( $data ) ) {
					$tags['image_width']  = $data[0];
					$tags['image_height'] = $data[1];
					break;
				}
			}
		}

		/* Display the meta now */

		if ( isset( $tags['image_width'] ) && $tags['image_width'] <= 400 ) {
			$content = '<span class="post-meta" style="position:relative;min-height: ' . ( absint( $tags['image_height'] / 2 ) - 10 ) . 'px;padding-left:' . $tags['image_width'] / 2 . 'px;"><a href="{post_url}" target="_blank">{post_image} {post_title} {post_excerpt} {post_domain}</a></span>';
		} else {
			$content = '<span class="post-meta"><a href="{post_url}" target="_blank">{post_image} {post_title} {post_excerpt} {post_domain}</a></span>';
		}

		if ( isset( $tags['description'] ) ) {
			if ( isset( $tags['image_width'] ) && 400 >= $tags['image_width'] ) {
				$content = str_replace( '{post_excerpt}', '', $content );
			} else {
				$content = str_replace( '{post_excerpt}', '<span class="post-excerpt">' . $tags['description'] . '</span>', $content );
			}
		} else {
			$content = str_replace( '{post_excerpt}', '', $content );
		}

		if ( isset( $tags['title'] ) ) {
			$content = str_replace( '{post_title}', '<span class="post-title">' . mb_convert_encoding( $tags['title'], 'HTML-ENTITIES', 'UTF-8' ) . '</span>', $content );
		} else {
			$content = str_replace( '{post_title}', '<span class="post-title">' . esc_html__( 'Untitled', $this->wall->textdomain ) . '</span>', $content ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( isset( $tags['image'] ) ) {
			if ( isset( $tags['image_width'] ) && 400 >= $tags['image_width'] ) {
				$content = str_replace( '{post_image}', '<span class="post-image" style="position:absolute;left:0;top:0;width:' . $tags['image_width'] / 2 . 'px;"><img src="' . $tags['image'] . '" alt="" title="" class="um-activity-featured-img" /></span>', $content );
			} else {
				$content = str_replace( '{post_image}', '<span class="post-image"><img src="' . $tags['image'] . '" alt="" title="" class="um-activity-featured-img" /></span>', $content );
			}
		} else {
			$content = str_replace( '{post_image}', '', $content );
		}

		$parse = wp_parse_url( $url );

		$content = str_replace( '{post_url}', $url, $content );
		$content = str_replace( '{post_domain}', '<span class="post-domain">' . strtoupper( $parse['host'] ) . '</span>', $content );

		update_post_meta( $post_id, '_shared_link', trim( $content ) );

		return trim( $content );
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
		return absint( get_post_meta( $post_id, '_likes', true ) );
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
}
