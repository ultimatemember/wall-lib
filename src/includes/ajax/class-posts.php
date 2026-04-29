<?php
namespace WallLib\ajax;

use WP_Filesystem_Base;
use WP_Query;

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

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_load_posts', array( $this, 'ajax_load_wall' ) );
		add_action( 'wp_ajax_nopriv_' . $this->wall->prefix . 'wall_load_posts', array( $this, 'ajax_load_wall' ) );

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_publish', array( $this, 'wall_publish' ) );
		add_action( 'wp_ajax_' . $this->wall->prefix . 'get_wall_post', array( $this, 'ajax_get_wall_post' ) );

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_like_post', array( $this, 'like_post' ) );
		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_unlike_post', array( $this, 'unlike_post' ) );

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_report_post', array( $this, 'report_post' ) );
		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_unreport_post', array( $this, 'unreport_post' ) );

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_get_post_likes', array( $this, 'get_post_likes' ) );
		add_action( 'wp_ajax_nopriv_' . $this->wall->prefix . 'wall_get_post_likes', array( $this, 'get_post_likes' ) );

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_remove_post', array( $this, 'remove_post' ) );

		add_action( 'wp_ajax_' . $this->wall->prefix . 'wall_get_full_post', array( $this, 'get_full_post' ) );
	}

	/**
	 * Load wall posts
	 */
	public function ajax_load_wall() {
		do_action( $this->wall->prefix . 'before_wall_load_posts' );

		if ( UM()->is_rate_limited( 'wall_load_posts' ) ) {
			wp_send_json_error( __( 'Too many requests', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$user_id = empty( $_POST['user_id'] ) ? 0 : absint( $_POST['user_id'] );

		$data = apply_filters( $this->wall->prefix . 'wall_load_posts_data', array() );

		$comm_num      = apply_filters( $this->wall->prefix . 'wall_comments_loadmore_number', 10 );
		$order_comment = apply_filters( $this->wall->prefix . 'wall_comments_loadmore_order', 10 );

		$args = array(
			'fields'      => 'ids',
			'post_type'   => $this->wall->post_type,
			'post_status' => 'publish',
			'meta_query'  => array(),
		);
		$args = apply_filters( $this->wall->prefix . 'wall_posts_args', $args, $data );

		$query = new WP_Query( $args );

		if ( 0 === absint( $query->found_posts ) ) {
			wp_send_json_success( array( 'empty' => '<div class="um-wall-empty">' . __( 'There are no posts', $this->wall->textdomain ) . '</div>' ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$t_args = array(
			'wall_posts'       => $query->posts,
			'um_activity_wall' => $this->wall,
			'comm_num'         => $comm_num,
			'order_comment'    => $order_comment,
			'profile_id'       => $user_id,
			'allowed_html'     => $this->wall->common()->posts()->get_allowed_html(),
		);

		// phpcs:disable WordPress.Security.NonceVerification -- already verified here
		if ( ! empty( $_POST['core_page'] ) ) {
			$t_args['page'] = sanitize_key( $_POST['core_page'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification -- already verified here

		$t_args        = apply_filters( $this->wall->prefix . 'wall_template_args', $t_args, $args, $query, $data );
		$template_name = apply_filters( $this->wall->prefix . 'wall_posts_template', 'v3/posts-loop.php' );

		$output = UM()->get_template( $template_name, $this->wall->plugin_basename, $t_args );

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

		$editor = apply_filters( $this->wall->prefix . 'wall_html_editor', 0 );

		$t_args = array(
			'post_id'         => $post_id,
			'post'            => $post,
			'count'           => $count,
			'uploaded_photos' => $uploaded_photos,
			'attachments'     => $attachments,
			'allowed_html'    => $this->wall->common()->posts()->get_allowed_html(),
			'editor'          => $editor,
		);

		$t_args        = apply_filters( $this->wall->prefix . 'wall_edit_post_template_args', $t_args );
		$template_name = apply_filters( $this->wall->prefix . 'wall_edit_posts_template', 'v3/edit-post.php' );

		add_filter( 'um_late_escaping_allowed_tags', array( $this->wall->common()->posts(), 'add_extra_kses_allowed_tags' ), 10, 2 );
		$output = UM()->get_template( $template_name, $this->wall->plugin_basename, $t_args );
		add_filter( 'um_late_escaping_allowed_tags', array( $this->wall->common()->posts(), 'add_extra_kses_allowed_tags' ), 10, 2 );

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
			check_ajax_referer( $this->wall->prefix . 'wall_post_publish', 'nonce' );
		} else {
			check_ajax_referer( $this->wall->prefix . 'wall_post_edit' . $post_id, 'nonce' );
		}

		do_action( $this->wall->prefix . 'before_wall_post_publish', $post_id );

		$_post_content = wp_kses_post( wp_unslash( $_POST['_post_content'] ) ); // sanitize content with allowed html tags for posts.
		$_post_content = str_replace( '&nbsp;', ' ', $_post_content ); // replace &nbsp; to space
		$_post_content = preg_replace( '/<div[^>]*>/i', "\n", $_post_content ); // replace div to new line
		$_post_content = preg_replace( '/<\/div>/i', '', $_post_content ); // remove closing div tags
		$_post_content = preg_replace( "/[ \t]*\n[ \t]*/", "\n", $_post_content ); // remove spaces and tabs around new lines
		$_post_content = preg_replace( "/\n{2,}/", "\n", $_post_content ); // replace multiple new lines with a single one
		$_post_content = trim( $_post_content ); // remove leading and trailing whitespace
		$_post_content = preg_replace( '/<br\s*\/?>/i', "\n", $_post_content ); // remove <br> in text

		$_post_images = array();
		$_photo_key   = apply_filters( $this->wall->prefix . 'photo_post_key', '' );
		if ( ! empty( $_POST[ $_photo_key ] ) ) { // don't need to sanitize there. It's sanitized in the lower level before save to the DB.
			foreach ( $_POST[ $_photo_key ] as $post_photo ) {
				if ( ! array_key_exists( 'hash', $post_photo ) ) {
					continue;
				}
				if ( ! UM()->common()->filesystem()->is_file_author( $post_photo['hash'] ) ) {
					continue;
				}

				$_post_images[] = $post_photo;
			}
		}

		um_maybe_unset_time_limit();

		if ( 0 === $post_id ) {
			if ( empty( $_post_content ) && empty( $_post_images ) ) {
				wp_send_json_error( array( 'message' => __( 'You should type something first or add a photo.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
			}

			$post_id = $this->handle_post_insert( $_post_content, $_post_images );
			$output  = apply_filters( $this->wall->prefix . 'wall_publish_output', $this->prepare_response( $post_id ), $post_id );
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

			$attachments = get_children(
				array(
					'post_parent'    => $post_id,
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'numberposts'    => -1,
				)
			);

			if ( empty( $_post_content ) && empty( $_post_images ) && empty( $attachments ) ) {
				wp_send_json_error( array( 'message' => __( 'You should type something first or add a photo.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
			}

			$post_id = $this->handle_post_update( $post_id, $_post_content, $_post_images );
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

		wp_send_json_error( array( 'message' => __( 'Something went wrong.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
	}

	/**
	 * Prepare response after post insert/update
	 *
	 * @param int $post_id post ID
	 *
	 * @return string prepared HTML content for AJAX response
	 */
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
			'profile_id'       => um_profile_id() ? um_profile_id() : 0,
		);

		// phpcs:disable WordPress.Security.NonceVerification -- already verified here
		if ( ! empty( $_POST['_page'] ) ) {
			$t_args['page'] = sanitize_key( $_POST['_page'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification -- already verified here

		$t_args        = apply_filters( $this->wall->prefix . 'wall_prepare_response_template_args', $t_args );
		$template_name = apply_filters( $this->wall->prefix . 'wall_posts_template', 'v3/posts-loop.php' );

		return UM()->ajax()->esc_html_spaces( UM()->get_template( $template_name, $this->wall->plugin_basename, $t_args ) );
	}

	/**
	 * Handle post insert
	 *
	 * @param $_post_content
	 * @param $_post_images
	 *
	 * @return int|\WP_Error
	 */
	private function handle_post_insert( $_post_content, $_post_images ) {
		$current_user_id = get_current_user_id();
		$orig_content    = apply_filters( $this->wall->prefix . 'new_post', $_post_content );

		$args = array(
			'post_title'   => '',
			'post_type'    => $this->wall->post_type,
			'post_status'  => 'publish',
			'post_author'  => $current_user_id,
			'post_content' => '',
			'post_excerpt' => '',
			'meta_input'   => array(
				'_user_id'          => $current_user_id,
				'_likes'            => 0,
				'_comments'         => 0,
				'_action'           => 'status',
				'_original_content' => wp_slash( $orig_content ),
			),
		);

		$args    = apply_filters( $this->wall->prefix . 'insert_post_args', $args );
		$post_id = wp_insert_post( $args );
		if ( empty( $post_id ) || is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Something went wrong with post store.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		} else {
			if ( '' !== $orig_content ) {
				// Apply hashtags for the post.
				$this->wall->common()->posts()->hashtagit( $post_id, $orig_content );

				$data = array(
					'ID' => $post_id,
				);

				$converted_content = $this->prepare_post_content( $orig_content ); // prepare blocks and preview cards from the text
				$excerpt_content   = $this->shorten_string( $orig_content ); // prepare post excerpt
				if ( $excerpt_content !== $orig_content ) {
					$data['post_excerpt'] = wp_slash( $excerpt_content ); // create post excerpt
				}
				$data['post_content'] = wp_slash( $converted_content );

				$data = apply_filters( $this->wall->prefix . 'insert_prepared_post_args', $data );
				wp_update_post( $data );
			}
		}

		// Upload new images
		if ( ! empty( $_post_images ) ) {
			$this->upload_images( $_post_images, $post_id );
		}

		$published_args = apply_filters( $this->wall->prefix . 'after_wall_post_published_args', array( $post_id ) );
		do_action_ref_array( $this->wall->prefix . 'after_wall_post_published', $published_args );

		update_post_meta( $post_id, '_um_post_version', $this->wall->plugin_version );

		return $post_id;
	}

	/**
	 * Handle post update
	 *
	 * @param $post_id
	 * @param $_post_content
	 * @param $_post_images
	 *
	 * @return int|\WP_Error
	 */
	private function handle_post_update( $post_id, $_post_content, $_post_images ) {
		// Update post
		$args = array( 'ID' => $post_id );

		$orig_content        = apply_filters( $this->wall->prefix . 'edit_post', $_post_content );
		$old_data            = get_post( $post_id );
		$old_data->post_meta = get_post_meta( $post_id );

		// Compare new changed content with the saved original content. Used `$old_data->post_meta['_original_content'][0]` because cannot get all `post_meta` via get_post_meta( $post_id ) with $single marker.
		if ( $old_data->post_meta['_original_content'] !== $orig_content ) {
			$args['meta_input']['_original_content'] = wp_slash( $orig_content );
		}

		$args    = apply_filters( $this->wall->prefix . 'update_post_args', $args, $old_data );
		$post_id = wp_update_post( $args );

		if ( empty( $post_id ) || is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Something went wrong with post store.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		} else {
			if ( $old_data->post_meta['_original_content'] !== $orig_content ) {
				$this->wall->common()->posts()->hashtagit( $post_id, $orig_content ); // Update hashtags for the post.

				$data = array(
					'ID' => $post_id,
				);

				$converted_content = $this->prepare_post_content( $orig_content ); // prepare blocks and preview cards from the text
				$excerpt_content   = $this->shorten_string( $orig_content ); // prepare post excerpt

				$data['post_excerpt'] = '';
				if ( $excerpt_content !== $orig_content ) {
					$data['post_excerpt'] = wp_slash( $excerpt_content ); // create post excerpt
				}
				$data['post_content'] = wp_slash( $converted_content );

				$data = apply_filters( $this->wall->prefix . 'update_prepared_post_args', $data );
				wp_update_post( $data );
			}
		}

		// Upload new images
		if ( ! empty( $_post_images ) || get_post_meta( $post_id, '_photo', true ) ) {
			$this->upload_images( $_post_images, $post_id );
		}

		$updated_args = apply_filters( $this->wall->prefix . 'after_wall_post_updated_args', array( $post_id, $old_data ) );
		do_action_ref_array( $this->wall->prefix . 'after_wall_post_updated', $updated_args );

		update_post_meta( $post_id, '_um_post_version', $this->wall->plugin_version );

		return $post_id;
	}

	/**
	 * Prepare post content before saving
	 *
	 * @param string $safe_content safe content
	 *
	 * @return string converted content
	 */
	public function prepare_post_content( $safe_content ) {
		$converted_content = $this->handle_links( $safe_content ); // generate wp blocks with a figure tags
		$converted_content = UM()->shortcodes()->emotize( $converted_content, false ); // UM legacy emoji convert from the predefined list of emoji.

		return $converted_content;
	}

	/**
	 * Generate embed blocks from the text content
	 *
	 * @param string $raw_text original text
	 *
	 * @return string new text with embed blocks
	 */
	private function handle_links( $raw_text ) {
		// Add X.com (formerly Twitter) oEmbed provider
		wp_oembed_add_provider(
			'#https?://(www\.)?x\.com/.+#i',
			'https://publish.twitter.com/oembed',
			true
		);
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$wp_embed  = _wp_oembed_get_object();

		$attributes = apply_filters(
			$this->wall->prefix . 'make_links_clickable_attrs',
			array(
				'target' => '_blank',
				'class'  => 'um-link',
				'rel'    => 'noopener nofollow ugc',
			)
		);

		$attribute_string = '';
		foreach ( $attributes as $key => $value ) {
			$attribute_string .= esc_html( $key ) . '="' . esc_attr( $value ) . '" ';
		}

		$processed_text = preg_replace_callback(
			'#(?<!href=")(?<!src=")(https?://[^\s]+)#',
			function ( $m ) use ( $site_host, $wp_embed, $attribute_string ) {
				$url = esc_url_raw( $m[0] );

				if ( $wp_embed && is_object( $wp_embed ) ) {
					$provider    = $wp_embed->get_provider( $url );
					$oembed_data = $wp_embed->get_data( $url );

					if ( ! empty( $provider ) && ! empty( $oembed_data ) && ! is_wp_error( $provider ) && ! is_wp_error( $oembed_data ) ) {
						$url_host = wp_parse_url( $url, PHP_URL_HOST );
						if ( ! $url_host || $url_host !== $site_host ) { // Don't make embed the current website URLs.
							$data = get_object_vars( $oembed_data );

							$type_raw      = isset( $data['type'] ) ? (string) $data['type'] : 'rich';
							$provider_name = isset( $data['provider_name'] ) ? (string) $data['provider_name'] : 'unknown';
							$type          = sanitize_key( $type_raw );
							$provider_slug = sanitize_title( $provider_name );

							$block_json = wp_json_encode(
								array(
									'url'              => $url,
									'type'             => $type,
									'providerNameSlug' => $provider_slug,
									'responsive'       => true,
								)
							);

							$figure_classes = array(
								'um-activity-oembed',
								'is-type-' . $type,
								'is-provider-' . $provider_slug,
							);
							$inner_classes  = array(
								'um-activity-oembed__wrapper',
								'is-type-' . $type,
							);

							$esc_url        = esc_url( $m[0] );
							$figure_classes = esc_attr( implode( ' ', $figure_classes ) );
							$inner_classes  = esc_attr( implode( ' ', $inner_classes ) );
							// don't change this line, otherwise oembed doesn't work =).
							return <<<BLOCK
							<!-- wp:embed {$block_json} --><figure class="{$figure_classes}">
								<div class="{$inner_classes}">
									{$esc_url}
								</div>
							</figure><!-- /wp:embed -->
							BLOCK;
						}
					}
				}

				// Fetch the link preview card for the raw URL.
				$meta_card = $this->generate_link_preview_card( $url );
				if ( false !== $meta_card ) {
					// If the meta card was successfully generated, return it.
					return $meta_card;
				}

				// Otherwise, just make a link clickable if there isn't oembed or preview meta card object based on it.
				return '<a ' . $attribute_string . ' href="' . esc_url( $m[0] ) . '">' . esc_html( $m[0] ) . '</a>';
			},
			$raw_text
		);

		return $processed_text;
	}

	/**
	 * Generate link preview card HTML
	 *
	 * @param string $url URL to generate preview for
	 *
	 * @return false|string HTML of the link preview card
	 */
	private function generate_link_preview_card( $url ) {
		// Fetch the URL content
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
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return false;
		}

		libxml_use_internal_errors( true );
		$doc    = new \DOMDocument();
		$loaded = $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $body );
		libxml_clear_errors();

		if ( ! $loaded ) {
			return false;
		}

		$title        = '';
		$desc         = '';
		$img          = '';
		$image_width  = null;
		$image_height = null;

		// Extract meta tags
		$metas = $doc->getElementsByTagName( 'meta' );
		foreach ( $metas as $meta ) {
			if ( ! $meta instanceof \DOMElement ) {
				continue;
			}
			$prop = strtolower( $meta->getAttribute( 'property' ) );
			$name = strtolower( $meta->getAttribute( 'name' ) );
			$val  = trim( $meta->getAttribute( 'content' ) );
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

		// Fallback: use domain as title if no title found
		$domain = parse_url( $url, PHP_URL_HOST );
		$domain = $domain ? strtoupper( preg_replace( '~^www\.~i', '', $domain ) ) : '';

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

		ob_start();
		?>
		<figure class="um-meta-preview">
			<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $link_class ); ?>" target="_blank">
				<?php echo wp_kses( $img_tag, UM()->get_allowed_html( 'templates' ) ); ?>
				<div class="um-meta-text">
					<span class="um-meta-title">
						<?php echo $title ? esc_html( $title ) : esc_html__( 'Untitled', $this->wall->textdomain ); // phpcs:ignore WordPress.WP.I18n ?>
					</span>
					<?php if ( ! empty( $desc ) ) { ?>
						<span class="um-meta-desc"><?php echo esc_html( mb_substr( $desc, 0, 240 ) ); ?></span>
					<?php } ?>
					<?php if ( ! empty( $domain ) ) { ?>
						<span class="um-meta-domain"><?php echo esc_html( $domain ); ?></span>
					<?php } ?>
				</div>
			</a>
		</figure>
		<?php
		$content = ob_get_clean();
		return str_replace( array( "\r", "\n", "\t" ), '', $content );
	}

	/**
	 * Convert a possibly relative URL to an absolute one based on a base URL
	 *
	 * @param string $maybe URL to convert
	 * @param string $base  Base URL
	 *
	 * @return string absolute URL
	 */
	private function absolutize_url( string $maybe, string $base ): string {
		$maybe = trim( $maybe );
		if ( '' === $maybe ) {
			return '';
		}

		// protocol-relative //example.com/...
		if ( strpos( $maybe, '//' ) === 0 ) {
			$parsed = wp_parse_url( $base );
			$scheme = ! empty( $parsed['scheme'] ) ? $parsed['scheme'] : 'https';

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
		$scheme    = $bp['scheme'];
		$host      = $bp['host'];
		$port      = isset( $bp['port'] ) ? ':' . $bp['port'] : '';
		$base_path = isset( $bp['path'] ) ? $bp['path'] : '/';

		// build full path
		if ( strpos( $maybe, '/' ) === 0 ) {
			$path = $maybe;
		} else {
			$dir  = rtrim( preg_replace( '~/[^/]*$~', '/', $base_path ), '/' ) . '/';
			$path = $dir . $maybe;
		}

		// normalize path (remove ./ and ../)
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

	/**
	 * Shorten any string based on word count
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public function shorten_string( $string ) {
		$words_limit = absint( apply_filters( $this->wall->prefix . 'wall_post_excerpt_words_limit', 25 ) );
		if ( empty( $words_limit ) ) {
			return $string;
		}

		/**
		 * \p{L} matches any kind of letter from any language, \p{N} matches any kind of digit from any language,
		 * \p{Pd} matches any kind of dash or hyphen, \p{Pc} matches a punctuation character such as an underscore that connects words,
		 * \p{Sm} matches any math symbol,
		 * :/?#=@%\.& includes the characters :, ., /, =, &, ?, %, which commonly appear in URLs.
		 */
		preg_match_all( '~[\p{L}\p{N}\p{Pd}\p{Pc}\p{Pd}\p{Sm}:/?#=@%\.&]+~u', $string, $matches, PREG_OFFSET_CAPTURE );

		$str_words = array();
		foreach ( $matches[0] as $match ) {
			$str_words[ $match[1] ] = $match[0]; // $match[0] - word, $match[1] - starting position.
		}

		$positions = array_keys( $str_words );

		if ( array_key_exists( $words_limit + 1, $positions ) ) {
			$trimmed = substr( $string, 0, $positions[ $words_limit ] - 1 );
			return $this->prepare_post_content( $trimmed );
		}

		return $string;
	}

	/**
	 * Upload post images and attach them to the post
	 *
	 * @param array $post_images Array of images to upload, each item should have 'path', 'filename', and 'hash' keys.
	 * @param int   $post_id     ID of the post to attach images to.
	 */
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
		}
	}

	/**
	 * Like wall post.
	 *
	 */
	public function like_post() {
		if ( empty( $_POST['post_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$post_id = absint( $_POST['post_id'] );

		check_ajax_referer( 'um_wall_like_post' . $post_id, 'nonce' );

		if ( ! $this->wall->common()->posts()->exists( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! $this->wall->common()->user()->can_like( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to like this post.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		do_action( $this->wall->prefix . 'before_wall_post_liked', $post_id, get_current_user_id() );

		$liked = get_post_meta( $post_id, '_liked', true );
		if ( is_array( $liked ) && in_array( get_current_user_id(), $liked, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You already liked this post', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
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
		if ( empty( $_POST['post_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$post_id = absint( $_POST['post_id'] );

		check_ajax_referer( 'um_wall_unlike_post' . $post_id, 'nonce' );

		if ( ! $this->wall->common()->posts()->exists( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! $this->wall->common()->user()->can_unlike( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to unlike this post.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		do_action( $this->wall->prefix . 'before_wall_post_unliked', $post_id, get_current_user_id() );

		$liked = get_post_meta( $post_id, '_liked', true );
		if ( empty( $liked ) || ! is_array( $liked ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post data', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! in_array( get_current_user_id(), $liked, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You didn\'t like this post', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
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
	 * Report wall post
	 *
	 */
	public function report_post() {
		if ( empty( $_POST['post_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$post_id = absint( $_POST['post_id'] );

		check_ajax_referer( 'um_wall_report_post' . $post_id, 'nonce' );

		if ( ! $this->wall->common()->posts()->exists( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$user_id = get_current_user_id();

		do_action( $this->wall->prefix . 'before_wall_post_report', $post_id, $user_id );

		$users_reported = get_post_meta( $post_id, '_reported_by', true );
		if ( empty( $users_reported ) ) {
			$users_reported = array();
		}

		if ( ! isset( $users_reported[ $user_id ] ) ) {
			$users_reported[ $user_id ] = current_time( 'timestamp' );
			update_post_meta( $post_id, '_reported_by', $users_reported );
		}

		if ( ! get_post_meta( $post_id, '_reported', true ) ) {
			$option = $this->wall->prefix . 'flagged';
			$count  = absint( get_option( $option ) );
			update_option( $option, $count + 1 );
		}

		$new_r = absint( get_post_meta( $post_id, '_reported', true ) );
		update_post_meta( $post_id, '_reported', $new_r + 1 );

		do_action( $this->wall->prefix . 'wall_after_post_reported', $post_id, $user_id );
		wp_send_json_success( 'success' );
	}

	/**
	 * Unreport wall post
	 *
	 */
	public function unreport_post() {
		if ( empty( $_POST['post_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$post_id = absint( $_POST['post_id'] );

		check_ajax_referer( 'um_wall_cancel_report_post' . $post_id, 'nonce' );

		if ( ! $this->wall->common()->posts()->exists( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$user_id = get_current_user_id();

		do_action( $this->wall->prefix . 'before_wall_post_unreport', $post_id, $user_id );

		$users_reported = get_post_meta( $post_id, '_reported_by', true );
		if ( is_array( $users_reported ) && isset( $users_reported[ $user_id ] ) ) {
			unset( $users_reported[ $user_id ] );
		}

		if ( ! $users_reported ) {
			$users_reported = '';
		}

		update_post_meta( $post_id, '_reported_by', $users_reported );

		if ( get_post_meta( $post_id, '_reported', true ) ) {

			$new_r = absint( get_post_meta( $post_id, '_reported', true ) );
			--$new_r;
			if ( $new_r < 0 ) {
				$new_r = 0;
			}
			update_post_meta( $post_id, '_reported', $new_r );

			if ( 0 === $new_r ) {
				$option = $this->wall->prefix . 'flagged';
				$count  = absint( get_option( $option ) );
				update_option( $option, absint( $count - 1 ) );
			}
		}

		do_action( $this->wall->prefix . 'wall_after_post_unreported', $post_id, $user_id );
		wp_send_json_success( 'success' );
	}

	/**
	 * Load post likes via AJAX
	 */
	public function get_post_likes() {
		if ( empty( $_POST['post_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$post_id = absint( $_POST['post_id'] );

		check_ajax_referer( 'um_wall_show_likes' . $post_id, 'nonce' );

		if ( ! $this->wall->common()->posts()->exists( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( UM()->is_rate_limited( 'wall_get_post_likes' ) ) {
			wp_send_json_error( __( 'Too many requests', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}

		if ( ! $this->wall->common()->user()->can_view_likes( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to see likes.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		do_action( $this->wall->prefix . 'before_wall_post_likes_loaded', $post_id, get_current_user_id() );

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
		if ( empty( $_POST['post_id'] ) ) {
			wp_send_json_error( __( 'Wrong post ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$post_id = absint( $_POST['post_id'] );

		check_ajax_referer( 'um_wall_delete_post' . $post_id, 'nonce' );

		if ( ! $this->wall->common()->posts()->exists( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

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
		if ( empty( $_POST['post_id'] ) ) {
			wp_send_json_error( __( 'Wrong post ID.', $this->wall->textdomain ) ); // phpcs:ignore WordPress.WP.I18n
		}
		$post_id = absint( $_POST['post_id'] );

		check_ajax_referer( $this->wall->prefix . 'get_full_post_' . $post_id, 'nonce' );

		if ( ! $this->wall->common()->posts()->exists( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Wrong post ID.', $this->wall->textdomain ) ) ); // phpcs:ignore WordPress.WP.I18n
		}

		$content_raw = get_post_field( 'post_content', $post_id );

		remove_filter( 'the_content', 'prepend_attachment' );
		remove_filter( 'the_content', 'wpautop' );

		$content = apply_filters( 'the_content', $content_raw );
		$content = $this->wall->common()->posts()->linkify_hashtags_in_content( $content );
		$content = $this->wall->common()->posts()->maybe_linkify_mentions( $content, 'post', $post_id );
		$content = nl2br( $content );

		add_filter( 'the_content', 'prepend_attachment' );
		add_filter( 'the_content', 'wpautop' );

		wp_send_json_success( array( 'content' => $content ) );
	}
}
