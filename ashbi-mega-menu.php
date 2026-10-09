<?php
/**
 * Plugin Name: Ashbi Mega Menu
 * Plugin URI:  https://github.com/camster91/ashbi-mega-menu
 * Description: Create beautiful mega menus like corporate platforms menus — managed visually from the admin panel. No coding required.
 * Version:     1.0.0
 * Author:      Cameron Ashley
 * Author URI:  https://github.com/camster91
 * License:     GPL-2.0-or-later
 * Text Domain: ashbi-mega-menu
 * Requires at least: 6.6
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ABMM_VERSION', '1.0.0' );
define( 'ABMM_PLUGIN_FILE', __FILE__ );
define( 'ABMM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABMM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ABMM_OPTION_KEY', 'abmm_menus' );
define( 'ABMM_VERSION_OPTION', 'abmm_plugin_version' );
define( 'ABMM_ARCHIVE_OPTION', 'abmm_archived_menus' );
define( 'ABMM_ONBOARDING_META', 'abmm_onboarding_complete' );

define( 'ABMM_PUBLIC_DISTRIBUTION', true );

require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-data.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-icons.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-admin.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-product-profiles.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-product-branding.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-page-context.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-frontend.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-shortcode.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-block.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-widget.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-import-export.php';

/* ==================================================================
   Bootstrap
   ================================================================== */

/** Translated strings shared by frontend and editor search. */
function abmm_menu_search_strings() {
	return array(
		'menu' => __( 'Menu', 'ashbi-mega-menu' ),
		'label' => __( 'Find a product or page', 'ashbi-mega-menu' ),
		'placeholder' => __( 'Search this menu', 'ashbi-mega-menu' ),
		'clear' => __( 'Clear', 'ashbi-mega-menu' ),
		'clearLabel' => __( 'Clear menu search', 'ashbi-mega-menu' ),
		'resultsLabel' => __( 'Menu search results', 'ashbi-mega-menu' ),
		/* translators: %d is the number of matching menu links. */
		'oneResult' => __( '%d result', 'ashbi-mega-menu' ),
		/* translators: %d is the number of matching menu links. */
		'manyResults' => __( '%d results', 'ashbi-mega-menu' ),
		'empty' => __( 'No matches. Try another word or clear your search.', 'ashbi-mega-menu' ),
	);
}

function abmm_init() {
	ABMM_Data::instance();
	ABMM_Icons::instance();
	ABMM_Import_Export::instance();
	ABMM_Product_Profiles::instance();
	ABMM_Product_Branding::instance();
	ABMM_Page_Context::instance();
	abmm_maybe_upgrade();

	if ( is_admin() ) {
		ABMM_Admin::instance();
	}

	ABMM_Frontend::instance();
	ABMM_Shortcode::instance();
	ABMM_Block::instance();
}
add_action( 'plugins_loaded', 'abmm_init' );


/* ==================================================================
   Activation
   ================================================================== */

function abmm_activate() {
	$existing = get_option( ABMM_OPTION_KEY );

	if ( false === $existing ) {
		// First-run onboarding intentionally lets the administrator choose blank or starter content.
		update_option( ABMM_OPTION_KEY, array() );
	} else {
		abmm_maybe_upgrade();
	}

	update_option( ABMM_VERSION_OPTION, ABMM_VERSION );
}
register_activation_hook( __FILE__, 'abmm_activate' );

/* ==================================================================
   Deactivation
   ================================================================== */

function abmm_deactivate() {
	// Stored menus remain available after reactivation.
}
register_deactivation_hook( __FILE__, 'abmm_deactivate' );

/* ==================================================================
   Upgrade handler
   ================================================================== */

function abmm_maybe_upgrade() {
	$stored = get_option( ABMM_VERSION_OPTION );

	if ( ABMM_VERSION === $stored ) {
		return;
	}

	/*
	 * Starter content belongs to activation only. Upgrades must never recreate a
	 * starter that an administrator intentionally edited or deleted.
	 *
	 * Future migrations should operate on documented fields, keep the original
	 * option intact until the transformed value is valid, and remain idempotent.
	 */

	update_option( ABMM_VERSION_OPTION, ABMM_VERSION );
}
