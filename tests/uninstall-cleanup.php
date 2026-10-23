<?php
/** Exercise the actual uninstall file against isolated per-site data stores. */
define( 'WP_UNINSTALL_PLUGIN', 'ashbi-mega-menu/ashbi-mega-menu.php' );
$mode = $argv[1] ?? 'multisite';
$site_count = in_array( $mode, array( 'multisite', 'multi-network', 'failure' ), true ) ? 205 : 1;
$current_site = 1;
$switch_stack = array();
$site_options = array();
$network_options = array( 'abmm_menus' => 'legacy', 'unrelated' => 'keep' );
$user_metadata = array( 'abmm_onboarding_complete' => 'starter', 'unrelated' => 'keep' );
$queries = 0;
foreach ( range( 1, $site_count ) as $site_id ) {
 $site_options[$site_id] = array(
  'abmm_menus' => array( 'private' ), 'abmm_archived_menus' => array(),
  'abmm_product_profiles' => array(), 'abmm_product_branding' => array(),
  'abmm_plugin_version' => '1.0.4', 'abmm_import_backups' => array(),
  'abmm_contextual_navigation_enabled' => '1', 'widget_abmm_widget' => array( 2 => array( 'menu_id' => 'private' ) ),
  '_transient_abmm_product_draft_random' => 'private draft',
  '_transient_timeout_abmm_product_draft_random' => 123,
  'unrelated' => 'keep', '_transient_other_plugin' => 'keep',
 );
}
$site_networks = array_fill_keys( range( 1, $site_count ), 17 );
if ( 'multi-network' === $mode ) {
 foreach ( range( 206, 208 ) as $foreign_id ) {
  $site_options[$foreign_id] = $site_options[1];
  $site_networks[$foreign_id] = 29;
 }
}
$before = $site_options;
function current_user_can( $capability ) { return 'activate_plugins' === $capability && 'denied' !== $GLOBALS['mode']; }
function is_multisite() { return in_array( $GLOBALS['mode'], array( 'multisite', 'multi-network', 'failure' ), true ); }
function get_sites( $args ) {
 ++$GLOBALS['queries'];
 if ( 'ids' !== $args['fields'] || $args['number'] > 100 ) { throw new Exception( 'Site enumeration must be bounded and return IDs only.' ); }
 $ids = array_keys( array_filter( $GLOBALS['site_networks'], static function ( $network_id ) use ( $args ) { return empty( $args['network_id'] ) || $network_id === $args['network_id']; } ) );
 return array_slice( $ids, $args['offset'] ?? 0, $args['number'] );
}
function get_current_network_id() { return 17; }
function get_networks( $args ) {
 $ids = 'multi-network' === $GLOBALS['mode'] ? array( 17, 29 ) : array( 17 );
 return array_slice( array_values( array_diff( $ids, $args['network__not_in'] ?? array() ) ), 0, $args['number'] );
}
function switch_to_blog( $id ) {
 $GLOBALS['switch_stack'][] = $GLOBALS['current_site'];
 $GLOBALS['current_site'] = $id;
}
function restore_current_blog() { $GLOBALS['current_site'] = array_pop( $GLOBALS['switch_stack'] ); }
function delete_option( $key ) { unset( $GLOBALS['site_options'][$GLOBALS['current_site']][$key] ); }
function delete_site_option( $key ) { unset( $GLOBALS['network_options'][$key] ); }
function delete_metadata( $type, $id, $key, $value, $all ) { unset( $GLOBALS['user_metadata'][$key] ); }
class ABMM_Uninstall_Test_DB {
 public $options = 'wp_options';
 public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
 public function prepare( $query, $value ) { return $query; }
 public function get_col( $query ) {
  if ( 'failure' === $GLOBALS['mode'] && 2 === $GLOBALS['current_site'] ) { throw new RuntimeException( 'Simulated cleanup failure' ); }
  return array_values( array_filter( array_keys( $GLOBALS['site_options'][$GLOBALS['current_site']] ), static function ( $key ) { return 0 === strpos( $key, '_transient_abmm_product_draft_' ); } ) );
 }
}
$wpdb = new ABMM_Uninstall_Test_DB();
try { require __DIR__ . '/../uninstall.php'; }
catch ( RuntimeException $error ) {
 if ( 'failure' !== $mode || 'Simulated cleanup failure' !== $error->getMessage() ) { throw $error; }
}
if ( 1 !== $current_site || ! empty( $switch_stack ) ) { throw new Exception( 'Uninstall did not restore the original site context.' ); }
if ( 'failure' === $mode ) {
 if ( ! isset( $user_metadata['abmm_onboarding_complete'], $network_options['abmm_menus'] ) ) { throw new Exception( 'Failed cleanup continued with shared metadata removal.' ); }
} elseif ( 'denied' === $mode ) {
 if ( $before !== $site_options || ! isset( $user_metadata['abmm_onboarding_complete'], $network_options['abmm_menus'] ) ) { throw new Exception( 'Denied uninstall changed data.' ); }
} else {
 foreach ( $site_options as $site_id => $options ) {
  if ( 29 === $site_networks[$site_id] ) {
   if ( $before[$site_id] !== $options ) { throw new Exception( 'Uninstall changed another network site ' . $site_id ); }
   continue;
  }
  if ( array( 'unrelated' => 'keep', '_transient_other_plugin' => 'keep' ) !== $options ) { throw new Exception( 'Plugin data remains or unrelated data changed on site ' . $site_id ); }
 }
 if ( ( 'multi-network' === $mode ? 'starter' !== ( $user_metadata['abmm_onboarding_complete'] ?? '' ) : isset( $user_metadata['abmm_onboarding_complete'] ) ) || isset( $network_options['abmm_menus'] ) || 'keep' !== $user_metadata['unrelated'] || 'keep' !== $network_options['unrelated'] ) { throw new Exception( 'Shared metadata cleanup failed.' ); }
 if ( in_array( $mode, array( 'multisite', 'multi-network' ), true ) && $queries < 3 ) { throw new Exception( 'Pagination boundary was not exercised.' ); }
}
echo "Uninstall {$mode}: per-site cleanup, isolation and context checks passed.\n";
