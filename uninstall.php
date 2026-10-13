<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Ashbi_Mega_Menu
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Capability check.
if ( ! current_user_can( 'activate_plugins' ) ) {
	return;
}

// Prevent running twice.
if ( defined( 'ABMM_UNINSTALLING' ) ) {
	return;
}
define( 'ABMM_UNINSTALLING', true );

/* ------------------------------------------------------------------
   Remove all plugin options / transients
   ------------------------------------------------------------------ */
$abmm_option_keys = array(
	'abmm_menus',
	'abmm_archived_menus',
	'abmm_product_profiles',
	'abmm_product_branding',
	'abmm_plugin_version',
	'abmm_import_backups',
	'abmm_contextual_navigation_enabled',
);

foreach ( $abmm_option_keys as $abmm_key ) {
	delete_option( $abmm_key );
	delete_site_option( $abmm_key ); // multisite
}

// Recovery drafts use unpredictable transient keys; remove every plugin-owned key.
global $wpdb;
$abmm_transient_prefix = $wpdb->esc_like( '_transient_abmm_product_draft_' ) . '%';
$abmm_recovery_options = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall must enumerate unpredictable plugin-owned transient keys once; results must not be cached.
	$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $abmm_transient_prefix ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core-owned table name.
);
foreach ( $abmm_recovery_options as $abmm_recovery_option ) {
	delete_option( $abmm_recovery_option );
	delete_option( str_replace( '_transient_', '_transient_timeout_', $abmm_recovery_option ) );
}

/* ------------------------------------------------------------------
   Remove user onboarding state
   ------------------------------------------------------------------ */
if ( function_exists( 'delete_metadata' ) ) {
	delete_metadata( 'user', 0, 'abmm_onboarding_complete', '', true );
}
