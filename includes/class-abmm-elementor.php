<?php
/**
 * Elementor integration: an "Ashbi Mega Menu" widget.
 *
 * Loaded only when Elementor is active. Shortcodes, the block and the
 * WordPress widget keep working unchanged.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABMM_Elementor {

	/**
	 * Register the widget once Elementor has loaded.
	 */
	public static function init() {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets Widgets manager.
	 */
	public static function register_widget( $widgets ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-elementor-widget.php';
		$widgets->register( new ABMM_Elementor_Widget() );
	}

	/**
	 * Selectors for one part of the menu in both looks (Ashbi and Classic).
	 *
	 * @param string[] $parts Class suffixes, e.g. 'mega__col-label'.
	 * @param string   $state Optional state suffix such as ':hover'.
	 * @return string
	 */
	public static function selector( $parts, $state = '' ) {
		$selectors = array();
		foreach ( array( 'abmm', 'emm' ) as $p ) {
			foreach ( $parts as $part ) {
				$selectors[] = '{{WRAPPER}} .' . $p . '-' . str_replace( '{p}', $p, $part ) . $state;
			}
		}
		return implode( ', ', $selectors );
	}
}
