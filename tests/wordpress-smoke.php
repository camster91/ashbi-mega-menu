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
$lock_name = 'abmm_menus_save_' . md5( $wpdb->options . '|' . ABMM_OPTION_KEY );
abmm_test_assert( '1' === (string) $other_db->get_var( $other_db->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name ) ), 'Second database connection acquires collection lock.' );
$locked = $data->create( 'Must not be written while locked' );
abmm_test_assert( is_wp_error( $locked ) && 'abmm_save_lock_timeout' === $locked->get_error_code(), 'Create respects a competing connection lock.' );
$other_db->get_var( $other_db->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
$other_db->close();
$copy_id = $data->duplicate( $id );
abmm_test_assert( is_string( $copy_id ), 'Duplicate succeeds without nested lock deadlock.' );
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
wp_set_current_user( 0 );
$response = rest_do_request( '/ashbi-mega-menu/v1/menus' );
abmm_test_assert( in_array( $response->get_status(), array( 401, 403 ), true ), 'Anonymous REST access is denied.' );
WP_CLI::success( 'Create, locked save, reload, stale revision, reactivation, icons, render, export, collection contention, import backups, recovery and REST protection passed.' );
