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
	public $plugin_url;

	/**
	 * Plugin version
	 *
	 * @param string
	 */
	public $plugin_version;

	/**
	 * Plugin path
	 *
	 * @param string
	 */
	public $plugin_path;

	/**
	 * Plugin basename
	 *
	 * @param string
	 */
	public $plugin_basename;

	/**
	 * Plugin prefix
	 *
	 * @param string
	 */
	public $prefix;

	/**
	 * Plugin text domain
	 *
	 * @param string
	 */
	public $textdomain;

	/**
	 * Plugin classes
	 *
	 * @param array
	 */
	public $classes = array();


	/**
	 * namespace
	 *
	 * @param string
	 */
	public $namespace = __NAMESPACE__;

	/**
	 * WallLib constructor.
	 *
	 * @param string $file
	 */
	public function __construct( $data ) {
		spl_autoload_register( array( $this, 'wall__autoloader' ) );

		UM()->classes['WallLib\Init'] = $this;

		$this->plugin_url      = $data['plugin_url'];
		$this->plugin_version  = $data['plugin_version'];
		$this->plugin_path     = $data['plugin_path'];
		$this->prefix          = $data['prefix'];
		$this->plugin_basename = $data['plugin_basename'];
		$this->textdomain      = $data['textdomain'];
		$this->classes         = $data['classes'];

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

			// todo: check slashes in strpos '\\WallLib\\frontend\\'
			if ( strpos( $class, 'WallLib\\frontend\\' ) !== false ) {
				$class = implode( '\\', $array );
				$slash = DIRECTORY_SEPARATOR;
				$path = str_replace(
					array( strtolower( __NAMESPACE__ ), '_', '\\' ),
					array( '', '-', $slash ),
					$class );

				$full_path =  __DIR__ . $slash . 'includes' . $path . '.php';
			} elseif ( strpos( $class, 'WallLib\\ajax\\' ) !== false ) {
				$class = implode( '\\', $array );
				$slash = DIRECTORY_SEPARATOR;
				$path = str_replace(
					array( strtolower( __NAMESPACE__ ), '_', '\\' ),
					array( '', '-', $slash ),
					$class );

				$full_path =  __DIR__ . $slash . 'includes' . $path . '.php';
			} elseif ( strpos( $class, 'WallLib\\common\\' ) !== false ) {
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
		$this->common()->includes();
		if ( UM()->is_request( 'ajax' ) ) {
			$this->ajax()->includes();
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
			UM()->classes['WallLib\frontend\Init'] = new frontend\Init( $this );
		}
		return UM()->classes['WallLib\frontend\Init'];
	}

	/**
	 *
	 * @return ajax\Init
	 */
	public function ajax() {
		if (empty(UM()->classes['WallLib\ajax\Init'])) {
			UM()->classes['WallLib\ajax\Init'] = new ajax\Init( $this );
		}
		return UM()->classes['WallLib\ajax\Init'];
	}

	/**
	 *
	 * @return common\Init
	 */
	public function common() {
		if (empty(UM()->classes['WallLib\common\Init'])) {
			UM()->classes['WallLib\common\Init'] = new common\Init( $this );
		}
		return UM()->classes['WallLib\common\Init'];
	}

	public function get_plugin_info() {
		return array(
			'name'    => $this->plugin_url,
			'version' => $this->plugin_version,
			'path'    => $this->plugin_path,
			'prefix'  => $this->prefix,
		);
	}
}
