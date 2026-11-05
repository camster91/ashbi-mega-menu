<?php
/**
 * Plugin Name: Ashbi Mega Menu
 * Plugin URI:  https://github.com/camster91/ashbi-mega-menu
 * Description: Build responsive navigation with a visual editor, local previews and portable exports.
 * Version:     1.2.9
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

define( 'ABMM_VERSION', '1.2.9' );
define( 'ABMM_PLUGIN_FILE', __FILE__ );
define( 'ABMM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABMM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ABMM_OPTION_KEY', 'abmm_menus' );
define( 'ABMM_VERSION_OPTION', 'abmm_plugin_version' );
define( 'ABMM_DELETE_DATA_OPTION', 'abmm_delete_data_on_uninstall' );
define( 'ABMM_ARCHIVE_OPTION', 'abmm_archived_menus' );
define( 'ABMM_ONBOARDING_META', 'abmm_onboarding_complete' );
define( 'ABMM_CONTEXTUAL_NAV_OPTION', 'abmm_contextual_navigation_enabled' );

define( 'ABMM_PUBLIC_DISTRIBUTION', true );

/** Whether the optional contextual navigation module is enabled. */
function abmm_contextual_navigation_enabled() {
	$stored = get_option( ABMM_CONTEXTUAL_NAV_OPTION, null );
	if ( null !== $stored ) {
		return '1' === (string) $stored;
	}

	// Preserve existing installations that already created contextual data.
	return ! empty( get_option( 'abmm_product_profiles', array() ) ) || ! empty( get_option( 'abmm_product_branding', array() ) );
}

require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-data.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-option-store.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-icons.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-admin.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-product-profiles.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-product-branding.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-page-context.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-frontend.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-style.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-elementor.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-classic.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-classic-icons.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-emm-migration.php';
require_once ABMM_PLUGIN_DIR . 'includes/class-abmm-cache.php';
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
		'label' => __( 'Find a link or page', 'ashbi-mega-menu' ),
		'placeholder' => __( 'Search this menu', 'ashbi-mega-menu' ),
		'clear' => __( 'Clear', 'ashbi-mega-menu' ),
		'clearLabel' => __( 'Clear menu search', 'ashbi-mega-menu' ),
		'resultsLabel' => __( 'Menu search results', 'ashbi-mega-menu' ),
		/* translators: %d is the number of matching menu links. */
		'oneResult' => __( '%d result', 'ashbi-mega-menu' ),
		/* translators: %d is the number of matching menu links. */
		'manyResults' => __( '%d results', 'ashbi-mega-menu' ),
		'empty' => __( 'No matches. Try another word or clear your search.', 'ashbi-mega-menu' ),
		'openMenu' => __( 'Open menu', 'ashbi-mega-menu' ),
		'closeMenu' => __( 'Close menu', 'ashbi-mega-menu' ),
		/* translators: %s: where the Back button returns to, such as a section name. */
		'backTo' => __( 'Back to %s', 'ashbi-mega-menu' ),
		'backToCategories' => __( 'categories', 'ashbi-mega-menu' ),
		'backToMenu' => __( 'menu', 'ashbi-mega-menu' ),
	);
}

/**
 * Forget where menus are placed. Saves can come from anywhere, such as the block
 * editor's REST requests, so this is hooked on every request.
 *
 * @return void
 */
function abmm_clear_placement_caches() {
	delete_transient( 'abmm_menu_reference_summaries' );
	delete_transient( 'abmm_emm_menus_in_use' );
	delete_transient( 'abmm_emm_shortcode_in_use' );
}

/**
 * Widgets are stored in options, and they can place a menu too.
 *
 * @param string $option Option name.
 * @return void
 */
function abmm_clear_placement_caches_for_option( $option ) {
	if ( 0 === strpos( (string) $option, 'widget_' ) ) {
		abmm_clear_placement_caches();
	}
}

function abmm_init() {
	add_action( 'save_post', 'abmm_clear_placement_caches', 10, 0 );
	add_action( 'deleted_post', 'abmm_clear_placement_caches', 10, 0 );
	add_action( 'elementor/document/after_save', 'abmm_clear_placement_caches', 10, 0 );
	add_action( 'added_option', 'abmm_clear_placement_caches_for_option', 10, 1 );
	add_action( 'updated_option', 'abmm_clear_placement_caches_for_option', 10, 1 );

	ABMM_Data::instance();
	ABMM_Icons::instance();
	ABMM_Import_Export::instance();
	if ( abmm_contextual_navigation_enabled() ) {
		ABMM_Product_Profiles::instance();
		ABMM_Product_Branding::instance();
		ABMM_Page_Context::instance();
	}
	abmm_maybe_upgrade();

	if ( is_admin() ) {
		ABMM_Admin::instance();
	}

	ABMM_Frontend::instance();
	ABMM_Classic::instance();
	ABMM_EMM_Migration::instance();
	ABMM_Cache::instance();
	ABMM_Elementor::init();
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
		// Atomic first creation cannot overwrite a collection created by another request.
		add_option( ABMM_OPTION_KEY, array() );
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
	ABMM_EMM_Migration::instance()->upgrade();

	update_option( ABMM_VERSION_OPTION, ABMM_VERSION );
}
