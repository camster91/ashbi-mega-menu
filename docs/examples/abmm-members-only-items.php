<?php
/**
 * Plugin Name: Ashbi Mega Menu – members-only items (example)
 * Description: Example add-on. Hides top-level items marked "members only" from logged-out visitors.
 * Requires Plugins: ashbi-mega-menu
 * License: GPL-2.0-or-later
 *
 * Shows the two halves of a conditional-visibility add-on:
 * 1. Storing its own data in a menu, under extensions.<slug>, via abmm_sanitize_menu.
 * 2. Applying it per request, via abmm_menu_data. Nothing is changed in storage.
 *
 * Data shape: $menu['extensions']['acme-members-only'] = array( '<item id>' => true, ... ).
 */

defined( 'ABSPATH' ) || exit;

const ACME_MEMBERS_ONLY = 'acme-members-only';

// Keep (and sanitize) this add-on's data when menus are saved, imported or previewed.
add_filter(
	'abmm_sanitize_menu',
	function ( $menu, $raw ) {
		$flags = $raw['extensions'][ ACME_MEMBERS_ONLY ] ?? null;
		if ( is_array( $flags ) ) {
			$clean = array();
			foreach ( $flags as $item_id => $on ) {
				if ( $on ) {
					$clean[ sanitize_text_field( (string) $item_id ) ] = true;
				}
			}
			$menu['extensions'][ ACME_MEMBERS_ONLY ] = $clean;
		}
		return $menu;
	},
	10,
	2
);

// Remove flagged items for logged-out visitors when the menu is rendered.
add_filter(
	'abmm_menu_data',
	function ( $menu ) {
		$flags = $menu['extensions'][ ACME_MEMBERS_ONLY ] ?? array();
		if ( ! $flags || is_user_logged_in() ) {
			return $menu;
		}
		$menu['items'] = array_values(
			array_filter(
				$menu['items'],
				function ( $item ) use ( $flags ) {
					return empty( $flags[ $item['id'] ?? '' ] );
				}
			)
		);
		return $menu;
	}
);

// Page caches serve one copy to everyone: tell them a menu with members-only
// items varies by login state, or exclude those pages from the cache.
