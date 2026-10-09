<?php
/**
 * Simulate two requests that loaded the same menu before either save lock ran.
 */

define( 'ABSPATH', __DIR__ . '/wordpress/' );
define( 'ABMM_OPTION_KEY', 'abmm_menus' );

class WP_Error {
	private $code;

	public function __construct( $code ) {
		$this->code = $code;
	}

	public function get_error_code() {
		return $this->code;
	}
}

class ABMM_Test_DB {
	public $options = 'wp_options';

	public function prepare( $query ) {
		return $query;
	}

	public function get_var() {
		return '1';
	}
}

function __( $text ) {
	return $text;
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function get_option( $key, $default = false ) {
	global $database_options, $cached_options;
	if ( array_key_exists( $key, $cached_options ) ) {
		return $cached_options[ $key ];
	}
	$cached_options[ $key ] = $database_options[ $key ] ?? $default;
	return $cached_options[ $key ];
}

function update_option( $key, $value ) {
	global $database_options, $cached_options;
	$database_options[ $key ] = $value;
	$cached_options[ $key ] = $value;
	return true;
}

function wp_cache_delete( $key, $group = '' ) {
	global $cached_options;
	if ( 'options' === $group && ( ABMM_OPTION_KEY === $key || 'alloptions' === $key ) ) {
		unset( $cached_options[ ABMM_OPTION_KEY ] );
	}
	return true;
}

require_once __DIR__ . '/../includes/class-abmm-data.php';

class ABMM_Test_Data extends ABMM_Data {
	public function sanitize_menu( $data ) {
		return $data;
	}
}

$wpdb = new ABMM_Test_DB();
$store = new ABMM_Test_Data();
$old_menu = array( 'title' => 'Old revision' );
$new_menu = array( 'title' => 'Another editor saved first' );
$database_options = array( ABMM_OPTION_KEY => array( 'menu_demo' => $new_menu ) );
$cached_options = array( ABMM_OPTION_KEY => array( 'menu_demo' => $old_menu ) );

$result = $store->save_if_revision( 'menu_demo', array( 'title' => 'Stale draft' ), $store->revision( $old_menu ) );
if ( ! $result instanceof WP_Error || 'abmm_menu_conflict' !== $result->get_error_code() || $new_menu !== $database_options[ ABMM_OPTION_KEY ]['menu_demo'] ) {
	fwrite( STDERR, "A stale option cache allowed a locked save to overwrite a newer revision.\n" );
	exit( 1 );
}

echo "Menu save cache regression passed.\n";
