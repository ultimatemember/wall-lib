<?php
namespace Dev\UM_Activity\WallLib\ajax;
//namespace Dev\UM_Groups\WallLib\ajax;
//namespace WallLib\ajax;

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
		$this->posts();
		$this->comments();
		$this->mentions();
	}

	/**
	 * @return Posts
	 */
	public function posts() {
		if ( empty( UM()->classes['WallLib\ajax\posts'] ) ) {
			UM()->classes['WallLib\ajax\posts'] = new Posts( $this->wall );
		}
		return UM()->classes['WallLib\ajax\posts'];
	}

	/**
	 * @return Comments
	 */
	public function comments() {
		if ( empty( UM()->classes['WallLib\ajax\comments'] ) ) {
			UM()->classes['WallLib\ajax\comments'] = new Comments( $this->wall );
		}
		return UM()->classes['WallLib\ajax\comments'];
	}

	/**
	 * @return Mentions
	 */
	public function mentions() {
		if ( empty( UM()->classes['WallLib\ajax\mentions'] ) ) {
			UM()->classes['WallLib\ajax\mentions'] = new Mentions( $this->wall );
		}
		return UM()->classes['WallLib\ajax\mentions'];
	}
}
