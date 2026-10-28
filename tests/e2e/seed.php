<?php
/**
 * Seed one sample menu and page per browser for the Playwright tests.
 * Run only inside a disposable WordPress test installation via WP-CLI.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}
if ( ! getenv( 'ABMM_DISPOSABLE_TEST' ) ) {
	WP_CLI::error( 'Set ABMM_DISPOSABLE_TEST=1 to confirm this is a disposable site.' );
}

// Test-only locale switches for RTL and long-translation checks.
wp_mkdir_p( WPMU_PLUGIN_DIR );
copy( __DIR__ . '/mu-plugins/abmm-test-locale.php', WPMU_PLUGIN_DIR . '/abmm-test-locale.php' );

$data = ABMM_Data::instance();
foreach ( array( 'chromium', 'firefox', 'webkit' ) as $browser ) {
	$id = $data->create_from_starter();
	if ( ! is_string( $id ) ) {
		WP_CLI::error( "Could not create the {$browser} menu." );
	}
	$slug = 'e2e-' . $browser;
	$existing = get_page_by_path( $slug );
	if ( $existing ) {
		wp_delete_post( $existing->ID, true );
	}
	$page = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'E2E ' . $browser,
			'post_name'    => $slug,
			// Full width, like a real header: the starter's site name, links and
			// button do not fit a narrow content column and would switch to the
			// phone layout, which desktop tests cannot hover.
			'post_content' => '<!-- wp:group {"align":"full","layout":{"type":"default"}} --><div class="wp-block-group alignfull"><!-- wp:shortcode -->[ashbi_mega_menu id="' . $id . '"]<!-- /wp:shortcode --></div><!-- /wp:group --><!-- wp:paragraph --><p>Page content after the menu.</p><!-- /wp:paragraph -->',
		),
		true
	);
	if ( is_wp_error( $page ) ) {
		WP_CLI::error( $page->get_error_message() );
	}
	update_post_meta( $page, '_abmm_e2e_menu', $id );
	WP_CLI::log( "{$browser}: menu {$id}, page /?pagename={$slug}" );
}
// Compatibility setups (CI "compat" job). Each places the chromium menu the way that setup would.
$setup   = (string) getenv( 'ABMM_E2E_SETUP' );
$menu_id = (string) get_post_meta( (int) get_page_by_path( 'e2e-chromium' )->ID, '_abmm_e2e_menu', true );
if ( '' === $menu_id ) {
	$menu_id = (string) array_key_last( $data->get_all() );
}
if ( 'classic' === $setup ) {
	// A classic theme: the menu as a widget in a widget area, plus the shortcode page.
	switch_theme( 'twentytwentyone' );
	if ( 'twentytwentyone' !== get_stylesheet() ) {
		WP_CLI::error( 'Could not activate Twenty Twenty-One.' );
	}
	update_option( 'widget_abmm_widget', array( 2 => array( 'title' => '', 'menu_id' => $menu_id ), '_multiwidget' => 1 ) );
	$sidebars = get_option( 'sidebars_widgets', array() );
	$sidebars['sidebar-1'] = array( 'abmm_widget-2' );
	update_option( 'sidebars_widgets', $sidebars );
	WP_CLI::log( "classic: Twenty Twenty-One, widget with menu {$menu_id} in sidebar-1" );
} elseif ( 'elementor' === $setup ) {
	// Elementor: a page built with Elementor's Shortcode widget, no plugin-specific integration.
	$existing = get_page_by_path( 'e2e-elementor' );
	if ( $existing ) {
		wp_delete_post( $existing->ID, true );
	}
	$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'E2E Elementor', 'post_name' => 'e2e-elementor', 'post_content' => '' ), true );
	$elementor = array(
		array(
			'id'       => 'abmmsec',
			'elType'   => 'section',
			'settings' => array( 'layout' => 'full_width' ),
			'elements' => array(
				array(
					'id'       => 'abmmcol',
					'elType'   => 'column',
					'settings' => array( '_column_size' => 100 ),
					'elements' => array(
						array( 'id' => 'abmmsc', 'elType' => 'widget', 'widgetType' => 'shortcode', 'settings' => array( 'shortcode' => '[ashbi_mega_menu id="' . $menu_id . '"]' ) ),
					),
				),
			),
		),
	);
	update_post_meta( $page, '_elementor_edit_mode', 'builder' );
	update_post_meta( $page, '_elementor_template_type', 'wp-page' );
	update_post_meta( $page, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
	update_post_meta( $page, '_elementor_data', wp_slash( wp_json_encode( $elementor ) ) );
	WP_CLI::log( "elementor: page /?pagename=e2e-elementor with menu {$menu_id}" );
} elseif ( 'cache' === $setup ) {
	// Page cache (Cache Enabler), script optimization (Autoptimize: aggregate,
	// minify and defer) and a persistent object cache (SQLite Object Cache).
	update_option( 'autoptimize_js', 'on' );
	update_option( 'autoptimize_js_aggregate', 'on' );
	update_option( 'autoptimize_js_defer_not_aggregate', '' );
	update_option( 'autoptimize_js_defer_inline', 'on' );
	update_option( 'autoptimize_css', 'on' );
	WP_CLI::log( 'cache: Cache Enabler, Autoptimize JS/CSS aggregation, object cache ' . ( wp_using_ext_object_cache() ? 'active' : 'NOT active' ) );
}

WP_CLI::success( 'Seeded browser test menus.' );
