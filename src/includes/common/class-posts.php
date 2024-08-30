<?php
namespace WallLib\common;

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
}
