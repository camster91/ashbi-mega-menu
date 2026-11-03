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
	 * The repeated header class makes a widget's Style settings outrank the
	 * menu's own Design-tab fonts (ABMM_Style), since the widget is the more
	 * specific choice for this placement.
	 *
	 * @param string[] $parts  Class suffixes, e.g. 'mega__col-label'.
	 * @param string[] $states Optional state suffixes such as ':hover'.
	 * @return string
	 */
	public static function selector( $parts, $states = array( '' ) ) {
		$selectors = array();
		foreach ( array( 'abmm', 'emm' ) as $p ) {
			foreach ( $parts as $part ) {
				foreach ( (array) $states as $state ) {
					$selectors[] = '{{WRAPPER}} .' . $p . '-header.' . $p . '-header.' . $p . '-header .' . $p . '-' . str_replace( '{p}', $p, $part ) . $state;
				}
			}
		}
		return implode( ', ', $selectors );
	}
}
