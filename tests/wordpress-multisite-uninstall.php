<?php
/** Destructive lifecycle regression: use only in the dedicated disposable CI network. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'ABMM_DISPOSABLE_TEST' ) || ! is_multisite() ) {
 throw new RuntimeException( 'This test requires an explicitly disposable WordPress multisite environment.' );
}
function abmm_multisite_assert( $condition, $message ) {
 if ( ! $condition ) { throw new RuntimeException( $message ); }
}
wp_set_current_user( 1 );
$network = get_network();
$site_ids = array( get_current_blog_id() );
for ( $i = 1; $i <= 2; $i++ ) {
 $was_installing = wp_installing();
 try {
  $id = wpmu_create_blog( $network->domain, trailingslashit( $network->path ) . 'abmm-qa-' . $i . '/', 'Uninstall QA ' . $i, 1 );
 } finally {
  // wpmu_create_blog() leaves installation mode enabled, changing transient storage.
  wp_installing( $was_installing );
 }
 abmm_multisite_assert( ! is_wp_error( $id ), 'Create a disposable subsite.' );
 $site_ids[] = (int) $id;
}
$original_site = get_current_blog_id();
abmm_multisite_assert( ! wp_installing() && ! wp_using_ext_object_cache(), 'Fixture must use normal database-backed transient storage.' );
$keys = array( 'abmm_menus', 'abmm_archived_menus', 'abmm_product_profiles', 'abmm_product_branding', 'abmm_plugin_version', 'abmm_import_backups', 'abmm_contextual_navigation_enabled' );
$extra_network = '1' === getenv( 'ABMM_EXTRA_NETWORK' );
$foreign_options = array();
if ( $extra_network ) {
 global $wpdb;
 // Core has site insertion APIs but no network insertion API. Create only a
 // disposable network row, invalidate its cache, then initialize through core.
 $foreign_domain = 'abmm-foreign.example.test';
 abmm_multisite_assert( false !== $wpdb->insert( $wpdb->site, array( 'domain' => $foreign_domain, 'path' => '/' ), array( '%s', '%s' ) ), 'Create a separate disposable network.' );
 $foreign_network_id = (int) $wpdb->insert_id;
 clean_network_cache( $foreign_network_id );
 abmm_multisite_assert( $foreign_network_id !== (int) $network->id && null !== get_network( $foreign_network_id ), 'Foreign network is distinct and discoverable.' );
 update_network_option( $foreign_network_id, 'site_name', 'Foreign network QA' );
 update_network_option( $foreign_network_id, 'siteurl', 'http://' . $foreign_domain . '/' );
 update_network_option( $foreign_network_id, 'admin_email', 'admin@example.test' );
 $was_installing = wp_installing();
 try {
  $foreign_site_id = wp_insert_site( array( 'domain' => $foreign_domain, 'path' => '/', 'network_id' => $foreign_network_id, 'title' => 'Foreign network site', 'user_id' => 1 ) );
 } finally { wp_installing( $was_installing ); }
 abmm_multisite_assert( ! is_wp_error( $foreign_site_id ) && wp_is_site_initialized( $foreign_site_id ), 'Initialize foreign site tables through WordPress.' );
 abmm_multisite_assert( $foreign_network_id === (int) get_site( $foreign_site_id )->network_id, 'Foreign site belongs to the separate network.' );
 update_network_option( $foreign_network_id, 'main_site', (int) $foreign_site_id );
 clean_network_cache( $foreign_network_id );
 $foreign_active = array( 'ashbi-mega-menu/ashbi-mega-menu.php' => time() );
 update_network_option( $foreign_network_id, 'active_sitewide_plugins', $foreign_active );
 update_network_option( $foreign_network_id, 'abmm_menus', 'Foreign legacy network data' );
 switch_to_blog( $foreign_site_id );
 try {
  $foreign_menu_id = ABMM_Data::instance()->create( 'Foreign network menu' );
  abmm_multisite_assert( is_string( $foreign_menu_id ), 'Create foreign site menu.' );
  foreach ( $keys as $key ) {
   if ( 'abmm_menus' !== $key ) { update_option( $key, array( 'foreign' => 'preserve' ) ); }
   $foreign_options[$key] = get_option( $key );
  }
  abmm_multisite_assert( set_transient( 'abmm_product_draft_uninstall_fixture', array( 'foreign' => 'private' ), 600 ), 'Seed foreign private recovery draft.' );
  abmm_multisite_assert( array( 'foreign' => 'private' ) === get_option( '_transient_abmm_product_draft_uninstall_fixture' ), 'Foreign draft uses its own database table.' );
 } finally { restore_current_blog(); }
 abmm_multisite_assert( (int) $network->id === get_current_network_id() && $original_site === get_current_blog_id(), 'Foreign fixture setup preserves the current network/site context.' );
}
$menu_ids = array();
foreach ( $site_ids as $site_id ) {
 switch_to_blog( $site_id );
 try {
  $menu_ids[$site_id] = ABMM_Data::instance()->create( 'Site ' . $site_id );
  abmm_multisite_assert( is_string( $menu_ids[$site_id] ), 'Site-local menu create succeeds.' );
  abmm_multisite_assert( 'Site ' . $site_id === ABMM_Data::instance()->get( $menu_ids[$site_id] )['title'], 'Site-local menu reload succeeds.' );
  $html = do_shortcode( '[ashbi_mega_menu id="' . $menu_ids[$site_id] . '"]' );
  abmm_multisite_assert( false !== strpos( $html, 'data-abmm-id=' ), 'Subsite menu renders.' );
  foreach ( $keys as $key ) {
   if ( 'abmm_menus' !== $key ) { update_option( $key, array( 'fixture' => 'private' ) ); }
  }
  set_transient( 'abmm_product_draft_uninstall_fixture', array( 'private' ), 600 );
  abmm_multisite_assert( array( 'private' ) === get_option( '_transient_abmm_product_draft_uninstall_fixture' ), 'Private recovery draft exists in this site options table before uninstall.' );
  update_option( 'abmm_qa_unrelated', 'preserve' );
  set_transient( 'abmm_qa_unrelated', 'preserve', 600 );
 } finally { restore_current_blog(); }
}
foreach ( $site_ids as $site_id ) {
 switch_to_blog( $site_id );
 try {
  $menus = ABMM_Data::instance()->get_all();
  abmm_multisite_assert( 1 === count( $menus ) && isset( $menus[$menu_ids[$site_id]] ), 'Menu collections remain isolated between sites.' );
 } finally { restore_current_blog(); }
}
update_site_option( 'abmm_menus', 'legacy network data' );
update_site_option( 'abmm_qa_unrelated', 'preserve' );
update_user_meta( 1, 'abmm_onboarding_complete', 'starter' );
update_user_meta( 1, 'abmm_qa_unrelated', 'preserve' );
require_once ABSPATH . 'wp-admin/includes/plugin.php';
deactivate_plugins( 'ashbi-mega-menu/ashbi-mega-menu.php', true, true );
foreach ( $site_ids as $site_id ) {
 switch_to_blog( $site_id );
 try { abmm_multisite_assert( ! empty( get_option( 'abmm_menus' ) ), 'Deactivation retains menus.' ); }
 finally { restore_current_blog(); }
}
uninstall_plugin( 'ashbi-mega-menu/ashbi-mega-menu.php' );
abmm_multisite_assert( $original_site === get_current_blog_id() && ! ms_is_switched(), 'Network uninstall restores the original blog context.' );
foreach ( $site_ids as $site_id ) {
 switch_to_blog( $site_id );
 try {
  foreach ( $keys as $key ) { abmm_multisite_assert( false === get_option( $key, false ), 'Uninstall removed ' . $key . ' from site ' . $site_id ); }
  abmm_multisite_assert( false === get_transient( 'abmm_product_draft_uninstall_fixture' ), 'Uninstall removed site-local private recovery drafts.' );
  abmm_multisite_assert( 'preserve' === get_option( 'abmm_qa_unrelated' ) && 'preserve' === get_transient( 'abmm_qa_unrelated' ), 'Uninstall preserved unrelated site data.' );
 } finally { restore_current_blog(); }
}
abmm_multisite_assert( false === get_site_option( 'abmm_menus', false ) && 'preserve' === get_site_option( 'abmm_qa_unrelated' ), 'Legacy network cleanup preserves unrelated network data.' );
$expected_onboarding = $extra_network ? 'starter' : '';
abmm_multisite_assert( $expected_onboarding === get_user_meta( 1, 'abmm_onboarding_complete', true ) && 'preserve' === get_user_meta( 1, 'abmm_qa_unrelated', true ), 'Shared onboarding remains when another network exists; unrelated user metadata always remains.' );
if ( $extra_network ) {
 abmm_multisite_assert( $foreign_active === get_network_option( $foreign_network_id, 'active_sitewide_plugins' ), 'Current-network uninstall preserves foreign network activation.' );
 abmm_multisite_assert( 'Foreign legacy network data' === get_network_option( $foreign_network_id, 'abmm_menus' ), 'Current-network uninstall preserves foreign network options.' );
 switch_to_blog( $foreign_site_id );
 try {
  foreach ( $foreign_options as $key => $value ) { abmm_multisite_assert( $value === get_option( $key ), 'Foreign plugin option survives: ' . $key ); }
  abmm_multisite_assert( 'Foreign network menu' === ABMM_Data::instance()->get( $foreign_menu_id )['title'], 'Foreign menu remains readable after current-network uninstall.' );
  abmm_multisite_assert( array( 'foreign' => 'private' ) === get_transient( 'abmm_product_draft_uninstall_fixture' ), 'Foreign private recovery draft survives current-network uninstall.' );
 } finally { restore_current_blog(); }
 abmm_multisite_assert( $original_site === get_current_blog_id() && ! ms_is_switched(), 'Foreign preservation checks restore the original site context.' );
}
WP_CLI::success( 'Current-network isolation, create/reload/render, deactivation and uninstall cleanup passed; extra network fixture: ' . ( $extra_network ? 'preserved' : 'not present' ) . '.' );
