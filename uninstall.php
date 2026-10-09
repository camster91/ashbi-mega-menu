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
$option_keys = array(
	'abmm_menus',
	'abmm_archived_menus',
	'abmm_product_profiles',
	'abmm_product_branding',
	'abmm_plugin_version',
	'abmm_import_backups',
);

foreach ( $option_keys as $key ) {
	delete_option( $key );
	delete_site_option( $key ); // multisite
}

/* ------------------------------------------------------------------
   Remove user onboarding state
   ------------------------------------------------------------------ */
if ( function_exists( 'delete_metadata' ) ) {
	delete_metadata( 'user', 0, 'abmm_onboarding_complete', '', true );
}
