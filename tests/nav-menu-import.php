<?php
/**
 * Import from a classic WordPress menu: conversion rules, the dry-run
 * report, the AJAX gate, limits, and that importing stores a new draft
 * without touching anything else. Uses small WordPress stubs.
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
function wp_kses( $html ) { return $html; }
function wp_kses_post( $html ) { return $html; }
function _x( $text ) { return $text; }
function _n( $single, $plural, $number ) { return 1 === (int) $number ? $single : $plural; }
function wp_json_encode( $value, $flags = 0, $depth = 512 ) { return json_encode( $value, $flags, $depth ); }
function wp_generate_uuid4() { return 'uuid-' . mt_rand(); }
function get_bloginfo() { return 'Test Site'; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class ABMM_Test_DB {
	public $options = 'wp_options';
	public function prepare( $query ) { return $query; }
	public function query( $query ) { return 1; }
	public function get_var( $query ) { return '1'; }
}
$wpdb = new ABMM_Test_DB();
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

// A WordPress menu fixture: term 7 "Main", term 8 empty.
$GLOBALS['abmm_nav_items']  = array();
$GLOBALS['abmm_post_status'] = array();
function abmm_nav( $id, $parent, $title, $url, $extra = array() ) {
	$GLOBALS['abmm_nav_items'][] = (object) array_merge(
		array( 'ID' => $id, 'db_id' => $id, 'menu_item_parent' => (string) $parent, 'title' => $title, 'url' => $url, 'target' => '', 'xfn' => '', 'classes' => array( '' ), 'description' => '', 'type' => 'custom', 'object_id' => 0 ),
		$extra
	);
}
function wp_get_nav_menu_object( $id ) {
	$names = array( 7 => 'Main', 8 => 'Empty' );
	return isset( $names[ $id ] ) ? (object) array( 'term_id' => $id, 'name' => $names[ $id ] ) : false;
}
function wp_get_nav_menu_items( $id ) { return 7 === (int) $id ? $GLOBALS['abmm_nav_items'] : array(); }
function get_post_status( $id ) { return $GLOBALS['abmm_post_status'][ $id ] ?? 'publish'; }

require_once dirname( __DIR__ ) . '/includes/class-abmm-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-data.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-import-export.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-admin.php';

function verify_import( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

// Home                                 -> link (target, rel, classes kept)
// Products                             -> mega with categories
//   Software (url)                     -> category, "see all" = its URL
//     CRM (description)                -> link
//       CRM for retail                 -> flattened (depth 4)
//         Retail POS                   -> flattened (depth 5)
//     CRM                              -> duplicate label warning
//   Hardware (#)                       -> category without "see all"
//     Scanners                         -> link
//   Draft page (unpublished)           -> skipped
//     Child of draft                   -> skipped (parent skipped)
// Company                              -> mega, one columns-only category
//   About, Careers                     -> links
// (empty label)                        -> skipped
// Bad link (javascript:)               -> skipped
// Orphan (parent 999)                  -> top-level link
abmm_nav( 1, 0, 'Home', 'https://example.test/', array( 'target' => '_blank', 'xfn' => 'nofollow', 'classes' => array( '', 'menu-home', 'highlight' ), 'description' => 'Top description' ) );
abmm_nav( 2, 0, 'Products', '#' );
abmm_nav( 3, 2, 'Software', 'https://example.test/software/', array( 'description' => 'Apps we make' ) );
abmm_nav( 4, 3, 'CRM', '/crm/', array( 'description' => 'Customer records', 'type' => 'post_type', 'object_id' => 40 ) );
abmm_nav( 5, 4, 'CRM for retail', '/crm/retail/' );
abmm_nav( 6, 5, 'Retail POS', '/crm/retail/pos/' );
abmm_nav( 13, 3, 'crm', '/crm-2/' );
abmm_nav( 7, 2, 'Hardware', '#' );
abmm_nav( 8, 7, 'Scanners', '/scanners/', array( 'xfn' => 'friend nofollow' ) );
abmm_nav( 9, 2, 'Draft page', '/draft/', array( 'type' => 'post_type', 'object_id' => 90 ) );
abmm_nav( 14, 9, 'Child of draft', '/draft/child/' );
abmm_nav( 10, 0, 'Company', '/company/' );
abmm_nav( 11, 10, 'About', '/about/' );
abmm_nav( 12, 10, 'Careers', '/careers/', array( 'target' => '_blank' ) );
abmm_nav( 15, 0, '   ', '/nothing/' );
abmm_nav( 16, 0, 'Bad link', 'javascript:alert(1)' );
abmm_nav( 17, 999, 'Orphan', '/orphan/' );
abmm_nav( 18, 10, 'No link', '' );
$GLOBALS['abmm_post_status'][90] = 'draft';

$store  = ABMM_Data::instance();
$result = $store->import_from_nav_menu( 7 );
verify_import( ! is_wp_error( $result ), 'A valid WordPress menu converts.' );
$menu   = $result['menu'];
$report = $result['report'];
$items  = $menu['items'];
verify_import( 'Main (imported)' === $menu['title'], 'The draft is named after the WordPress menu.' );
verify_import( array( 'Home', 'Products', 'Company', 'Orphan' ) === array_column( $items, 'label' ), 'Top-level order is kept and skipped items are left out.' );

$home = $items[0];
verify_import( 'link' === $home['type'] && 'https://example.test/' === $home['url'], 'A top-level item without children becomes a link.' );
verify_import( '_blank' === $home['target'] && 'nofollow noopener' === $home['rel'] && 'menu-home highlight' === $home['class'], 'Target, rel/xfn and classes are kept (new-tab links gain noopener).' );

$products = $items[1];
verify_import( 'mega' === $products['type'] && empty( $products['hide_category_bar'] ), 'An item with grandchildren becomes a mega menu with a category bar.' );
verify_import( array( 'Software', 'Hardware' ) === array_column( $products['categories'], 'title' ), 'Depth-2 children become categories; the unpublished one is skipped.' );
$software = $products['categories'][0];
verify_import( 'Apps we make' === $software['description'], 'Category descriptions are kept.' );
verify_import( 'https://example.test/software/' === $software['panel_url'] && 'Software' === $software['panel_title'], "A category's own page becomes its see-all link." );
$links = $software['groups'][0]['links'];
verify_import( array( 'CRM', 'CRM for retail', 'Retail POS', 'crm' ) === array_column( $links, 'label' ), 'Depth 3 and deeper become links, flattened in order.' );
verify_import( 'Customer records' === $links[0]['description'] && '/crm/' === $links[0]['url'], 'Link descriptions and URLs are kept.' );
verify_import( '' === $products['categories'][1]['panel_url'], 'A "#" category gets no see-all link.' );

$company = $items[2];
verify_import( ! empty( $company['hide_category_bar'] ) && 1 === count( $company['categories'] ), 'Children without children become one columns-only category.' );
verify_import( array( 'About', 'Careers', 'No link' ) === array_column( $company['categories'][0]['groups'][0]['links'], 'label' ), 'The columns-only category holds the children.' );
verify_import( '_blank' === $company['categories'][0]['groups'][0]['links'][1]['target'], 'Link targets are kept in columns.' );

verify_import( 14 === $report['imported'], 'The report counts imported entries (got ' . $report['imported'] . ').' );
verify_import( array( 'CRM for retail', 'Retail POS' ) === array_column( $report['flattened'], 'label' ) && array( 4, 5 ) === array_column( $report['flattened'], 'depth' ), 'The report lists what was flattened and from which level.' );
$skipped = array_column( $report['skipped'], 'reason', 'label' );
verify_import( isset( $skipped['Draft page'], $skipped['Child of draft'], $skipped['Bad link'], $skipped['Untitled item #15'] ), 'Unpublished, orphaned-by-skip, unsafe and unlabeled items are reported as skipped.' );
verify_import( false !== strpos( $skipped['Draft page'], 'not published' ), 'The skip reason says why.' );
$warnings = implode( "\n", $report['warnings'] );
verify_import( false !== strpos( $warnings, 'called "crm"' ), 'Duplicate labels are flagged.' );
verify_import( false !== strpos( $warnings, 'description of "Home"' ), 'A dropped top-level description is flagged.' );
verify_import( false !== strpos( $warnings, '"friend" on "Scanners"' ), 'Unsupported XFN values are flagged.' );
verify_import( false !== strpos( $warnings, '"No link" has no URL' ), 'A link without a URL is flagged.' );
verify_import( false === strpos( $warnings, '"Products" has no URL' ), 'A parent without a URL is not flagged.' );
verify_import( 'nofollow' === $products['categories'][1]['groups'][0]['links'][0]['rel'], 'Supported XFN values are kept.' );
verify_import( array() === ( $GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ] ?? array() ), 'Converting stores nothing.' );

// Failures.
verify_import( is_wp_error( $store->import_from_nav_menu( 8 ) ), 'An empty WordPress menu is refused.' );
verify_import( is_wp_error( $store->import_from_nav_menu( 404 ) ), 'A missing WordPress menu is refused.' );

// AJAX: gate, preview (dry run) and import.
function nav_request( $post, $admin = true ) {
	$GLOBALS['abmm_test_admin'] = $admin;
	$_POST = $post;
	try {
		ABMM_Admin::instance()->ajax_import_nav_menu();
	} catch ( ABMM_Test_Response $response ) {
		return $response;
	}
	throw new Exception( 'The import request did not end with a response.' );
}
$base = array( 'action' => 'abmm_import_nav_menu', 'nonce' => 'valid-fixture-nonce', 'term_id' => '7' );
verify_import( 403 === nav_request( $base, false )->getCode(), 'Only users with manage_options can import.' );
verify_import( 403 === nav_request( array_merge( $base, array( 'nonce' => 'stale' ) ) )->getCode(), 'A bad nonce is refused.' );
verify_import( 400 === nav_request( array_merge( $base, array( 'term_id' => '8' ) ) )->getCode(), 'An empty menu returns an error.' );
$preview = nav_request( $base );
verify_import( $preview->response['success'] && ! isset( $preview->response['data']['id'] ), 'Preview succeeds without creating a menu.' );
verify_import( 'Products' === $preview->response['data']['summary'][1]['label'] && 2 === count( $preview->response['data']['summary'][1]['categories'] ), 'Preview summarises the result.' );
verify_import( array() === ( $GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ] ?? array() ), 'Preview stores nothing.' );

$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ] = array( 'existing' => $store->sanitize_menu( array( 'title' => 'Existing', 'items' => array() ) ) );
$imported = nav_request( array_merge( $base, array( 'mode' => 'import' ) ) );
verify_import( $imported->response['success'], 'Import succeeds.' );
$new_id = $imported->response['data']['id'];
$all    = $GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ];
verify_import( isset( $all[ $new_id ], $all['existing'] ) && 2 === count( $all ), 'Import adds one new menu and keeps the others.' );
verify_import( $all[ $new_id ] === $store->sanitize_menu( $all[ $new_id ] ), 'The stored menu is sanitized.' );
verify_import( false !== strpos( $imported->response['data']['edit_url'], 'edit=' . $new_id ), 'Import returns the editor URL.' );
verify_import( empty( $all[ $new_id ]['cta']['show'] ), 'The draft has no call to action switched on.' );

// Existing import limits apply.
$GLOBALS['abmm_nav_items'] = array();
for ( $i = 1; $i <= 6000; $i++ ) {
	abmm_nav( $i, 0, 'Item ' . $i . ' ' . str_repeat( 'x', 120 ), '/page-' . $i . '/' );
}
$big = $store->import_from_nav_menu( 7 );
verify_import( is_wp_error( $big ) && false !== strpos( $big->get_error_message(), '1 MB' ), 'A menu over the 1 MB limit is refused.' );

echo "WordPress menu import: conversion, report, dry run, gate, draft creation and limits passed.\n";
