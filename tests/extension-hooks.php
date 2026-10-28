<?php
/**
 * Extension API contract: the documented hooks fire with the documented
 * arguments, and their results are constrained as documented. Breaking one of
 * these is a breaking change (see docs/extending.md).
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
function get_post_status() { return 'publish'; }
function wp_register_style() {}
function wp_register_script() {}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script() {}
function get_option( $key, $default = false ) { return $GLOBALS['abmm_test_options'][ $key ] ?? $default; }
function wp_cache_delete() {}
function wp_kses( $html ) { return $html; }
function wp_kses_post( $html ) { return $html; }
function _x( $text ) { return $text; }
function _n( $single, $plural, $number ) { return 1 === (int) $number ? $single : $plural; }
function wp_json_encode( $value, $flags = 0, $depth = 512 ) { return json_encode( $value, $flags, $depth ); }
function wp_generate_uuid4() { return 'uuid-' . mt_rand(); }
function get_bloginfo() { return 'Test Site'; }
class WP_Error { public function __construct( $code = '', $message = '' ) {} }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class ABMM_Test_DB {
	public $options = 'wp_options';
	public function prepare( $query ) { return $query; }
	public function query( $query ) { return 1; }
	public function get_var( $query ) { return '1'; }
}
$wpdb = new ABMM_Test_DB();

// A small but real hook registry, so callbacks, priorities and arguments behave as in WordPress.
$GLOBALS['abmm_hooks'] = array();
function add_filter( $tag, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['abmm_hooks'][ $tag ][ $priority ][] = array( $callback, $accepted ); return true; }
function add_action( $tag, $callback, $priority = 10, $accepted = 1 ) { return add_filter( $tag, $callback, $priority, $accepted ); }
function remove_all_filters( $tag ) { unset( $GLOBALS['abmm_hooks'][ $tag ] ); }
function apply_filters( $tag, $value, ...$args ) {
	$hooks = $GLOBALS['abmm_hooks'][ $tag ] ?? array();
	ksort( $hooks );
	foreach ( $hooks as $callbacks ) {
		foreach ( $callbacks as list( $callback, $accepted ) ) {
			$value = call_user_func_array( $callback, array_slice( array_merge( array( $value ), $args ), 0, $accepted ) );
		}
	}
	return $value;
}
function do_action( $tag, ...$args ) { apply_filters( $tag, $args[0] ?? null, ...array_slice( $args, 1 ) ); }
function update_option( $key, $value ) {
	$old = $GLOBALS['abmm_test_options'][ $key ] ?? null;
	$GLOBALS['abmm_test_options'][ $key ] = $value;
	if ( null === $old ) { do_action( 'add_option_' . $key, $key, $value ); } else { do_action( 'update_option_' . $key, $old, $value, $key ); }
	return true;
}

require_once dirname( __DIR__ ) . '/includes/class-abmm-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-data.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-frontend.php';

function verify_hook( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

$data = ABMM_Data::instance();
$menu = array(
	'title' => 'Main',
	'items' => array(
		array( 'id' => 'home', 'label' => 'Home', 'type' => 'link', 'url' => '/' ),
		array( 'id' => 'members', 'label' => 'Members only', 'type' => 'link', 'url' => '/members/' ),
	),
);

// abmm_menu_saved / abmm_menu_deleted fire for every write path.
$log = array();
add_action( 'abmm_menu_saved', function ( $id, $stored, $previous ) use ( &$log ) { $log[] = array( 'saved', $id, $stored['title'], null === $previous ); }, 10, 3 );
add_action( 'abmm_menu_deleted', function ( $id, $old ) use ( &$log ) { $log[] = array( 'deleted', $id, $old['title'] ); }, 10, 2 );
$data->save( 'main', $menu );
verify_hook( array( array( 'saved', 'main', 'Main', true ) ) === $log, 'Saving a new menu fires abmm_menu_saved with a null previous menu.' );
$log = array();
$data->save( 'main', $menu );
verify_hook( array() === $log, 'Saving an unchanged menu fires nothing.' );
$data->save( 'main', array_merge( $menu, array( 'title' => 'Main v2' ) ) );
verify_hook( array( array( 'saved', 'main', 'Main v2', false ) ) === $log, 'Changing a menu fires abmm_menu_saved with the previous menu.' );
$log = array();
$data->save( 'footer', array( 'title' => 'Footer', 'items' => array() ) );
$data->delete( 'footer' );
verify_hook( array( array( 'saved', 'footer', 'Footer', true ), array( 'deleted', 'footer', 'Footer' ) ) === $log, 'Deleting fires abmm_menu_deleted with the old menu.' );
$log = array();
$data->replace_all( array( 'other' => array( 'title' => 'Other', 'items' => array() ) ) );
verify_hook( in_array( array( 'saved', 'other', 'Other', true ), $log, true ) && in_array( array( 'deleted', 'main', 'Main v2' ), $log, true ), 'Replacing the collection reports added and removed menus.' );
$data->replace_all( array( 'main' => $menu ) );

// abmm_sanitize_menu: extension data is kept under extensions.<slug>; core keys keep their shape.
add_filter( 'abmm_sanitize_menu', function ( $clean, $raw ) {
	$visibility = $raw['extensions']['acme-visibility'] ?? array();
	if ( is_array( $visibility ) ) {
		$clean['extensions']['acme-visibility'] = array_map( 'sanitize_key', $visibility );
	}
	$clean['items']   = 'not an array';
	$clean['unknown'] = 'dropped';
	return $clean;
}, 10, 2 );
$clean = $data->sanitize_menu( array_merge( $menu, array( 'extensions' => array( 'acme-visibility' => array( 'members' => 'Logged_In!' ) ) ) ) );
verify_hook( array( 'members' => 'logged_in' ) === $clean['extensions']['acme-visibility'], 'An add-on keeps its sanitized data under extensions.<slug>.' );
verify_hook( is_array( $clean['items'] ) && 2 === count( $clean['items'] ), 'A filter cannot change the type of a core key.' );
verify_hook( ! isset( $clean['unknown'] ), 'Unknown top-level keys are dropped.' );
verify_hook( $clean === $data->sanitize_menu( $clean ), 'Sanitizing is stable with extension data.' );
remove_all_filters( 'abmm_sanitize_menu' );
add_filter( 'abmm_sanitize_menu', function () { return 'broken'; } );
verify_hook( is_array( $data->sanitize_menu( $menu )['items'] ), 'A filter returning a non-array is ignored.' );
remove_all_filters( 'abmm_sanitize_menu' );
verify_hook( ! isset( $data->sanitize_menu( array_merge( $menu, array( 'extensions' => array( 'x' => 1 ) ) ) )['extensions'] ), 'Without an add-on, extension data is not stored.' );

// abmm_menu_readiness_issues adds checks; malformed issues are dropped.
add_filter( 'abmm_menu_readiness_issues', function ( $issues, $checked ) {
	$issues[] = array( 'field' => 'Acme Field', 'message' => 'Add a <b>members</b> page.' );
	$issues[] = 'not an issue';
	$issues[] = array( 'field' => 'x', 'message' => '' );
	return $issues;
}, 10, 2 );
$ready = $data->readiness( $data->sanitize_menu( $menu ) );
verify_hook( false === $ready['ready'], 'An added issue makes the menu not ready.' );
verify_hook( array( array( 'field' => 'acmefield', 'message' => 'Add a members page.' ) ) === array_values( array_filter( $ready['issues'], function ( $i ) { return 'acmefield' === $i['field']; } ) ), 'Issues are sanitized.' );
verify_hook( 1 === count( array_filter( $ready['issues'], function ( $i ) { return 'acmefield' === $i['field']; } ) ), 'Malformed issues are dropped.' );
remove_all_filters( 'abmm_menu_readiness_issues' );

// abmm_menu_data: per-request changes, sanitized again, never stored.
$frontend = ABMM_Frontend::instance();
add_filter( 'abmm_menu_data', function ( $m, $id, $args ) {
	if ( 'main' !== $id || ! empty( $args['show_all'] ) ) {
		return $m;
	}
	$m['items'] = array_values( array_filter( $m['items'], function ( $item ) { return 'members' !== $item['id']; } ) );
	$m['items'][0]['label'] = 'Home <script>x</script>';
	return $m;
}, 10, 3 );
$html = $frontend->render( 'main' );
verify_hook( false !== strpos( $html, 'abmm-header' ) && false === strpos( $html, 'Members only' ), 'abmm_menu_data can hide an item.' );
verify_hook( false === strpos( $html, '<script>' ), 'Filtered data is sanitized again before rendering.' );
verify_hook( false !== strpos( $frontend->render( 'main', array( 'show_all' => true ) ), 'Members only' ), 'Render arguments reach the filter.' );
verify_hook( 'Members only' === $data->get( 'main' )['items'][1]['label'], 'abmm_menu_data does not change the stored menu.' );
add_filter( 'abmm_menu_data', function ( $m, $id ) { return 'empty' === ( $GLOBALS['abmm_hide'] ?? '' ) ? array() : $m; }, 20, 2 );
$GLOBALS['abmm_hide'] = 'empty';
verify_hook( '' === $frontend->render( 'main' ), 'Returning an empty menu renders nothing.' );
unset( $GLOBALS['abmm_hide'] );
remove_all_filters( 'abmm_menu_data' );

// abmm_menu_html receives the markup and its context.
add_filter( 'abmm_menu_html', function ( $html, $id, $m, $args ) { return $html . '<!-- ' . $id . ':' . count( $m['items'] ) . ':' . ( $args['context'] ?? '' ) . ' -->'; }, 10, 4 );
verify_hook( false !== strpos( $frontend->render( 'main', array( 'context' => 'block' ) ), '<!-- main:2:block -->' ), 'abmm_menu_html gets the HTML, menu ID, menu and arguments.' );
remove_all_filters( 'abmm_menu_html' );
verify_hook( '' === $frontend->render( 'missing' ), 'A missing menu still renders nothing.' );

// The documented example add-on works as written.
function is_user_logged_in() { return ! empty( $GLOBALS['abmm_logged_in'] ); }
require dirname( __DIR__ ) . '/docs/examples/abmm-members-only-items.php';
$data->save( 'main', array_merge( $menu, array( 'extensions' => array( 'acme-members-only' => array( 'members' => true ) ) ) ) );
verify_hook( array( 'members' => true ) === $data->get( 'main' )['extensions']['acme-members-only'], 'The example stores its flags with the menu.' );
verify_hook( false === strpos( $frontend->render( 'main' ), 'Members only' ), 'The example hides members-only items from visitors.' );
$GLOBALS['abmm_logged_in'] = true;
verify_hook( false !== strpos( $frontend->render( 'main' ), 'Members only' ), 'The example shows them to logged-in users.' );

echo "Extension API: lifecycle actions, sanitize, readiness, menu data and HTML filters behave as documented.\n";
