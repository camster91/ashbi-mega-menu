<?php
/** Recoverability and numeric-ID placement tests in real WordPress. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }
function abmm_import_assert( $condition, $message ) { if ( ! $condition ) { WP_CLI::error( $message ); } }
$data = ABMM_Data::instance(); $porter = ABMM_Import_Export::instance();
$original = $data->get_all();
$menu = $data->sanitize_menu( array( 'title' => 'Roundtrip QA', 'items' => array( array( 'id' => 'home', 'type' => 'link', 'label' => 'Home', 'url' => '/' ) ) ) );
$menus = array( 'first-menu' => $menu, 0 => $menu, 123 => $menu );
for ( $i = 1; $i <= 51; $i++ ) { $menus[ 'roundtrip_' . $i ] = $menu; }
abmm_import_assert( true === $data->replace_all( $menus ), 'Seed numeric IDs and 51+ menu collection.' );
$export = $porter->export_json();
abmm_import_assert( is_array( $export ) && empty( $porter->validate_payload( $export ) ), 'Complete 54-menu export is accepted by importer.' );
$file = wp_tempnam( 'abmm-recovery.json' );
file_put_contents( $file, wp_json_encode( $export, JSON_PRETTY_PRINT ) );
$cli = new ABMM_WP_CLI_Command(); $cli->import( array(), array( 'file' => $file, 'mode' => 'replace' ) ); unlink( $file );
abmm_import_assert( array_keys( $menus ) === array_keys( $data->get_all() ), 'CLI recovery preserves every menu ID.' );
$merge = $porter->import_json( array( 'menus' => array( 456 => $menu ) ), 'merge' );
abmm_import_assert( ! is_wp_error( $merge ) && isset( $data->get_all()[123], $data->get_all()[456], $data->get_all()[0] ), 'Numeric keys preserved in merge.' );
$shortcode = do_shortcode( '[ashbi_mega_menu id="123"]' );
abmm_import_assert( false !== strpos( $shortcode, 'data-abmm-id="123"' ), 'Numeric placement remains reachable.' );
$zero = do_shortcode( '[ashbi_mega_menu id="0"]' );
abmm_import_assert( false !== strpos( $zero, 'data-abmm-id="0"' ), 'Zero ID does not render first menu fallback.' );
abmm_import_assert( true === $data->replace_all( $original ), 'Restore disposable fixture menus.' );
WP_CLI::success( '54-menu portable export/CLI recovery, numeric merge IDs and shortcode placements passed.' );
