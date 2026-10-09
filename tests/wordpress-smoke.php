<?php
/** Run only inside a disposable WordPress test installation via WP-CLI. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
 exit;
}
function abmm_test_assert( $condition, $message ) {
 if ( ! $condition ) { WP_CLI::error( $message ); }
}
abmm_test_assert( class_exists( 'ABMM_Data' ), 'Plugin must be active.' );
$data = ABMM_Data::instance();
$id = $data->create( 'Smoke test' );
abmm_test_assert( is_string( $id ), 'Create a menu.' );
$menu = $data->get( $id );
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
wp_set_current_user( 0 );
$response = rest_do_request( '/ashbi-mega-menu/v1/menus' );
abmm_test_assert( in_array( $response->get_status(), array( 401, 403 ), true ), 'Anonymous REST access is denied.' );
WP_CLI::success( 'Create, locked save, reload, stale revision, reactivation, icons, render, export and REST protection passed.' );
