<?php
namespace WallLib\ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Init
 *
 * @package WallLib\ajax
 */
class Init {

	private $wall;

	public function __construct( $wall ) {
		$this->wall = $wall;
	}

	/**
	 * Create classes' instances where __construct isn't empty for hooks init
	 */
	public function includes() {
		$this->comments();
		$this->mentions();
		$this->posts();
	}

	/**
	 * @return Comments
	 */
	public function comments() {
		if ( empty( UM()->classes[ $this->wall->prefix . 'WallLib\ajax\comments' ] ) ) {
			UM()->classes[ $this->wall->prefix . 'WallLib\ajax\comments' ] = new Comments( $this->wall );
		}
		return UM()->classes[ $this->wall->prefix . 'WallLib\ajax\comments' ];
	}

	/**
	 * @return Mentions
	 */
	public function mentions() {
		if ( empty( UM()->classes[ $this->wall->prefix . 'WallLib\ajax\mentions' ] ) ) {
			UM()->classes[ $this->wall->prefix . 'WallLib\ajax\mentions' ] = new Mentions( $this->wall );
		}
		return UM()->classes[ $this->wall->prefix . 'WallLib\ajax\mentions' ];
	}

	/**
	 * @return Posts
	 */
	public function posts() {
		if ( empty( UM()->classes[ $this->wall->prefix . 'WallLib\ajax\posts' ] ) ) {
			UM()->classes[ $this->wall->prefix . 'WallLib\ajax\posts' ] = new Posts( $this->wall );
		}
		return UM()->classes[ $this->wall->prefix . 'WallLib\ajax\posts' ];
	}
}
