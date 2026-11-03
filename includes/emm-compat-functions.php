<?php
/**
 * Easy Mega Menu's theme helpers, answered by Ashbi.
 *
 * Loaded only when the "Handle [easy_mega_menu] shortcodes" setting is on and
 * Easy Mega Menu is not active, so a theme that calls emm_render_menu() keeps
 * its header. These keep Easy Mega Menu's original names on purpose.
 *
 * @package Ashbi_Mega_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emm_get_menu' ) ) {
	/**
	 * Return a menu's HTML by ID (Easy Mega Menu compatible).
	 *
	 * @param string $menu_id Menu ID.
	 * @param array  $args    context, presentation, review_post_id.
	 * @return string
	 */
	function emm_get_menu( $menu_id, $args = array() ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- compatibility with Easy Mega Menu themes.
		return ABMM_Frontend::instance()->render( $menu_id, is_array( $args ) ? $args : array() );
	}
}

if ( ! function_exists( 'emm_render_menu' ) ) {
	/**
	 * Print a menu by ID (Easy Mega Menu compatible).
	 *
	 * @param string $menu_id Menu ID.
	 * @param array  $args    context, presentation, review_post_id.
	 */
	function emm_render_menu( $menu_id, $args = array() ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- compatibility with Easy Mega Menu themes.
		echo emm_get_menu( $menu_id, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the renderer.
	}
}
