<?php
/**
 * Plugin Name: Ashbi Mega Menu – purge page cache on save (example)
 * Description: Example add-on. Clears a page cache whenever a menu is saved or removed.
 * Requires Plugins: ashbi-mega-menu
 * License: GPL-2.0-or-later
 *
 * abmm_menu_saved and abmm_menu_deleted cover every way a menu changes:
 * the editor, import, WP-CLI, REST, duplicate, delete and archive.
 * They run inside the plugin's save lock, so do not save menus from them.
 */

defined( 'ABSPATH' ) || exit;

function acme_abmm_purge_cache() {
	// Replace with the cache plugin's own purge function.
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
	do_action( 'acme_purge_page_cache' );
}
add_action( 'abmm_menu_saved', 'acme_abmm_purge_cache' );
add_action( 'abmm_menu_deleted', 'acme_abmm_purge_cache' );
