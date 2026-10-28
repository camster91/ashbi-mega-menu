<?php
/**
 * Multisite: each site keeps its own menus, editing and import/export stay
 * within a site, placements render the current site's menu, and the setup
 * guide is per site. Run in the disposable CI network before the uninstall test.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'ABMM_DISPOSABLE_TEST' ) || ! is_multisite() ) {
	throw new RuntimeException( 'This test requires an explicitly disposable WordPress multisite environment.' );
}
function abmm_ms_assert( $condition, $message ) {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
}
wp_set_current_user( 1 );
$network = get_network();
$main    = get_current_blog_id();
$was     = wp_installing();
try {
	$second = wpmu_create_blog( $network->domain, trailingslashit( $network->path ) . 'abmm-ms-qa/', 'Multisite QA', 1 );
} finally {
	wp_installing( $was );
}
abmm_ms_assert( ! is_wp_error( $second ), 'Create a disposable subsite.' );

$data   = ABMM_Data::instance();
$porter = ABMM_Import_Export::instance();
try {
	$main_before = $data->get_all();
	$main_id     = $data->create( 'Main site menu' );
	abmm_ms_assert( is_string( $main_id ), 'Create a menu on the main site.' );

	switch_to_blog( $second );
	abmm_ms_assert( ! isset( $data->get_all()[ $main_id ] ), 'A new site does not see the main site\'s menus.' );
	$second_id = $data->create( 'Subsite menu' );
	$menu      = $data->get( $second_id );
	$menu['items'] = array( array( 'id' => 'home', 'label' => 'Subsite home', 'type' => 'link', 'url' => home_url( '/' ) ) );
	abmm_ms_assert( true === $data->save( $second_id, $menu ), 'Edit and save a menu on the subsite.' );
	$html = do_shortcode( '[ashbi_mega_menu id="' . $second_id . '"]' );
	abmm_ms_assert( false !== strpos( $html, 'Subsite home' ), 'The subsite renders its own menu.' );
	abmm_ms_assert( '' === do_shortcode( '[ashbi_mega_menu id="' . $main_id . '"]' ), 'A placement cannot render another site\'s menu.' );
	$export = $porter->export_json( array( $second_id ) );
	update_user_option( 1, ABMM_Admin::SETUP_GUIDE_META, array( 'dismissed' => true, 'verified' => true ) );
	restore_current_blog();

	abmm_ms_assert( ! isset( $data->get_all()[ $second_id ] ), 'The main site does not see the subsite\'s menus.' );
	abmm_ms_assert( 'Main site menu' === $data->get( $main_id )['title'], 'Saving on the subsite left the main site unchanged.' );
	$result = $porter->import_json( $export, 'copy' );
	abmm_ms_assert( ! is_wp_error( $result ), 'Import a subsite export on the main site.' );
	$copies = array_filter( $data->get_all(), function ( $m ) { return 'Subsite home' === ( $m['items'][0]['label'] ?? '' ); } );
	abmm_ms_assert( 1 === count( $copies ), 'Import creates an independent copy on the main site.' );
	abmm_ms_assert( ! get_user_option( ABMM_Admin::SETUP_GUIDE_META, 1 ), 'The setup guide state is per site.' );

	switch_to_blog( $second );
	abmm_ms_assert( 1 === count( $data->get_all() ), 'Importing on the main site did not touch the subsite.' );
	restore_current_blog();
	abmm_ms_assert( true === $data->replace_all( $main_before ), 'Restore the main site\'s menus.' );
} finally {
	if ( ms_is_switched() ) {
		restore_current_blog();
	}
	wp_delete_site( $second );
}
WP_CLI::success( 'Multisite: per-site menus, editing, placement, import/export and setup guide isolation passed.' );
