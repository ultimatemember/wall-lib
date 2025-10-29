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
		add_action( 'wp_ajax_um_get_wall_post', array( $this, 'ajax_get_wall_post' ) );

		add_action( 'wp_ajax_um_wall_like_post', array( $this, 'like_post' ) );
		add_action( 'wp_ajax_um_wall_unlike_post', array( $this, 'unlike_post' ) );

		add_action( 'wp_ajax_um_wall_get_post_likes', array( $this, 'get_post_likes' ) );
		add_action( 'wp_ajax_nopriv_um_wall_get_post_likes', array( $this, 'get_post_likes' ) );

		add_action( 'wp_ajax_um_wall_remove_post', array( $this, 'remove_post' ) );

		add_action( 'wp_ajax_um_wall_get_full_post', array( $this, 'get_full_post' ) );
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
		$comm_num      = apply_filters( $this->wall->prefix . 'wall_comments_loadmore_number', 10 );
		$order_comment = apply_filters( $this->wall->prefix . 'wall_comments_loadmore_order', 10 );

		$args = array(
			'fields'      => 'ids',
			'post_type'   => $this->wall->post_type,
			'post_status' => 'publish',
			'meta_query'  => array(),
		);

		$args = apply_filters( $this->wall->prefix . 'wall_posts_args', $args, $data );

		$query = new \WP_Query( $args );

		$t_args = array(
			'wall_posts'       => $query->get_posts(),
			'um_activity_wall' => $this->wall,
			'comm_num'         => $comm_num,
			'order_comment'    => $order_comment,
		);

		$t_args = apply_filters( $this->wall->prefix . 'wall_template_args', $t_args, $args, $query );

//		add_filter( 'safe_style_css', array( $this->wall->common()->posts(), 'add_extra_safe_style_css' ) );
		$output = UM()->get_template( 'v3/posts-loop.php', $this->wall->plugin_basename, $t_args );
//		remove_filter( 'safe_style_css', array( $this->wall->common()->posts(), 'add_extra_safe_style_css' ) );

		wp_send_json_success( $output );
	}

	/**
	 * Add a new wall post
	 */
	public function ajax_get_wall_post() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['post_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$post_id = absint( $_POST['post_id'] );
		$wall_id = absint( $_POST['wall_id'] );
		// phpcs:enable WordPress.Security.NonceVerification

		check_ajax_referer( 'um_wall_get_post' . $post_id, 'nonce' );

		$post = get_post( $post_id );
		if ( empty( $post ) || is_wp_error( $post ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$attachments = get_children(
			array(
				'post_parent'    => $post_id,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'numberposts'    => -1,
			)
		);

		$old_ui = false;
		if ( empty( $attachments ) ) {
			if ( get_post_meta( $post_id, '_photo', true ) ) {
				$filename      = get_post_meta( $post_id, '_photo_filename', true );
				$attachments[] = $filename;
				$old_ui        = true;
			}
		}

		$uploaded_photos = array();
		if ( is_array( $attachments ) ) {
			$count = count( $attachments );

			if ( ! empty( $attachments ) ) {
				foreach ( $attachments as $attachment_id => $attachment ) {
					if ( $old_ui ) {
						$preview_url = $this->wall->common()->posts()->get_download_link( $post_id, get_current_user_id() );
					} else {
						$image       = wp_get_attachment_image_src( $attachment_id );
						$preview_url = $image[0];
						$filename    = $attachment->post_title;
					}

					$args = array(
						'photo_id'    => $attachment_id,
						'preview_url' => $preview_url,
						'filename'    => $filename,
					);

					$uploaded_photos[] = $args;
				}
			}
		} else {
			$count = '' !== $attachments ? 1 : 0;
		}

		$t_args = array(
			'post_id'         => $post_id,
			'wall_id'         => $wall_id,
			'post'            => $post,
			'count'           => $count,
			'uploaded_photos' => $uploaded_photos,
		);

		$output = UM()->get_template( 'v3/edit-post.php', $this->wall->plugin_basename, $t_args );

		wp_send_json_success( $output );
	}

	/**
	 * Add a new wall post via AJAX
	 */
	public function wall_publish() {
		// When '_post_id' === 0 then insert, else edit.
		if ( ! isset( $_POST['_post_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please specify the post ID. It\'s required.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$post_id = absint( $_POST['_post_id'] );

		if ( 0 === $post_id ) {
			check_ajax_referer( 'um-wall-post-publish', 'nonce' );
		} else {
			check_ajax_referer( 'um-wall-post-edit' . $post_id, 'nonce' );
		}

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
				$output    = wp_kses_post( sprintf( __( 'Post is submitted successfully. To view post <a href="%s" class="um-link">click here</a>.', $this->wall->textdomain ), $permalink ) ); // phpcs:ignore WordPress.WP.I18n
			} else {
				$output = $this->prepare_response( $post_id );
			}
		} else {
			if ( ! empty( $_POST['deleted_attachments'] ) ) {
				$deleted_ids = explode( ',', sanitize_text_field( $_POST['deleted_attachments'] ) );

				foreach ( $deleted_ids as $id ) {
					$attachment_id = absint( $id );
					if ( $attachment_id > 0 ) {
						wp_delete_attachment( $attachment_id, true );
					}
				}
			}

			$post_id = $this->handle_post_update( $post_id, $_post_content, $_post_images, $wall_id );
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
		$comm_num      = apply_filters( $this->wall->prefix . 'wall_comments_loadmore_number', 10 );
		$order_comment = apply_filters( $this->wall->prefix . 'wall_comments_loadmore_order', 10 );

		$t_args = array(
			'allowed_html'     => $this->wall->common()->posts()->get_allowed_html(),
			'wall_post'        => $post_id,
			'wall_posts'       => array( $post_id ),
			'single_post'      => true,
			'um_activity_wall' => $this->wall,
			'comm_num'         => $comm_num,
			'order_comment'    => $order_comment,
		);

		$template_name = apply_filters( $this->wall->prefix . 'wall_posts_template', 'v3/posts-loop.php' );

		return UM()->ajax()->esc_html_spaces( UM()->get_template( $template_name, $this->wall->plugin_basename, $t_args ) );
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
				'_action'           => 'status',
				'_original_content' => '',
			),
		);

		$orig_content = '';
		if ( trim( $_post_content ) ) {
			$orig_content = wp_kses_post( $_post_content );
			$safe_content = apply_filters( $this->wall->prefix . 'new_post', $orig_content, 0 );
		}

		$args    = apply_filters( $this->wall->prefix . 'insert_post_args', $args );
		$post_id = wp_insert_post( $args );

		// Hashtags taxonomy
		if ( '' !== $safe_content ) {
			$this->wall->common()->posts()->hashtagit( $post_id, $safe_content );
		}

		$converted_content = '';
		$excerpt_content   = '';
		if ( isset( $safe_content ) && '' !== $safe_content ) {
			$converted_content = $this->generate_embed_blocks_from_text( $safe_content );
			$converted_content = $this->wrap_links_with_meta_cards( $converted_content, $post_id );
			$converted_content = $this->linkify_hashtags_in_content( $converted_content );
			// Replace emojis codes
			$converted_content = convert_smilies( $converted_content ); // WordPress native converts text equivalent of smilies to images.
			$converted_content = UM()->shortcodes()->emotize( $converted_content ); // UM legacy emoji convert from the predefined list of emoji.
			$converted_content = wp_staticize_emoji( $converted_content ); // WordPress native converts emoji to a static img element.
			$converted_content = preg_replace( '#<p[^>]*?>#i', '', $converted_content );
			$converted_content = str_replace( '</p>', '<br>', $converted_content );
			$excerpt_content   = $this->shorten_string( $converted_content );

			update_post_meta( $post_id, '_original_content', $orig_content );
		}

		$content_final = apply_filters(
			$this->wall->prefix . 'insert_post_content_filter',
			$converted_content,
			get_current_user_id(),
			$post_id,
			'new'
		);

		// update post
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => $post_id,
				'post_name'    => $post_id,
				'post_content' => $content_final,
				'post_excerpt' => $excerpt_content,
			)
		);

		// Upload new images
		if ( ! empty( $_post_images ) ) {
			$this->upload_images( $_post_images, $post_id );
		}

		do_action( $this->wall->prefix . 'after_wall_post_published', $post_id, get_current_user_id(), $wall_id );

		update_post_meta( $post_id, '_um_post_version', $this->wall->plugin_version );

		return $post_id;
	}

	private function handle_post_update( $post_id, $_post_content, $_post_images, $wall_id ) {
		if ( trim( $_post_content ) ) {
			$orig_content = wp_kses_post( $_post_content );
			$safe_content = apply_filters( $this->wall->prefix . 'edit_post', $orig_content, 0 );

			$safe_content = apply_filters( $this->wall->prefix . 'update_post_content_filter', $safe_content, $this->wall->common()->posts()->get_author( $post_id ), $post_id, 'save' );

			$args['post_content'] = $safe_content;
		}

		$args['ID'] = $post_id;
		$args       = apply_filters( $this->wall->prefix . 'update_post_args', $args );
		wp_update_post( $args );

		// Hashtags taxonomy
		if ( '' !== $safe_content ) {
			$this->wall->common()->posts()->hashtagit( $post_id, $safe_content );
		}

		if ( isset( $safe_content ) && '' !== $safe_content ) {
			$converted_content = $this->generate_embed_blocks_from_text( $safe_content );
			$converted_content = $this->wrap_links_with_meta_cards( $converted_content, $post_id );
			$converted_content = $this->linkify_hashtags_in_content( $converted_content );
			// Replace emojis codes
			$converted_content = convert_smilies( $converted_content ); // WordPress native converts text equivalent of smilies to images.
			$converted_content = UM()->shortcodes()->emotize( $converted_content ); // UM legacy emoji convert from the predefined list of emoji.
			$converted_content = wp_staticize_emoji( $converted_content ); // WordPress native converts emoji to a static img element.
			$converted_content = preg_replace( '#<p[^>]*?>#i', '', $converted_content );
			$converted_content = str_replace( '</p>', '<br>', $converted_content );
			$excerpt_content   = $this->shorten_string( $converted_content );
			if ( $converted_content !== $safe_content ) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $converted_content,
						'post_excerpt' => $excerpt_content,
					)
				);
			}

			update_post_meta( $post_id, '_original_content', $orig_content );
		}

		// Upload new images
		if ( ! empty( $_post_images ) || get_post_meta( $post_id, '_photo', true ) ) {
			$this->upload_images( $_post_images, $post_id );
		}

		do_action( $this->wall->prefix . 'after_wall_post_updated', $post_id, get_current_user_id(), $wall_id );

		update_post_meta( $post_id, '_um_post_version', $this->wall->plugin_version );

		return $post_id;
	}

	/**
	 * Generate embed blocks from the text content
	 *
	 * @param string $raw_text original text
	 *
	 * @return string new text with embed blocks
	 */
	public function generate_embed_blocks_from_text( $raw_text ) {
		wp_oembed_add_provider(
			'#https?://(www\.)?x\.com/.+#i',
			'https://publish.twitter.com/oembed',
			true
		);

		$lines     = explode( "\n", trim( $raw_text ) );
		$result    = '';
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( empty( $line ) ) {
				continue;
			}

			// get a link from the string
			if ( preg_match( '#(https?://[^\s]+)#', $line, $matches ) ) {
				$url = $matches[1];

				$url_host = wp_parse_url( $url, PHP_URL_HOST );
				if ( $url_host && $url_host === $site_host ) {
					$result .= '<p>' . esc_html( $line ) . "</p>\n";
					continue;
				}

				// Get oEmbed data
				$wp_embed    = _wp_oembed_get_object();
				$provider    = $wp_embed->get_provider( $url );
				$oembed_data = $wp_embed->get_data( $url );

				if ( $provider && $oembed_data ) {
					$type          = isset( $oembed_data->type ) ? esc_attr( $oembed_data->type ) : 'rich';
					$provider_slug = isset( $oembed_data->provider_name ) ? sanitize_title( $oembed_data->provider_name ) : 'unknown';

					$block_json = wp_json_encode(
						array(
							'url'              => $url,
							'type'             => $type,
							'providerNameSlug' => $provider_slug,
							'responsive'       => true,
						)
					);

					$result .= <<<BLOCK
					<!-- wp:embed {$block_json} -->
					<figure class="um-active-oembed is-type-{$type} is-provider-{$provider_slug}">
					  <div class="um-active-oembed__wrappe is-type-{$type}">
						{$url}
					  </div>
					</figure>
					<!-- /wp:embed -->
					BLOCK;
				} else {
					// simple link
					$linked_line = preg_replace_callback(
						'#(https?://[^\s]+)#',
						function ( $m ) {
							$url = esc_url( $m[1] );
							return "<a target='_blank' rel='nofollow' class='um-link' href=\"{$url}\">{$url}</a>";
						},
						$line
					);

					$result .= "<p'>{$linked_line}</p>\n";
				}
			} else {
				$result .= '<p>' . esc_html( $line ) . "</p>\n";
			}
		}

		return $result;
	}

	public function wrap_links_with_meta_cards( $content, $post_id = 0 ) {
		remove_filter( 'the_content', 'wpautop' );
		// Get all URLs in the content
		$raw_urls = wp_extract_urls( $content );
		if ( empty( $raw_urls ) ) {
			return $content;
		}

		$all_urls = array_map( array( $this, 'normalize_url_candidate' ), $raw_urls );
		$all_urls = array_filter( $all_urls );                 // remove empty
		$all_urls = array_values( array_unique( $all_urls ) ); // remove duplicates

		if ( empty( $all_urls ) ) {
			return $content;
		}

		// Get URLs inside <figure> blocks to skip them
		$skip_urls = array();
		if ( preg_match_all( '#<figure[^>]*>.*?</figure>#si', $content, $figure_blocks ) ) {
			foreach ( $figure_blocks[0] as $figure_html ) {
				$figure_urls = wp_extract_urls( $figure_html );
				if ( $figure_urls ) {
					$skip_urls = array_merge( $skip_urls, $figure_urls );
				}
			}
		}

		// Process each unique URL
		foreach ( array_unique( $all_urls ) as $url ) {
			if ( in_array( $url, $skip_urls, true ) ) {
				continue;
			}

			$meta_card = $this->generate_link_preview_card( esc_url_raw( $url ), $post_id );

			// Replace only "naked" links not inside <a> or <iframe>
			$content = preg_replace_callback(
				'#<a\s+[^>]*href=["\']' . preg_quote( $url, '#' ) . '["\'][^>]*>\s*' . preg_quote( $url, '#' ) . '\s*</a>#i',
				function () use ( $meta_card ) {
					return $meta_card;
				},
				$content
			);

			$content = preg_replace_callback(
				'#(?<!["\'=])(' . preg_quote( $url, '#' ) . ')(?![^<]*?>)#',
				function () use ( $meta_card ) {
					return $meta_card;
				},
				$content
			);
			$content = preg_replace( '#<a[^>]*>\s*</a>#i', '', $content );
		}

		return $content;
	}

	private function normalize_url_candidate( $u ) {
		if ( ! is_string( $u ) ) {
			return '';
		}

		$u = html_entity_decode( $u, ENT_QUOTES, 'UTF-8' );
		$u = trim( $u );

		if ( strpos( $u, '\/' ) !== false ) {
			$u = str_replace( '\/', '/', $u );
		}
		if ( strpos( $u, '\\' ) !== false ) {
			$u = str_replace( '\\', '', $u );
		}

		if ( strpos( $u, '//' ) === 0 ) {
			$u = 'https:' . $u;
		}

		if ( ! preg_match( '~^https?://~i', $u ) ) {
			return '';
		}
		$u = esc_url_raw( $u );

		return $u;
	}

	private function absolutize_url( string $maybe, string $base ): string {
		$maybe = trim( $maybe );
		if ( '' === $maybe ) {
			return '';
		}

		// protocol-relative //example.com/...
		if ( strpos( $maybe, '//' ) === 0 ) {
			$scheme = parse_url( $base, PHP_URL_SCHEME ) ?: 'https';
			return $scheme . ':' . $maybe;
		}

		// absolute http/https
		if ( preg_match( '~^https?://~i', $maybe ) ) {
			return $maybe;
		}

		// relative /path or path
		$bp = wp_parse_url( $base );
		if ( empty( $bp['scheme'] ) || empty( $bp['host'] ) ) {
			return $maybe;
		}
		$scheme   = $bp['scheme'];
		$host     = $bp['host'];
		$port     = isset($bp['port']) ? ':' . $bp['port'] : '';
		$basePath = isset($bp['path']) ? $bp['path'] : '/';

		// если начинается с / — от корня, иначе от директории
		if ( strpos( $maybe, '/' ) === 0 ) {
			$path = $maybe;
		} else {
			$dir  = rtrim( preg_replace( '~/[^/]*$~', '/', $basePath ), '/' ) . '/';
			$path = $dir . $maybe;
		}

		// нормализовать ../ и ./
		$parts = array();
		foreach ( explode( '/', $path ) as $seg ) {
			if ( '' === $seg || '.' === $seg ) {
				continue;
			}
			if ( '..' === $seg ) {
				array_pop( $parts ); {
				continue;
				}
			}
			$parts[] = $seg;
		}
		$path = '/' . implode( '/', $parts );

		return "{$scheme}://{$host}{$port}{$path}";
	}

	private function generate_link_preview_card( $url, $post_id = 0 ) {
		$url = esc_url_raw( $url );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 3,
				'headers'     => array(
					'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/LinkPreview)',
					'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '<a class="um-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow ugc">' . esc_html( $url ) . '</a>';
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return '<a class="um-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow ugc">' . esc_html( $url ) . '</a>';
		}

		libxml_use_internal_errors( true );
		$doc    = new \DOMDocument();
		$loaded = $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $body );
		libxml_clear_errors();

		// если HTML не распарсился — фоллбек
		if ( ! $loaded ) {
			return '<a class="um-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow ugc">' . esc_html( $url ) . '</a>';
		}

		$title = $desc = $img = '';
		$image_width = $image_height = null;

		// Собираем OG/Meta
		$metas = $doc->getElementsByTagName( 'meta' );
		foreach ( $metas as $meta ) {
			if ( ! $meta instanceof \DOMElement ) {
				continue;
			}
			$prop = strtolower( (string) $meta->getAttribute( 'property' ) );
			$name = strtolower( (string) $meta->getAttribute( 'name' ) );
			$val  = trim( (string) $meta->getAttribute( 'content' ) );
			if ( '' === $val ) {
				continue;
			}

			if ( 'og:title' === $prop || 'title' === $name ) {
				$title = $val;
			} elseif ( 'og:description' === $prop || 'description' === $name ) {
				$desc = $val;
			} elseif ( 'og:image' === $prop ) {
				$img = $this->absolutize_url( $val, $url );
			} elseif ( 'og:image:width' === $prop ) {
				$image_width = absint( $val );
			} elseif ( 'og:image:height' === $prop ) {
				$image_height = absint( $val );
			}
		}

		// Fallback: <title>
		if ( '' === $title ) {
			$nodes = $doc->getElementsByTagName( 'title' );
			if ( $nodes->length > 0 ) {
				$title = trim( (string) $nodes->item(0)->nodeValue );
			}
		}

		// Fallback: first <img>
		if ( '' === $img ) {
			$imgs = $doc->getElementsByTagName( 'img' );
			foreach ( $imgs as $img_tag ) {
				$src = trim( (string) $img_tag->getAttribute( 'src' ) );
				if ( '' === $src ) {
					continue;
				}
				$img = $this->absolutize_url( $src, $url );
				if ( preg_match( '~^https?://~i', $img ) ) {
					break;
				}
				$img = '';
			}
		}

		$domain = parse_url( $url, PHP_URL_HOST );
		$domain = $domain ? strtoupper( preg_replace( '~^www\.~i', '', $domain ) ) : '';

		$title_esc   = $title ? esc_html( $title ) : esc_html__( 'Untitled', 'um-activity' );
		$desc_html   = $desc   ? '<div class="um-meta-desc">' . esc_html( mb_substr( $desc, 0, 240 ) ) . '</div>' : '';
		$domain_html = $domain ? '<div class="um-meta-domain">' . esc_html( $domain ) . '</div>' : '';

		if ( $img ) {
			if ( $image_width && $image_width <= 400 ) {
				$img_tag    = '<div class="um-meta-thumb um-meta-thumb-profile"><img src="' . esc_url( $img ) . '" alt="" class="um-activity-featured-img" /></div>';
				$link_class = 'um-meta-link-profile';
			} else {
				$img_tag    = '<div class="um-meta-thumb"><img src="' . esc_url( $img ) . '" alt="" /></div>';
				$link_class = '';
			}
		} else {
			$img_tag    = '';
			$link_class = '';
		}

		$url_attr = esc_url( $url );

		return <<<HTML
<figure class="um-meta-preview">
  <a href="{$url_attr}" class="{$link_class}" target="_blank" rel="noopener nofollow ugc">
    {$img_tag}
    <div><span class="um-meta-text"><span class="um-meta-title">{$title_esc}</span>{$desc_html}{$domain_html}</span></div>
  </a>
</figure>
HTML;
	}
//	private function generate_link_preview_card( $url, $post_id = 0 ) {
//		$response = wp_remote_get(
//			$url,
//			array(
//				'timeout' => 5,
//				'headers' => array(
//					'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/LinkPreview)',
//				),
//			),
//		);
//
//		if ( is_wp_error( $response ) ) {
//			return '<a class="um-link" href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a>';
//		}
//
//		$body = wp_remote_retrieve_body( $response );
//		if ( empty( $body ) ) {
//			return '<a class="um-link" href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a>';
//		}
//
//		libxml_use_internal_errors( true );
//		$doc = new \DOMDocument();
/*		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $body );*/
//
//		$title = $desc = $img = '';
//
//		foreach ( $doc->getElementsByTagName( 'meta' ) as $meta ) {
//			$prop = $meta->getAttribute( 'property' );
//			$name = $meta->getAttribute( 'name' );
//
//			if ( 'og:title' === $prop || 'title' === $name ) {
//				$title = $meta->getAttribute( 'content' );
//			}
//			if ( 'og:description' === $prop || 'description' === $name ) {
//				$desc = $meta->getAttribute( 'content' );
//			}
//			if ( 'og:image' === $meta->getAttribute( 'property' ) ) {
//				$img  = $src = trim( str_replace( '\\', '/', $meta->getAttribute( 'content' ) ) );
//				$data = $this->is_image( $src );
//				if ( is_array( $data ) ) {
//					$img          = $src;
//					$image_width  = $data[0];
//					$image_height = $data[1];
//				}
//			}
//
//			if ( 'og:image:width' === $meta->getAttribute( 'property' ) ) {
//				$image_width = trim( $meta->getAttribute( 'content' ) );
//			}
//			if ( 'og:image:height' === $meta->getAttribute( 'property' ) ) {
//				$image_height = trim( $meta->getAttribute( 'content' ) );
//			}
//		}
//
//		// Fallback: use <title> tag
//		if ( empty( $title ) ) {
//			$nodes = $doc->getElementsByTagName( 'title' );
//			if ( $nodes->length > 0 ) {
//				$title = $nodes->item( 0 )->nodeValue;
//			}
//		}
//
//		// Fallback: get first image from page if no og:image
//		if ( empty( $img ) ) {
//			foreach ( $doc->getElementsByTagName( 'img' ) as $img_tag ) {
//				$src = $img_tag->getAttribute( 'src' );
//				if ( strpos( $src, '//' ) === 0 ) {
//					$src = 'http:' . $src;
//				}
//				if ( filter_var( $src, FILTER_VALIDATE_URL ) ) {
//					$img = $src;
//					break;
//				}
//			}
//		}
//
//		$domain = wp_parse_url( $url, PHP_URL_HOST );
//		$title  = $title ? esc_html( $title ) : esc_html__( 'Untitled', 'um-activity' );
//		$desc   = $desc ? '<div class="um-meta-desc">' . esc_html( $desc ) . '</div>' : '';
//		$domain = $domain ? '<div class="um-meta-domain">' . esc_html( strtoupper( $domain ) ) . '</div>' : '';
//		if ( isset( $image_width ) && $image_width <= 400 ) {
//			$img_tag    = '<div class="um-meta-thumb um-meta-thumb-profile"><img src="' . esc_url( $img ) . '" alt="" class="um-activity-featured-img" /></div>';
//			$link_class = 'um-meta-link-profile';
//		} else {
//			$img_tag    = $img ? '<div class="um-meta-thumb"><img src="' . esc_url( $img ) . '" alt=""></div>' : '';
//			$link_class = '';
//		}
//
//		return <<<HTML
//		<figure class="um-meta-preview"><a href="{$url}" class="{$link_class}" target="_blank">{$img_tag}<div><span class="um-meta-text"><span class="um-meta-title">{$title}</span>{$desc}{$domain}</span></div></a></figure>
//		HTML;
//	}

	/**
	 * Change #hashtags in the text to links to the hashtag archive page
	 *
	 * @param string $content original text
	 *
	 * @return string new text with links
	 */
	public function linkify_hashtags_in_content( $content ) {
		$taxonomy = 'um_hashtag';

		return preg_replace_callback(
			'/(?<!\w)#([\p{Pc}\p{N}\p{L}\p{Mn}]+)/um',
			function ( $m ) use ( $taxonomy ) {
				$tag_name = $m[1];

				$term = get_term_by( 'name', $tag_name, $taxonomy );
				if ( ! $term || is_wp_error( $term ) ) {
					$term = get_term_by( 'slug', sanitize_title( $tag_name ), $taxonomy );
				}

				if ( $term && ! is_wp_error( $term ) ) {
					$link = um_get_core_page( 'activity' ) . '?hashtag=' . $term->slug;
					if ( $link ) {
						return '<a class="um-hashtag um-link" href="' . esc_url( $link ) . '">#' . esc_html( $tag_name ) . '</a>';
					}
				}

				return '#' . esc_html( $tag_name );
			},
			$content
		);
	}

	/***
	 ***    @shorten any string based on word count
	 ***/
	public function shorten_string( $string ) {
		$words_limit = absint( UM()->options()->get( 'activity_post_truncate' ) );
		if ( ! $words_limit ) {
			return $string;
		}

		$blocks = array();
		$offset = 0;

		// find all <figure> blocks
		preg_match_all( '#<figure.*?</figure>#si', $string, $figure_matches, PREG_OFFSET_CAPTURE );

		foreach ( $figure_matches[0] as $match ) {
			$pos   = $match[1];
			$len   = strlen( $match[0] );
			$block = substr( $string, $offset, $pos - $offset );

			$text_chunks = preg_split( '/<br\s*\/?>/i', $block );
			foreach ( $text_chunks as $chunk ) {
				$chunk = trim( $chunk );
				if ( '' !== $chunk ) {
					$blocks[] = array(
						'type'    => 'text',
						'content' => $chunk,
					);
				}
			}

			$blocks[] = array(
				'type'    => 'figure',
				'content' => $match[0],
			);
			$offset   = $pos + $len;
		}

		// add remaining text after last <figure>
		if ( $offset < strlen( $string ) ) {
			$remaining   = substr( $string, $offset );
			$text_chunks = preg_split( '/<br\s*\/?>/i', $remaining );
			foreach ( $text_chunks as $chunk ) {
				$chunk = trim( $chunk );
				if ( '' !== $chunk ) {
					$blocks[] = array(
						'type'    => 'text',
						'content' => $chunk,
					);
				}
			}
		}

		// count total words
		$total_words = 0;
		foreach ( $blocks as $block ) {
			if ( 'figure' === $block['type'] ) {
				++$total_words;
			} else {
				$words        = preg_split( '/\s+/', wp_strip_all_tags( $block['content'] ) );
				$total_words += count( $words );
			}
		}

		// return empty if within limit
		if ( $total_words <= $words_limit ) {
			return '';
		}

		// get excerpt
		$excerpt = '';
		$count   = 0;

		foreach ( $blocks as $block ) {
			if ( $count >= $words_limit ) {
				break;
			}

			if ( 'figure' === $block['type'] ) {
				if ( $count + 1 <= $words_limit ) {
					$excerpt .= $block['content'];
					++$count;
				}
			} else {
				$text  = wp_strip_all_tags( $block['content'] );
				$words = preg_split( '/\s+/', $text );

				$remaining = $words_limit - $count;
				if ( count( $words ) <= $remaining ) {
					$excerpt .= $block['content'] . '<br>';
					$count   += count( $words );
				} else {
					$excerpt .= esc_html( implode( ' ', array_slice( $words, 0, $remaining ) ) );
					break;
				}
			}
		}

		return $excerpt;
	}

	private function upload_images( $_post_images, $post_id ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';

			$credentials = request_filesystem_credentials( site_url() );
			WP_Filesystem( $credentials );
		}

		$allowed      = UM()->common()->filesystem()::image_mimes( 'allowed' );
		$allowed      = apply_filters( $this->wall->prefix . 'wall_allowed_mime_types', $allowed );
		$user_basedir = UM()->common()->filesystem()->get_user_uploads_dir( get_current_user_id() );
		if ( is_array( $_post_images ) ) {
			foreach ( $_post_images as $photo ) {
				$path       = sanitize_file_name( $photo['path'] );
				$filename   = sanitize_file_name( $photo['filename'] );
				$image_type = wp_check_filetype( $path, $allowed ); // Don't need checking empty condition below, because had validation above.
				$old_path   = wp_normalize_path( UM()->common()->filesystem()->get_file_by_hash( $photo['hash'] ) );
				if ( file_exists( $user_basedir . DIRECTORY_SEPARATOR . $filename ) ) {
					$filename = wp_unique_filename( $user_basedir . DIRECTORY_SEPARATOR, $filename ); // Make the file name unique in the (new) upload directory.
				}
				$new_path = wp_normalize_path( $user_basedir . DIRECTORY_SEPARATOR . $filename );

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
		// OLD UI image transfer
		if ( get_post_meta( $post_id, '_photo', true ) ) {
			$photo      = get_post_meta( $post_id, '_photo', true );
			$photo_data = get_post_meta( $post_id, '_photo_metadata', true );
			$filename   = $photo;
			if ( isset( $photo_data['original_name'] ) && '' !== $photo_data['original_name'] ) {
				$filename = $photo_data['original_name'];
				$old_path = wp_normalize_path( UM()->common()->filesystem()->get_user_uploads_dir( get_current_user_id() ) . DIRECTORY_SEPARATOR . $photo );
				if ( file_exists( $user_basedir . DIRECTORY_SEPARATOR . $filename ) ) {
					$filename = wp_unique_filename( $user_basedir . DIRECTORY_SEPARATOR, $filename ); // Make the file name unique in the (new) upload directory.
				}
				$new_path = wp_normalize_path( UM()->common()->filesystem()->get_user_uploads_dir( get_current_user_id() ) . DIRECTORY_SEPARATOR . $filename );
				$wp_filesystem->move( $old_path, $new_path, true );
			}

			$image_type = wp_check_filetype( $new_path, $allowed );

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

			// transfer old UI image meta to new UI
			update_post_meta( $post_id, '_transfer_new_ui', 1 );
		}
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

		$liked_array = is_array( $liked ) ? $liked : array();
		foreach ( $liked as $key => $user_id ) {
			if ( ! UM()->common()->users()->can_view_user( $user_id ) ) {
				unset( $liked_array[ $key ] );
			}
		}

		$increase_likes = false;
		$likes          = count( $liked_array );

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
			++$likes;
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

		$liked_array = is_array( $liked ) ? $liked : array();
		foreach ( $liked as $key => $user_id ) {
			if ( ! UM()->common()->users()->can_view_user( $user_id ) ) {
				unset( $liked_array[ $key ] );
			}
		}

		$likes = count( $liked_array );

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

		foreach ( $likes as $key => $user_id ) {
			if ( ! UM()->common()->users()->can_view_user( $user_id ) ) {
				unset( $likes[ $key ] );
			}
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

		check_ajax_referer( 'um_wall_delete_post' . $post_id, 'nonce' );
		// phpcs:enable WordPress.Security.NonceVerification

		if ( ! $this->wall->common()->user()->can_remove_post( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to remove this post.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		wp_delete_post( $post_id, true );

		wp_send_json_success();
	}

	/**
	 * Get full post via AJAX
	 */
	public function get_full_post() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST['post_id'] ) ) {
			wp_send_json_error( __( 'Wrong post ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$post_id = absint( $_POST['post_id'] );
		// phpcs:enable WordPress.Security.NonceVerification
		check_ajax_referer( 'um_wall_see_more' . $post_id, 'nonce' );

		// phpcs:enable WordPress.Security.NonceVerification
		$content_raw = get_post_field( 'post_content', $post_id );
		$content     = do_blocks( $content_raw );
		$content     = wpautop( $content );
		$content     = do_shortcode( $content );
		global $wp_embed;
		$content = $wp_embed->autoembed( $content );

		wp_send_json_success( array( 'content' => $content ) );
	}
}
