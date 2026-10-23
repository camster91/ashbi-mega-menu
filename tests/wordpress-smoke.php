<?php
/** Run only inside a disposable WordPress test installation via WP-CLI. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
 exit;
}
global $wpdb;
function abmm_test_assert( $condition, $message ) {
 if ( ! $condition ) { WP_CLI::error( $message ); }
}
abmm_test_assert( class_exists( 'ABMM_Data' ), 'Plugin must be active.' );
$data = ABMM_Data::instance();

$current_method = new ReflectionMethod( ABMM_Frontend::instance(), 'is_current_url' );
$current_method->setAccessible( true );
$saved_uri = $_SERVER['REQUEST_URI'] ?? null;
$_SERVER['REQUEST_URI'] = '/?page_id=24';
abmm_test_assert( ! $current_method->invoke( ABMM_Frontend::instance(), home_url( '/' ) ), 'A home link is not current on a plain-permalink page.' );
abmm_test_assert( $current_method->invoke( ABMM_Frontend::instance(), home_url( '/?page_id=24' ) ), 'A plain-permalink page link is current on that page.' );
$_SERVER['REQUEST_URI'] = '/?utm_source=mail';
abmm_test_assert( $current_method->invoke( ABMM_Frontend::instance(), home_url( '/' ) ), 'Tracking parameters do not stop the home link matching.' );
abmm_test_assert( ! $current_method->invoke( ABMM_Frontend::instance(), '#abmm-sample' ), 'Template placeholders are never current.' );
if ( null === $saved_uri ) { unset( $_SERVER['REQUEST_URI'] ); } else { $_SERVER['REQUEST_URI'] = $saved_uri; }

$clamped = $data->sanitize_menu( array(
	'title'    => 'Numbers',
	'items'    => array(),
	'brand'    => array( 'logo_height' => '-999' ),
	'settings' => array( 'icon_size' => '-999', 'icon_radius' => '-999', 'header_z_index' => '-999', 'panel_width' => '-999', 'sidebar_width' => '-999', 'border_radius' => '-999' ),
) );
$cs = $clamped['settings'];
abmm_test_assert( 12 === $cs['icon_size'] && 0 === $cs['icon_radius'] && 0 === $cs['header_z_index'], 'Negative icon and z-index values clamp to the minimum.' );
abmm_test_assert( 640 === $cs['panel_width'] && 180 === $cs['sidebar_width'] && 0 === $cs['border_radius'], 'Negative widths and radius clamp to the minimum.' );
abmm_test_assert( 16 === $clamped['brand']['logo_height'], 'Negative logo height clamps to the minimum.' );
$featured = $data->sanitize_menu( array(
	'title' => 'Featured',
	'items' => array(
		array( 'label' => 'Old', 'type' => 'mega', 'mega_style' => 'platforms', 'categories' => array() ),
		array( 'label' => 'New', 'type' => 'mega', 'mega_style' => 'features', 'links' => array(), 'featured' => array( 'show' => true, 'title' => '<b>Launch</b> week', 'text' => 'Short text', 'label' => 'Read', 'url' => 'javascript:alert(1)', 'image_url' => 'https://example.com/a.png' ) ),
	),
) );
abmm_test_assert( false === $featured['items'][0]['featured']['show'] && '' === $featured['items'][0]['featured']['title'], 'A mega item without a featured card gets an empty, hidden card.' );
abmm_test_assert( 'Launch week' === $featured['items'][1]['featured']['title'] && '' === $featured['items'][1]['featured']['url'], 'Featured card text is sanitised and unsafe URLs are removed.' );
abmm_test_assert( 'https://example.com/a.png' === $featured['items'][1]['featured']['image_url'], 'A safe featured image URL is kept.' );
$featured_again = $data->sanitize_menu( $featured );
abmm_test_assert( $featured['items'][1]['featured'] === $featured_again['items'][1]['featured'], 'A featured card round-trips through sanitising unchanged.' );
$id = $data->create( 'Smoke test' );
abmm_test_assert( is_string( $id ), 'Create a menu.' );
$menu = $data->get( $id );
abmm_test_assert( empty( $menu['cta']['show'] ) && '' === $menu['cta']['label'] && '' === $menu['cta']['url'], 'A blank menu has no visible CTA or placeholder destination.' );
$revision = $data->revision( $menu );
$menu['title'] = 'Updated smoke test';
$saved = $data->save_if_revision( $id, $menu, $revision );
abmm_test_assert( ! is_wp_error( $saved ), 'MySQL advisory lock and save must succeed.' );
wp_cache_delete( ABMM_OPTION_KEY, 'options' );
abmm_test_assert( 'Updated smoke test' === $data->get( $id )['title'], 'Saved data reloads.' );
$stale = $data->save_if_revision( $id, $menu, $revision );
abmm_test_assert( is_wp_error( $stale ), 'A stale revision must be rejected.' );
$before = get_option( ABMM_OPTION_KEY );
abmm_activate();
abmm_test_assert( $before === get_option( ABMM_OPTION_KEY ), 'Reactivation must preserve menus.' );
abmm_test_assert( count( ABMM_Icons::instance()->all() ) === 40, 'All licensed icons load.' );
$html = do_shortcode( '[ashbi_mega_menu id="' . $id . '"]' );
abmm_test_assert( false !== strpos( $html, 'abmm-' ), 'Shortcode renders navigation.' );
$exporter = ABMM_Import_Export::instance();
$export = $exporter->export_single_menu( $id );
abmm_test_assert( ! is_wp_error( $export ) && empty( $exporter->validate_payload( $export ) ), 'Export produces a valid portable payload.' );
// Exercise the actual CLI class with a real portable file, including its size limit.
$cli_file = wp_tempnam( 'abmm-roundtrip.json' );
abmm_test_assert( false !== file_put_contents( $cli_file, wp_json_encode( $export ) ), 'CLI fixture file written.' );
$cli = new ABMM_WP_CLI_Command();
$cli->import( array(), array( 'file' => $cli_file, 'dry-run' => true ) );
$cli->import( array(), array( 'file' => $cli_file, 'mode' => 'merge' ) );
unlink( $cli_file );
abmm_test_assert( $data->get( $id ) === $menu, 'CLI roundtrip preserves menu.' );
// Every path touching the shared collection must serialize against the same lock.
$other_db = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$other_db->set_prefix( $wpdb->prefix );
$held = ( time() + 60 ) . '|other-request';
abmm_test_assert( 1 === (int) $other_db->query( $other_db->prepare( "INSERT INTO {$other_db->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )", ABMM_Data::LOCK_OPTION, $held ) ), 'Second database connection takes the collection lock.' );
$locked = $data->create( 'Must not be written while locked' );
abmm_test_assert( is_wp_error( $locked ) && 'abmm_save_lock_timeout' === $locked->get_error_code(), 'Create respects a competing connection lock.' );
// A lock left by a request that died must expire instead of blocking saves forever.
$other_db->query( $other_db->prepare( "UPDATE {$other_db->options} SET option_value = %s WHERE option_name = %s", ( time() - 1 ) . '|crashed-request', ABMM_Data::LOCK_OPTION ) );
$recovered = $data->create( 'Saved after an expired lock' );
abmm_test_assert( is_string( $recovered ), 'An expired lock is cleared and the save succeeds.' );
abmm_test_assert( null === $other_db->get_var( $other_db->prepare( "SELECT option_value FROM {$other_db->options} WHERE option_name = %s", ABMM_Data::LOCK_OPTION ) ), 'The lock row is removed after the save.' );
$data->delete( $recovered );
$other_db->close();
$copy_id = $data->duplicate( $id );
abmm_test_assert( is_string( $copy_id ), 'Duplicate succeeds without nested lock deadlock.' );
$second_copy_id = $data->duplicate( $id );
abmm_test_assert( is_string( $second_copy_id ) && $data->get( $copy_id )['title'] !== $data->get( $second_copy_id )['title'], 'Repeated duplicates get distinct names.' );
$data->delete( $second_copy_id );
abmm_test_assert( true === $data->archive( $copy_id ) && null === $data->get( $copy_id ), 'Archive removes active copy.' );
abmm_test_assert( true === $data->restore( $copy_id ) && null !== $data->get( $copy_id ), 'Archived copy restores.' );
$collection_before_import = $data->get_all();
$result = $exporter->import_json( $export, 'copy' );
abmm_test_assert( ! is_wp_error( $result ) && count( $data->get_all() ) === count( $collection_before_import ) + 1, 'Copy import preserves collection.' );
$backup = $exporter->get_backups()[0];
abmm_test_assert( $backup['menus'] === $collection_before_import, 'Import backup captures state inside lock.' );
abmm_test_assert( is_wp_error( $exporter->restore_backup( $backup['id'], 'stale' ) ), 'Recovery rejects a stale collection revision.' );
$restored = $exporter->restore_backup( $backup['id'], $data->revision( $data->get_all() ) );
abmm_test_assert( true === $restored && $data->get_all() === $collection_before_import, 'Backup recovery restores exact collection.' );
// Fail the backup write via WordPress option filter: importing must never proceed.
$fail_backup_write = function ( $value, $old ) { return $old; };
add_filter( 'pre_update_option_abmm_import_backups', $fail_backup_write, 10, 2 );
$failed = $exporter->import_json( $export, 'replace' );
remove_filter( 'pre_update_option_abmm_import_backups', $fail_backup_write, 10 );
abmm_test_assert( is_wp_error( $failed ) && $data->get_all() === $collection_before_import, 'Failed recovery point aborts import.' );
// Status notices must be signed for the current administrator session.
wp_set_current_user( 1 );
$notice_method = new ReflectionMethod( 'ABMM_Product_Branding', 'notice_state' );
$branding = ABMM_Product_Branding::instance();
$original_get = $_GET;
$_GET = array( 'updated' => '1', 'error' => 'alt' );
abmm_test_assert( array( 'updated' => false, 'error' => '' ) === $notice_method->invoke( $branding ), 'Unsigned notices are ignored.' );
$_GET['_abmm_notice'] = 'invalid';
abmm_test_assert( array( 'updated' => false, 'error' => '' ) === $notice_method->invoke( $branding ), 'Invalid notice nonce is ignored.' );
$_GET['_abmm_notice'] = wp_create_nonce( 'abmm_branding_notice' );
abmm_test_assert( array( 'updated' => true, 'error' => 'alt' ) === $notice_method->invoke( $branding ), 'Signed admin notices are preserved.' );
$_GET['error'] = array( 'alt' );
$_GET['updated'] = array( '1' );
abmm_test_assert( array( 'updated' => false, 'error' => '' ) === $notice_method->invoke( $branding ), 'Structured notice values are ignored.' );
$_GET['_abmm_notice'] = array( 'invalid' );
abmm_test_assert( array( 'updated' => false, 'error' => '' ) === $notice_method->invoke( $branding ), 'Structured nonce is ignored.' );
$_GET = array( 'updated' => '1', '_abmm_notice' => wp_create_nonce( 'abmm_branding_notice' ) );
wp_set_current_user( 0 );
abmm_test_assert( array( 'updated' => false, 'error' => '' ) === $notice_method->invoke( $branding ), 'Anonymous notices are ignored.' );
$_GET = $original_get;
$response = rest_do_request( '/ashbi-mega-menu/v1/menus' );
abmm_test_assert( in_array( $response->get_status(), array( 401, 403 ), true ), 'Anonymous REST access is denied.' );
WP_CLI::success( 'Create, locked save, reload, stale revision, reactivation, icons, render, export, collection contention, import backups, recovery, signed notices and REST protection passed.' );
