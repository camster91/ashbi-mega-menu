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
			'post_content' => '<!-- wp:shortcode -->[ashbi_mega_menu id="' . $id . '"]<!-- /wp:shortcode --><!-- wp:paragraph --><p>Page content after the menu.</p><!-- /wp:paragraph -->',
		),
		true
	);
	if ( is_wp_error( $page ) ) {
		WP_CLI::error( $page->get_error_message() );
	}
	WP_CLI::log( "{$browser}: menu {$id}, page /?pagename={$slug}" );
}
WP_CLI::success( 'Seeded browser test menus.' );
