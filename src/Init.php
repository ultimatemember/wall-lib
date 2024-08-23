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
		spl_autoload_register( array( $this, 'wall__autoloader' ) );

		$this->plugin_url     = $data['plugin_url'];
		$this->plugin_version = $data['plugin_version'];
		$this->plugin_path    = $data['plugin_path'];
		$this->plugin_prefix  = $data['plugin_prefix'];

		$this->includes();

		add_shortcode( 'ultimatemember_post_type', array( $this, 'ultimatemember_post_type' ) );
	}

	/**
	 * Autoload UM classes handler
	 *
	 * @since 2.0
	 *
	 * @param $class
	 */
	function wall__autoloader( $class ) {
		if ( strpos( $class, 'WallLib' ) !== false ) {

			$array = explode( '\\', strtolower( $class ) );
			$array[ count( $array ) - 1 ] = 'class-'. end( $array );

			if ( strpos( $class, '\\WallLib\\frontend\\' ) !== false ) {
				$class = implode( '\\', $array );
				$slash = DIRECTORY_SEPARATOR;
				$path = str_replace(
					array( strtolower( __NAMESPACE__ ), '_', '\\' ),
					array( '', '-', $slash ),
					$class );

				$full_path =  __DIR__ . $slash . 'includes' . $path . '.php';
			}

			if( isset( $full_path ) && file_exists( $full_path ) ) {
				include_once $full_path;
			}
		}
	}

	public function includes() {

		if ( UM()->is_request( 'ajax' ) ) {

		} elseif ( UM()->is_request( 'frontend' ) ) {
			$this->frontend()->includes();
		}
	}

	/**
	 *
	 * @return frontend\Init
	 */
	public function frontend() {
		if (empty(UM()->classes['WallLib\frontend\Init'])) {
			UM()->classes['WallLib\frontend\Init'] = new frontend\Init();
		}
		return UM()->classes['WallLib\frontend\Init'];
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
