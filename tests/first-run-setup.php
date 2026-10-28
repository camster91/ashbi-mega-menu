<?php
/**
 * First-run setup: a draft menu from existing pages, the placement
 * recommendation, and the per-administrator setup guide (first run, skip,
 * resume, repeat). Uses small WordPress stubs.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'ABMM_OPTION_KEY', 'abmm_menus' );
define( 'ABMM_ARCHIVE_OPTION', 'abmm_archives' );
define( 'ABMM_VERSION', 'test' );
define( 'ABMM_PLUGIN_URL', 'https://example.test/wp-content/plugins/ashbi-mega-menu/' );
define( 'ABMM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'ABMM_ONBOARDING_META', 'abmm_onboarding_complete' );

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
$wpdb = new ABMM_Test_DB();

// Site fixture.
$GLOBALS['abmm_pages'] = array(
	10 => (object) array( 'ID' => 10, 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => 0, 'post_title' => 'Services' ),
	11 => (object) array( 'ID' => 11, 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => 10, 'post_title' => 'Design' ),
	12 => (object) array( 'ID' => 12, 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => 11, 'post_title' => 'Logos' ),
	13 => (object) array( 'ID' => 13, 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => 0, 'post_title' => 'About <b>us</b>' ),
	14 => (object) array( 'ID' => 14, 'post_type' => 'page', 'post_status' => 'draft', 'post_parent' => 0, 'post_title' => 'Draft' ),
	15 => (object) array( 'ID' => 15, 'post_type' => 'post', 'post_status' => 'publish', 'post_parent' => 0, 'post_title' => 'A post' ),
	16 => (object) array( 'ID' => 16, 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => 99, 'post_title' => 'Orphan' ),
);
function get_post( $id ) { return $GLOBALS['abmm_pages'][ (int) $id ] ?? null; }
function get_permalink( $post ) { return 'https://example.test/?page_id=' . ( is_object( $post ) ? $post->ID : (int) $post ); }
function get_current_user_id() { return $GLOBALS['abmm_user'] ?? 1; }
$GLOBALS['abmm_meta'] = array();
function get_user_meta( $user, $key, $single = false ) { return $GLOBALS['abmm_meta'][ $user ][ $key ] ?? ''; }
function update_user_meta( $user, $key, $value ) { $GLOBALS['abmm_meta'][ $user ][ $key ] = $value; return true; }
$GLOBALS['abmm_block_theme'] = false;
$GLOBALS['abmm_widgets'] = false;
function wp_is_block_theme() { return $GLOBALS['abmm_block_theme']; }
function current_theme_supports( $feature ) { return 'widgets' === $feature && $GLOBALS['abmm_widgets']; }
$GLOBALS['abmm_placed_posts'] = array();
function get_post_types() { return array( 'post' => 'post', 'page' => 'page' ); }
function get_posts( $args ) { return $GLOBALS['abmm_placed_posts']; }
function parse_blocks( $content ) { return array(); }
function get_shortcode_regex( $tags ) { return '\\[(\\[?)(' . implode( '|', $tags ) . ')([^\\]]*)\\]'; }
function shortcode_parse_atts( $text ) { preg_match_all( '/(\\w+)="([^"]*)"/', $text, $m ); return array_combine( $m[1], $m[2] ); }

require_once dirname( __DIR__ ) . '/includes/class-abmm-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-data.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-admin.php';

function verify_setup( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}
function setup_request( $handler, $post ) {
	$GLOBALS['abmm_test_admin'] = true;
	$_POST = array_merge( array( 'nonce' => 'valid-fixture-nonce' ), $post );
	try {
		ABMM_Admin::instance()->$handler();
	} catch ( ABMM_Test_Response $response ) {
		return $response;
	}
	throw new Exception( 'No response.' );
}

$data  = ABMM_Data::instance();
$admin = ABMM_Admin::instance();

// A draft menu from pages: order kept, children nested, only published pages.
$id   = $data->create_from_pages( array( 13, 10, 11, 12, 14, 15, 16, 999 ) );
$menu = $data->get( $id );
verify_setup( is_string( $id ) && $menu, 'Choosing pages creates a menu.' );
verify_setup( 'Main menu' === $menu['title'], 'The menu has a neutral name.' );
verify_setup( array( 'About us', 'Services', 'Orphan' ) === array_column( $menu['items'], 'label' ), 'Top-level pages keep their chosen order; tags are stripped; unpublished pages and posts are skipped.' );
verify_setup( 'link' === $menu['items'][0]['type'] && 'https://example.test/?page_id=13' === $menu['items'][0]['url'], 'A page without chosen children is a link to its permalink.' );
$services = $menu['items'][1];
verify_setup( 'mega' === $services['type'] && ! empty( $services['hide_category_bar'] ), 'A page with chosen children becomes a dropdown of link columns.' );
verify_setup( array( 'Design', 'Logos' ) === array_column( $services['categories'][0]['groups'][0]['links'], 'label' ), 'Children and grandchildren go in the parent dropdown.' );
verify_setup( 'link' === $menu['items'][2]['type'], 'A page whose parent was not chosen is a top-level link.' );
verify_setup( empty( $menu['cta']['show'] ), 'The draft has no button switched on.' );
verify_setup( is_wp_error( $data->create_from_pages( array( 14, 15 ) ) ), 'No published pages is an error.' );

// Onboarding endpoint: pages choice.
$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ] = array();
$res = setup_request( 'ajax_onboarding', array( 'choice' => 'pages', 'page_ids' => array( '10', '11' ) ) );
verify_setup( $res->response['success'] && false !== strpos( $res->response['data']['url'], 'edit=' ), 'The pages choice creates a menu and opens it.' );
verify_setup( 'pages' === get_user_meta( 1, ABMM_ONBOARDING_META ), 'The choice is remembered for this administrator.' );
verify_setup( 400 === setup_request( 'ajax_onboarding', array( 'choice' => 'pages', 'page_ids' => array() ) )->getCode(), 'Choosing no pages is refused.' );

// Placement recommendation follows the theme.
$GLOBALS['abmm_block_theme'] = true;
verify_setup( 'block' === $admin->placement_recommendation()['method'], 'A block theme gets the Header block.' );
$GLOBALS['abmm_block_theme'] = false;
$GLOBALS['abmm_widgets'] = true;
$GLOBALS['wp_registered_sidebars'] = array( 'header' => array() );
verify_setup( 'widget' === $admin->placement_recommendation()['method'], 'A classic theme with widget areas gets the widget.' );
$GLOBALS['wp_registered_sidebars'] = array();
verify_setup( 'shortcode' === $admin->placement_recommendation()['method'], 'Otherwise, the shortcode or PHP.' );

// Setup guide: first run, resume, skip, reopen, repeat.
$menus = $data->get_all();
$not_ready = array_fill_keys( array_keys( $menus ), array( 'ready' => false ) );
$state = $admin->setup_guide_state( $menus, $not_ready );
verify_setup( array( 'create' => true, 'edit' => false, 'place' => false, 'verify' => false ) === $state['steps'] && ! $state['dismissed'], 'First run: only "create" is done and the guide shows.' );
$ready = array_fill_keys( array_keys( $menus ), array( 'ready' => true ) );
verify_setup( $admin->setup_guide_state( $menus, $ready )['steps']['edit'], 'Resume: a ready menu completes "edit" without user input.' );
$GLOBALS['abmm_placed_posts'] = array( (object) array( 'post_content' => '[ashbi_mega_menu id="' . array_key_first( $menus ) . '"]' ) );
verify_setup( $admin->setup_guide_state( $menus, $ready )['steps']['place'], 'A detected shortcode completes "place".' );
$GLOBALS['abmm_placed_posts'] = array();
setup_request( 'ajax_setup_guide', array( 'guide_action' => 'dismiss' ) );
verify_setup( $admin->setup_guide_state( $menus, $ready )['dismissed'], 'Skip: hiding the guide is remembered.' );
$GLOBALS['abmm_user'] = 2;
verify_setup( ! $admin->setup_guide_state( $menus, $ready )['dismissed'], 'Each administrator has their own guide state.' );
$GLOBALS['abmm_user'] = 1;
setup_request( 'ajax_setup_guide', array( 'guide_action' => 'reopen' ) );
verify_setup( ! $admin->setup_guide_state( $menus, $ready )['dismissed'], 'The guide can be reopened.' );
setup_request( 'ajax_setup_guide', array( 'guide_action' => 'verify' ) );
$done = $admin->setup_guide_state( $menus, $ready );
verify_setup( $done['complete'] && $done['steps']['place'], 'Checking it live completes the guide, even when the placement cannot be detected.' );
setup_request( 'ajax_setup_guide', array( 'guide_action' => 'unverify' ) );
verify_setup( ! $admin->setup_guide_state( $menus, $ready )['complete'], 'Repeat: the check can be undone.' );
verify_setup( 400 === setup_request( 'ajax_setup_guide', array( 'guide_action' => 'nonsense' ) )->getCode(), 'Unknown actions are refused.' );
$GLOBALS['abmm_test_admin'] = false;
$_POST = array( 'nonce' => 'valid-fixture-nonce', 'guide_action' => 'dismiss' );
try { $admin->ajax_setup_guide(); $code = 200; } catch ( ABMM_Test_Response $r ) { $code = $r->getCode(); }
verify_setup( 403 === $code, 'Only administrators can change the guide.' );

echo "First-run setup: pages to draft menu, placement recommendation and the per-admin setup guide passed.\n";
