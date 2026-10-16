<?php
/** Reject malformed upload metadata before sanitizers or filesystem access. */
require __DIR__ . '/backup-access.php';
require_once __DIR__ . '/../includes/class-abmm-admin.php';
function sanitize_file_name( string $value ) { return $value; }
function size_format( $value ) { return (string) $value; }
$admin = ( new ReflectionClass( 'ABMM_Admin' ) )->newInstanceWithoutConstructor();
$valid = array( 'name' => 'menu.json', 'size' => 100, 'error' => UPLOAD_ERR_OK, 'tmp_name' => '/not-an-upload' );
$invalid = array( null, 'invalid', array() );
foreach ( array( 'name', 'size', 'error', 'tmp_name' ) as $field ) {
	foreach ( array( array(), array( 'nested' ), null, new stdClass() ) as $value ) {
		$invalid[] = array_replace( $valid, array( $field => $value ) );
	}
	$missing = $valid;
	unset( $missing[ $field ] );
	$invalid[] = $missing;
}
foreach ( array( -1, 0, '100', 1.5, true, ABMM_Import_Export::MAX_IMPORT_BYTES + 1 ) as $size ) {
	$invalid[] = array_replace( $valid, array( 'size' => $size ) );
}
$invalid[] = array_replace( $valid, array( 'name' => 'menu.php' ) );
$invalid[] = array_replace( $valid, array( 'error' => UPLOAD_ERR_PARTIAL ) );
$invalid[] = $valid; // Valid metadata cannot make a non-uploaded local path trusted.
set_error_handler( function ( $severity, $message ) { throw new ErrorException( $message, 0, $severity ); } );
foreach ( $invalid as $file ) {
	$_FILES = array( 'import_file' => $file );
	$_POST = array( 'nonce' => 'valid-fixture-nonce' );
	$GLOBALS['abmm_test_admin'] = true;
	$before = $store->get_all();
	try {
		$admin->ajax_import_menu();
		throw new Exception( 'Import did not terminate.' );
	} catch ( ABMM_Test_Response $response ) {
		check( 400 === $response->getCode(), 'Malformed upload was not rejected.' );
	}
	check( $before === $store->get_all(), 'Rejected upload changed stored menus.' );
}
restore_error_handler();
echo "Malformed upload metadata rejected without PHP warnings or menu changes.\n";
