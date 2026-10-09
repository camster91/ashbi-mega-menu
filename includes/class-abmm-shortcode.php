<?php
/**
 * Shortcode + theme helper.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Shortcode {

	/**
	 * @var ABMM_Shortcode|null
	 */
	private static $instance = null;

	/**
	 * @return ABMM_Shortcode
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'ashbi_mega_menu', array( $this, 'shortcode' ) );
	}

	/**
	 * [ashbi_mega_menu id="menu_demo" context="page" presentation="unified"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'           => '',
				'context'      => '',
				'presentation' => '',
			),
			$atts,
			'ashbi_mega_menu'
		);

		if ( empty( $atts['id'] ) ) {
			$menus = ABMM_Data::instance()->get_all();
			if ( empty( $menus ) ) {
				return '';
			}
			$atts['id'] = array_key_first( $menus );
		}

		return ABMM_Frontend::instance()->render(
			$atts['id'],
			array(
				'context'      => sanitize_key( $atts['context'] ),
				'presentation' => sanitize_key( $atts['presentation'] ),
			)
		);
	}
}

/**
 * Theme helper: return a mega menu HTML string by ID.
 *
 * @param string $menu_id Menu ID.
 * @param array  $args    Optional args.
 * @return string
 */
function abmm_get_menu( $menu_id, $args = array() ) {
	return ABMM_Frontend::instance()->render( $menu_id, $args );
}

/**
 * Theme helper: echo a mega menu by ID.
 *
 * @param string $menu_id Menu ID.
 * @param array  $args    Optional args.
 */
function abmm_render_menu( $menu_id, $args = array() ) {
	echo abmm_get_menu( $menu_id, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
