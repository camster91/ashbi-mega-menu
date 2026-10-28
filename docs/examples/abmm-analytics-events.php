<?php
/**
 * Plugin Name: Ashbi Mega Menu – analytics events (example)
 * Description: Example add-on. Forwards menu open, close and drawer events to an analytics tool the site already uses.
 * Requires Plugins: ashbi-mega-menu
 * License: GPL-2.0-or-later
 *
 * The core plugin sends nothing anywhere. It dispatches DOM events that an
 * add-on may forward, with the site owner's consent rules applied:
 *   abmm:open   { menuId, itemId, label, layout }
 *   abmm:close  { menuId, itemId, label }
 *   abmm:drawer { menuId, open }
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_register_script( 'acme-abmm-analytics', '', array(), '1.0.0', true );
		wp_enqueue_script( 'acme-abmm-analytics' );
		wp_add_inline_script(
			'acme-abmm-analytics',
			<<<'JS'
['abmm:open', 'abmm:drawer'].forEach(function (name) {
	document.addEventListener(name, function (event) {
		// Replace with the site's own analytics call, e.g. window.gtag or window.plausible.
		if (typeof window.acmeTrack === 'function') window.acmeTrack(name, event.detail);
	});
});
JS
		);
	}
);
