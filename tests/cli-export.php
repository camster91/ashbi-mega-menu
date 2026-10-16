<?php
/** CLI exports must remain portable without writing private data into the web root. */
require __DIR__ . '/import-roundtrip.php';
$store->replace_all( array( 'export-test' => array( 'title' => 'CLI export', 'items' => array(), 'settings' => ABMM_Data::default_settings() ) ) );
$cli = new ABMM_WP_CLI_Command();
$target = sys_get_temp_dir() . '/abmm-export-must-not-write.json';
foreach ( array( $target, '../menu.json', 'php://output', array( 'menu.json' ), '' ) as $file ) {
	$failed = false;
	try { $cli->export( array(), array( 'file' => $file ) ); }
	catch ( Exception $error ) { $failed = true; }
	verify_roundtrip( $failed, 'A legacy export path was not explicitly rejected.' );
}
verify_roundtrip( ! file_exists( $target ), 'Export wrote a file.' );
ob_start();
$cli->export( array(), array() );
$json = ob_get_clean();
$payload = json_decode( $json, true );
verify_roundtrip( JSON_ERROR_NONE === json_last_error() && empty( $exporter->validate_payload( $payload ) ), 'CLI stdout is not portable JSON.' );
verify_roundtrip( array_keys( $payload['menus'] ) === array( 'export-test' ), 'CLI export lost a menu.' );
ob_start();
$cli->export( array(), array( 'id' => 'export-test' ) );
$single = json_decode( ob_get_clean(), true );
verify_roundtrip( $single['menus'] === $payload['menus'], 'Selected-menu CLI export changed content.' );
echo "CLI portable stdout export and unsafe path rejection passed.\n";
