<?php
/** Run security regressions only in a disposable, active WordPress installation. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

function abmm_review_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

class ABMM_Review_Ajax_Stop extends RuntimeException {}

/** Capture the real wp_send_json() response without allowing wp_die() to exit. */
function abmm_review_ajax( $method ) {
	$handler = function () {
		return function () { throw new ABMM_Review_Ajax_Stop(); };
	};
	add_filter( 'wp_die_ajax_handler', $handler );
	ob_start();
	try {
		ABMM_Admin::instance()->$method();
		throw new RuntimeException( 'AJAX handler did not terminate with a response.' );
	} catch ( ABMM_Review_Ajax_Stop $stop ) {
		$body = ob_get_contents();
	} finally {
		ob_end_clean();
		remove_filter( 'wp_die_ajax_handler', $handler );
	}
	$response = json_decode( $body, true );
	abmm_review_assert( JSON_ERROR_NONE === json_last_error() && is_array( $response ), 'AJAX response must be valid JSON.' );
	return $response;
}

$store       = ABMM_Data::instance();
$porter      = ABMM_Import_Export::instance();
$original    = $store->get_all();
$old_user    = get_current_user_id();
$old_post    = $_POST;
$old_request = $_REQUEST;
$old_files   = $_FILES;
$missing     = new stdClass();
$old_backups = get_option( 'abmm_import_backups', $missing );
$admins      = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
abmm_review_assert( ! empty( $admins ), 'Disposable WordPress needs an administrator.' );

try {
	wp_set_current_user( $admins[0]->ID );
	$malicious = array(
		'title' => 'Security QA <script>alert(1)</script>',
		'brand' => array( 'show' => true, 'title' => 'Brand " onmouseover="alert(1)', 'url' => 'javascript:alert(1)' ),
		'items' => array(
			array( 'id' => 'plain', 'label' => 'Home <img src=x onerror=alert(1)>', 'url' => 'javascript:alert(1)', 'target' => '_blank', 'rel' => 'opener noopener', 'class' => 'safe " onclick="alert(1)' ),
			array( 'id' => 'mega', 'type' => 'mega', 'label' => 'Products', 'categories' => array(
				array( 'title' => 'Category <script>alert(1)</script>', 'icon_color' => array( 'bad' ), 'groups' => array(
					array( 'title' => 'Group', 'links' => array(
						array( 'label' => 'Safe link', 'url' => '/safe?x=1&y=2', 'description' => '<svg onload=alert(1)>Description</svg>', 'icon_url' => 'javascript:alert(1)', 'icon_size' => array( 99 ), 'rel' => array( 'bad' ), 'class' => array( 'bad' ) ),
					) ),
				) ),
			) ),
		),
		'settings' => array( 'accent' => array( 'bad' ), 'panel_width' => array( 999999 ), 'layout' => array( 'bad' ), 'presentation' => array( 'bad' ) ),
	);
	$clean = $store->sanitize_menu( $malicious );
	abmm_review_assert( '' === $clean['brand']['url'] && '' === $clean['items'][0]['url'], 'Script URL schemes are removed.' );
	abmm_review_assert( false === strpos( $clean['items'][0]['label'], '<' ), 'HTML labels are stripped.' );
	abmm_review_assert( 'noopener' === $clean['items'][0]['rel'], 'Unsafe relationship tokens are discarded and blank targets retain noopener.' );
	abmm_review_assert( 'sidebar-left' === $clean['settings']['layout'] && '#1a73e8' === $clean['settings']['accent'], 'Malformed nested settings use safe defaults.' );
	$payload = array( 'format' => 'ashbi-mega-menu', 'format_version' => 1, 'menus' => array( 'review-security' => $malicious ) );
	$result = $porter->import_json( $payload, 'merge' );
	abmm_review_assert( ! is_wp_error( $result ) && $clean === $store->get( 'review-security' ), 'Actual importer persists only sanitized menu fields.' );
	$html = do_shortcode( '[ashbi_mega_menu id="review-security"]' );
	abmm_review_assert( false !== strpos( $html, 'data-abmm-id="review-security"' ) && false !== strpos( $html, 'Safe link' ), 'Sanitized mega menu still renders nested navigation.' );
	abmm_review_assert( false === strpos( $html, '<script' ) && false === strpos( $html, 'javascript:' ) && false === strpos( $html, '<img src=x' ), 'Imported executable markup and URL schemes do not reach menu output.' );
	abmm_review_assert( false !== strpos( $html, '&quot; onmouseover=&quot;' ), 'Quotes in displayed brand text are escaped at rendering.' );
	abmm_review_assert( false !== strpos( $html, '/safe?x=1&#038;y=2' ), 'Nested destinations receive URL-context escaping.' );
	$export = $porter->export_single_menu( 'review-security' );
	abmm_review_assert( is_array( $export ) && empty( $porter->validate_payload( $export ) ), 'Sanitized export remains portable.' );
	$roundtrip = json_decode( wp_json_encode( $export ), true );
	$result = $porter->import_json( $roundtrip, 'merge' );
	abmm_review_assert( ! is_wp_error( $result ) && $clean === $store->get( 'review-security' ), 'JSON export/import roundtrip preserves the sanitized menu.' );

	$before_invalid = $store->get_all();
	foreach ( array( '{invalid', 'null', '"scalar"', str_repeat( '[', 40 ) . '0' . str_repeat( ']', 40 ) ) as $raw ) {
		$request = new WP_REST_Request( 'POST', '/ashbi-mega-menu/v1/menus/import' );
		$request->set_body( $raw );
		$request->set_param( 'mode', 'merge' );
		$response = $porter->rest_import_menus( $request );
		abmm_review_assert( is_wp_error( $response ), 'REST importer rejects malformed, scalar or excessively nested JSON.' );
	}
	abmm_review_assert( $before_invalid === $store->get_all(), 'Rejected JSON does not mutate menus.' );

	if ( ! defined( 'DOING_AJAX' ) ) {
		define( 'DOING_AJAX', true );
	}
	$nonce = wp_create_nonce( 'abmm_admin' );
	$_POST = array( 'nonce' => $nonce, 'menu_id' => 'review-security', 'menu_data' => '{invalid' );
	$_REQUEST = $_POST;
	$response = abmm_review_ajax( 'ajax_save_menu' );
	abmm_review_assert( false === $response['success'], 'Real admin save handler rejects malformed JSON.' );
	$upload = array( 'name' => 'menu.json', 'size' => 10, 'error' => UPLOAD_ERR_OK, 'tmp_name' => '/tmp/not-a-real-http-upload' );
	$bad_uploads = array( 'not-an-upload', array() );
	foreach ( array( 'name', 'size', 'error', 'tmp_name' ) as $field ) {
		$bad = $upload;
		$bad[ $field ] = array( $upload[ $field ] );
		$bad_uploads[] = $bad;
	}
	$bad_uploads[] = array_merge( $upload, array( 'size' => -10 ) );
	$bad_uploads[] = array_merge( $upload, array( 'size' => ABMM_Import_Export::MAX_IMPORT_BYTES + 1 ) );
	$bad_uploads[] = $upload;
	foreach ( $bad_uploads as $bad ) {
		$_POST = array( 'nonce' => $nonce, 'mode' => 'merge' );
		$_REQUEST = $_POST;
		$_FILES = array( 'import_file' => $bad );
		$response = abmm_review_ajax( 'ajax_import_menu' );
		abmm_review_assert( false === $response['success'], 'Real upload handler rejects malformed metadata or an unverified temporary file.' );
	}
	abmm_review_assert( $before_invalid === $store->get_all(), 'Rejected uploads leave menus unchanged.' );

	// Launch the actual command so WP_CLI::line() writes to captured stdout.
	$stdout = WP_CLI::runcommand( 'abmm export --id=review-security', array( 'return' => 'stdout', 'launch' => true ) );
	$cli_export = json_decode( $stdout, true );
	abmm_review_assert( JSON_ERROR_NONE === json_last_error() && is_array( $cli_export ), 'CLI stdout contains JSON only, without success prose.' );
	abmm_review_assert( empty( $porter->validate_payload( $cli_export ) ) && $clean === $cli_export['menus']['review-security'], 'CLI stdout export is portable and matches stored menu content.' );
} finally {
	$restored = $store->replace_all( $original );
	if ( $missing === $old_backups ) {
		delete_option( 'abmm_import_backups' );
	} else {
		update_option( 'abmm_import_backups', $old_backups );
	}
	$_POST = $old_post;
	$_REQUEST = $old_request;
	$_FILES = $old_files;
	wp_set_current_user( $old_user );
	abmm_review_assert( true === $restored, 'Restore original fixture menus.' );
}
WP_CLI::success( 'Real WordPress JSON, nested sanitization, menu escaping, malformed upload metadata and CLI stdout regressions passed.' );
