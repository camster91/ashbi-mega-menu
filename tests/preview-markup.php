<?php
/**
 * Device preview markup: the editor's phone and tablet frames render unsaved
 * menu data with the live renderer. Checks the nonce and capability gate, that
 * nothing is stored, that input is sanitized, and the preview-only link IDs.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'ABMM_OPTION_KEY', 'abmm_menus' );
define( 'ABMM_ARCHIVE_OPTION', 'abmm_archives' );
define( 'ABMM_VERSION', 'test' );
define( 'ABMM_PLUGIN_URL', 'https://example.test/wp-content/plugins/ashbi-mega-menu/' );
define( 'ABMM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['abmm_test_options'] = array();
$_SERVER['REQUEST_URI'] = '/';

function __( $text ) { return $text; }
function _x( $text ) { return $text; }
function esc_html__( $text ) { return esc_html( $text ); }
function esc_attr__( $text ) { return esc_attr( $text ); }
function esc_attr_x( $text ) { return esc_attr( $text ); }
function esc_html_e( $text ) { echo esc_html( $text ); }
function esc_attr_e( $text ) { echo esc_attr( $text ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function abmm_test_clean_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) { return ''; }
	$url = preg_replace( '/[\x00-\x20"<>`]+/', '', $url );
	if ( preg_match( '/^([a-z][a-z0-9+.-]*):/i', $url, $m ) && ! in_array( strtolower( $m[1] ), array( 'http', 'https', 'mailto', 'tel' ), true ) ) {
		return '';
	}
	return $url;
}
function esc_url_raw( $url ) { return abmm_test_clean_url( $url ); }
function esc_url( $url ) { return htmlspecialchars( abmm_test_clean_url( $url ), ENT_QUOTES, 'UTF-8' ); }
function wp_kses_no_null( $text ) { return str_replace( "\0", '', (string) $text ); }
function sanitize_text_field( $text ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) ); }
function sanitize_textarea_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_html_class( $class ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class ); }
function sanitize_hex_color( $color ) { return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? $color : null; }
function absint( $n ) { return abs( (int) $n ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_unslash( $value ) { return $value; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function wp_generate_password( $length = 12 ) { return substr( md5( uniqid( '', true ) ), 0, $length ); }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function is_preview() { return false; }
function get_post_status() { return 'publish'; }
function add_action() {}
function add_filter() {}
function apply_filters( $tag, $value ) { return $value; }
function wp_register_style() {}
function wp_register_script() {}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script() {}
function get_option( $key, $default = false ) { return $GLOBALS['abmm_test_options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['abmm_test_options'][ $key ] = $value; return true; }
function wp_cache_delete() {}
function is_wp_error( $value ) { return false; }
function wp_kses( $html ) { return $html; }
function wp_kses_post( $html ) { return $html; }
function check_ajax_referer( $action, $field ) {
	if ( 'abmm_admin' !== $action || 'valid-fixture-nonce' !== ( $_POST[ $field ] ?? '' ) ) {
		throw new ABMM_Test_Response( array( 'success' => false ), 403 );
	}
}
function current_user_can( $capability ) { return 'manage_options' === $capability && ! empty( $GLOBALS['abmm_test_admin'] ); }
function wp_send_json_error( $data, $status = 200 ) { throw new ABMM_Test_Response( array( 'success' => false, 'data' => $data ), $status ); }
function wp_send_json_success( $data ) { throw new ABMM_Test_Response( array( 'success' => true, 'data' => $data ), 200 ); }

class ABMM_Test_Response extends Exception {
	public $response;
	public function __construct( $response, $status ) { parent::__construct( '', $status ); $this->response = $response; }
}
class ABMM_Import_Export { const MAX_MENU_BYTES = 1048576; }

require_once dirname( __DIR__ ) . '/includes/class-abmm-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-data.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-frontend.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-admin.php';

function verify_preview( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

function preview_request( $post, $admin = true ) {
	$GLOBALS['abmm_test_admin'] = $admin;
	$_POST = $post;
	try {
		ABMM_Admin::instance()->ajax_preview_markup();
	} catch ( ABMM_Test_Response $response ) {
		return $response;
	}
	throw new Exception( 'The preview request did not end with a response.' );
}

$menu = array(
	'title' => 'Draft',
	'items' => array(
		array(
			'id'         => 'products',
			'label'      => 'Products <b>new</b>',
			'type'       => 'mega',
			'mega_style' => 'platforms',
			'categories' => array(
				array( 'id' => 'finance', 'title' => 'Finance', 'groups' => array( array( 'title' => 'Get paid', 'links' => array( array( 'id' => 'link_invoice', 'label' => 'Invoicing', 'url' => '/invoicing/' ), array( 'id' => 'x" onmouseover="alert(1)', 'label' => 'Odd id', 'url' => '/odd/' ) ) ) ) ),
				array( 'id' => 'teams', 'title' => 'Teams', 'groups' => array( array( 'title' => 'People', 'links' => array( array( 'id' => 'link_dir', 'label' => 'Directory', 'url' => 'javascript:alert(1)' ) ) ) ) ),
			),
		),
		array( 'id' => 'pricing', 'label' => 'Pricing', 'type' => 'link', 'url' => '/pricing/' ),
	),
	'cta'   => array( 'show' => true, 'label' => 'Start', 'url' => '/start/' ),
);
$valid = array( 'action' => 'abmm_preview_markup', 'nonce' => 'valid-fixture-nonce', 'menu_id' => 'menu-1', 'menu_data' => json_encode( $menu ) );

// Gate: a nonce and manage_options are both required.
$denied = preview_request( $valid, false );
verify_preview( 403 === $denied->getCode() && ! $denied->response['success'], 'A user without manage_options cannot render previews.' );
$denied = preview_request( array_merge( $valid, array( 'nonce' => 'stale' ) ) );
verify_preview( 403 === $denied->getCode() && ! $denied->response['success'], 'An invalid nonce is rejected.' );

// Bad input.
verify_preview( 400 === preview_request( array_merge( $valid, array( 'menu_data' => '' ) ) )->getCode(), 'Empty menu data is rejected.' );
verify_preview( 400 === preview_request( array_merge( $valid, array( 'menu_data' => '{oops' ) ) )->getCode(), 'Malformed JSON is rejected.' );
verify_preview( 413 === preview_request( array_merge( $valid, array( 'menu_data' => str_repeat( 'x', ABMM_Import_Export::MAX_MENU_BYTES + 1 ) ) ) )->getCode(), 'Oversized menu data is rejected.' );

// Rendering: live markup, sanitized, nothing stored.
$GLOBALS['abmm_test_options'] = array();
$ok = preview_request( $valid );
verify_preview( $ok->response['success'], 'An authorized request renders.' );
$html = $ok->response['data']['html'];
verify_preview( array() === $GLOBALS['abmm_test_options'], 'Rendering a preview stores nothing.' );
verify_preview( false !== strpos( $html, 'class="abmm-header' ) && false !== strpos( $html, 'data-abmm-item-id="products"' ), 'The preview uses the live header markup.' );
verify_preview( 2 === substr_count( $html, 'data-abmm-section-toggle' ), 'The two-tap accordion buttons are rendered.' );
verify_preview( false !== strpos( $html, 'abmm-nav__cta' ), 'The drawer CTA is rendered.' );
verify_preview( false === strpos( $html, '<b>' ), 'Labels are sanitized before rendering.' );
verify_preview( false === stripos( $html, 'javascript:' ), 'Unsafe URLs are removed before rendering.' );
verify_preview( false !== strpos( $html, '<li class="abmm-mega__col-item" data-abmm-link-id="link_invoice">' ), 'Preview links carry their ID for click-to-select.' );
verify_preview( false === strpos( $html, 'onmouseover="' ), 'Link IDs are sanitized or escaped.' );

// The live site never gets the preview attributes.
$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ] = array( 'menu-1' => ABMM_Data::instance()->sanitize_menu( $menu ) );
$live = ABMM_Frontend::instance()->render( 'menu-1' );
verify_preview( false !== strpos( $live, 'abmm-mega__col-item' ) && false === strpos( $live, 'data-abmm-link-id' ), 'Live markup has no preview link IDs.' );

echo "Device preview markup: gate, sanitizing, no storage and preview-only link IDs passed.\n";
