<?php
/** Exercise actual backup handlers with unauthorized, invalid-nonce and authorized requests. */
require __DIR__ . '/menu-save-cache.php';
class ABMM_Test_Response extends Exception {
 public $response;
 public function __construct( $response, $status ) { parent::__construct( '', $status ); $this->response = $response; }
}
function check_ajax_referer( $action, $field ) {
 if ( 'abmm_admin' !== $action || 'valid-fixture-nonce' !== ( $_POST[ $field ] ?? '' ) ) {
  throw new ABMM_Test_Response( array( 'success' => false ), 403 );
 }
}
function current_user_can( $capability ) { return 'manage_options' === $capability && ! empty( $GLOBALS['abmm_test_admin'] ); }
function wp_send_json_error( $data, $status = 200 ) { throw new ABMM_Test_Response( array( 'success' => false, 'data' => $data ), $status ); }
function wp_send_json_success( $data ) { throw new ABMM_Test_Response( array( 'success' => true, 'data' => $data ), 200 ); }
function backup_request( $handler, $admin, $nonce ) {
 $GLOBALS['abmm_test_admin'] = $admin;
 $_POST = array( 'nonce' => $nonce );
 try { ABMM_Import_Export::instance()->$handler(); }
 catch ( ABMM_Test_Response $response ) { return $response; }
 throw new Exception( 'Backup request did not terminate with a response.' );
}
foreach ( array( 'ajax_list_backups', 'ajax_export_backup', 'ajax_restore_backup' ) as $handler ) {
 $before = $store->get_all();
 $denied = backup_request( $handler, false, 'valid-fixture-nonce' );
 check( 403 === $denied->getCode() && ! $denied->response['success'], 'Non-admin backup request allowed.' );
 $denied = backup_request( $handler, true, 'invalid' );
 check( 403 === $denied->getCode() && ! $denied->response['success'], 'Invalid-nonce backup request allowed.' );
 check( $before === $store->get_all(), 'Denied backup request changed menus.' );
}
$allowed = backup_request( 'ajax_list_backups', true, 'valid-fixture-nonce' );
check( $allowed->response['success'] && isset( $allowed->response['data']['revision'] ), 'Authorized backup list failed.' );
check( ! isset( $allowed->response['data']['backups'][0]['menus'] ), 'Backup listing returned full private menu content.' );
echo "Backup authorization and nonce regressions passed.\n";
