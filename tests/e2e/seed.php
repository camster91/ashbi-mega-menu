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
	WP_CLI::log( "{$browser}: menu {$id}, page /?pagename={$slug}" );
}
WP_CLI::success( 'Seeded browser test menus.' );
