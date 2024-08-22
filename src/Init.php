<?php
namespace WallLib;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Init {

	/**
	 * Plugin url
	 *
	 * @param string
	 */
	private $plugin_url;

	/**
	 * Plugin version
	 *
	 * @param string
	 */
	private $plugin_version;

	/**
	 * Plugin path
	 *
	 * @param string
	 */
	private $plugin_path;

	/**
	 * Plugin prefix
	 *
	 * @param string
	 */
	private $plugin_prefix;

	/**
	 * WallLib constructor.
	 *
	 * @param string $file
	 */
	public function __construct( $data ) {
		$this->plugin_url     = $data['plugin_url'];
		$this->plugin_version = $data['plugin_version'];
		$this->plugin_path    = $data['plugin_path'];
		$this->plugin_prefix  = $data['plugin_prefix'];

		add_shortcode( 'ultimatemember_post_type', array( $this, 'ultimatemember_post_type' ) );
	}

	public function get_plugin_info() {
		return array(
			'name'    => $this->plugin_url,
			'version' => $this->plugin_version,
			'path'    => $this->plugin_path,
			'prefix'  => $this->plugin_prefix,
		);
	}

	public function ultimatemember_post_type( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'post_type' => 'post',
			),
			$atts
		);

		$query = new \WP_Query(
			array(
				'post_type'      => $atts['post_type'],
				'posts_per_page' => 5,
			)
		);

		if ($query->have_posts()) {
			$output = '<ul>';
			while ($query->have_posts()) {
				$query->the_post();
				$output .= '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
			}
			$output .= '</ul>';
			wp_reset_postdata();
		} else {
			$output = 'No posts found.';
		}

		return $output;
	}
}
