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
 $id = wpmu_create_blog( $network->domain, trailingslashit( $network->path ) . 'abmm-qa-' . $i . '/', 'Uninstall QA ' . $i, 1 );
 abmm_multisite_assert( ! is_wp_error( $id ), 'Create a disposable subsite.' );
 $site_ids[] = (int) $id;
}
$original_site = get_current_blog_id();
$keys = array( 'abmm_menus', 'abmm_archived_menus', 'abmm_product_profiles', 'abmm_product_branding', 'abmm_plugin_version', 'abmm_import_backups', 'abmm_contextual_navigation_enabled' );
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
abmm_multisite_assert( '' === get_user_meta( 1, 'abmm_onboarding_complete', true ) && 'preserve' === get_user_meta( 1, 'abmm_qa_unrelated', true ), 'Onboarding cleanup preserves unrelated user metadata.' );
WP_CLI::success( 'Multisite isolation, create/reload/render, deactivation retention and network-wide uninstall cleanup passed on three real sites.' );
