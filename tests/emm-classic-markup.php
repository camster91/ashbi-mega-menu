<?php
/**
 * Classic mode renders imported Easy Mega Menu menus with Easy Mega Menu's
 * exact markup.
 *
 * Each case imports an Easy Mega Menu export with the importer's mapping, renders it
 * with ABMM_Classic and compares the HTML byte for byte with what Easy Mega
 * Menu printed for the same menu and page. Only the icon sprite's folder is
 * normalized, because the sprite now lives in this site's uploads.
 *
 * Fixtures: tests/fixtures/emm (public sample) or, for a real site, a private
 * folder named by ABMM_EMM_FIXTURES holding emm-export.json, cases.json and the
 * captured header files. Client data never goes in this repository.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'ABMM_OPTION_KEY', 'abmm_menus' );
define( 'ABMM_ARCHIVE_OPTION', 'abmm_archives' );
define( 'ABMM_CONTEXTUAL_NAV_OPTION', 'abmm_contextual_navigation_enabled' );
define( 'ABMM_VERSION', 'test' );
define( 'ABMM_PLUGIN_URL', 'https://example.test/wp-content/plugins/ashbi-mega-menu/' );
define( 'ABMM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$abmm_fixtures = getenv( 'ABMM_EMM_FIXTURES' ) ?: __DIR__ . '/fixtures/emm';
$abmm_cases    = json_decode( (string) file_get_contents( $abmm_fixtures . '/cases.json' ), true );
$abmm_uploads  = sys_get_temp_dir() . '/abmm-classic-test-' . getmypid();

$GLOBALS['abmm_test_options'] = array();
$GLOBALS['abmm_test_home']    = $abmm_cases['home'];
$_SERVER['REQUEST_URI']       = '/';

function __( $text ) { return $text; }
function _x( $text ) { return $text; }
function esc_html__( $text ) { return esc_html( $text ); }
function esc_attr__( $text ) { return esc_attr( $text ); }
function esc_attr_x( $text ) { return esc_attr( $text ); }
function esc_html_e( $text ) { echo esc_html( $text ); }
function esc_attr_e( $text ) { echo esc_attr( $text ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return str_replace( '&', '&#038;', htmlspecialchars( esc_url_raw( $url ), ENT_QUOTES, 'UTF-8', false ) ); }
function esc_url_raw( $url ) {
	$url = trim( (string) $url );
	if ( preg_match( '/^([a-z][a-z0-9+.-]*):/i', $url, $m ) && ! in_array( strtolower( $m[1] ), array( 'http', 'https', 'mailto', 'tel' ), true ) ) {
		return '';
	}
	return preg_replace( '/[\x00-\x20"<>`]+/', '', $url );
}
function wp_kses_no_null( $text ) { return str_replace( "\0", '', (string) $text ); }
function sanitize_text_field( $text ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) ); }
function sanitize_textarea_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_html_class( $class ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class ); }
function sanitize_hex_color( $color ) { return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? $color : null; }
function sanitize_file_name( $name ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_unslash( $value ) { return $value; }
function home_url( $path = '' ) { return $GLOBALS['abmm_test_home'] . $path; }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function wp_generate_password( $length = 12 ) { return substr( md5( uniqid( '', true ) ), 0, $length ); }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function is_preview() { return false; }
function get_post_status() { return 'publish'; }
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function apply_filters( $tag, $value ) { return $value; }
function wp_register_style() {}
function wp_register_script() {}
function wp_enqueue_style( $handle ) { $GLOBALS['abmm_test_styles'][ $handle ] = true; }
function wp_localize_script() {}
function wp_add_inline_style( $handle, $css ) { $GLOBALS['abmm_test_inline_css'] = ( $GLOBALS['abmm_test_inline_css'] ?? '' ) . $css; }
function abmm_menu_search_strings() { return array(); }
function wp_enqueue_script() {}
function wp_add_inline_script() {}
function wp_json_encode( $data, $flags = 0, $depth = 512 ) { return json_encode( $data, $flags, $depth ); }
function get_option( $key, $default = false ) { return $GLOBALS['abmm_test_options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['abmm_test_options'][ $key ] = $value; return true; }
function wp_cache_delete() {}
function is_wp_error( $value ) { return false; }
function wp_kses( $html ) { return $html; }
function wp_kses_post( $html ) { return $html; }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['abmm_test_uploads'], 'baseurl' => $GLOBALS['abmm_test_home'] . '/wp-content/uploads', 'error' => false ); }
function abmm_contextual_navigation_enabled() { return false; }

require_once dirname( __DIR__ ) . '/includes/class-abmm-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-data.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-frontend.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-classic.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-classic-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-emm-migration.php';

function verify_classic( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

/** Show the first difference with some context. */
function classic_first_difference( $expected, $actual ) {
	$length = min( strlen( $expected ), strlen( $actual ) );
	for ( $i = 0; $i < $length && $expected[ $i ] === $actual[ $i ]; $i++ );
	$from = max( 0, $i - 160 );
	return "at byte $i\n--- expected\n" . substr( $expected, $from, 320 ) . "\n--- actual\n" . substr( $actual, $from, 320 );
}

// The importer copies Easy Mega Menu's sprite into uploads.
$GLOBALS['abmm_test_uploads'] = $abmm_uploads;
@mkdir( $abmm_uploads . '/' . ABMM_Classic::UPLOAD_DIR, 0777, true );
copy( $abmm_fixtures . '/emm-sprite.svg', $abmm_uploads . '/' . ABMM_Classic::UPLOAD_DIR . '/emm-sprite-0123456789.svg' );
update_option( ABMM_Classic::ASSETS_OPTION, array( 'css' => '', 'sprite' => 'emm-sprite-0123456789.svg', 'version' => $abmm_cases['version'] ) );

$export    = json_decode( (string) file_get_contents( $abmm_fixtures . '/emm-export.json' ), true );
$migration = ABMM_EMM_Migration::instance();
$menus     = array();
foreach ( $export['menus'] as $id => $menu ) {
	$mapped = $migration->map_menu( $id, $menu );
	verify_classic( ABMM_Classic::is_classic( $mapped['menu'] ), "Imported menu $id uses classic mode." );
	verify_classic( array() === $mapped['issues'], "Menu $id imports exactly:\n" . implode( "\n", $mapped['issues'] ) );
	$menus[ $id ] = $mapped['menu'];
}
// A second sanitize (save, export, import) keeps classic menus unchanged.
foreach ( $menus as $id => $menu ) {
	verify_classic( ABMM_Data::instance()->sanitize_menu( $menu ) === $menu, "Menu $id is stable across save and re-import." );
}
$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ] = $menus;

$sequence = new ReflectionProperty( 'ABMM_Classic', 'render_sequence' );
$sequence->setAccessible( true );
$normalize = function ( $html ) {
	return trim( preg_replace( '#href="[^"]*/emm-sprite[^"/?]*\.svg\?ver=([^"\#]*)\##', 'href="SPRITE?ver=$1#', $html ) );
};

$checked = 0;
foreach ( $abmm_cases['pages'] as $page ) {
	$_SERVER['REQUEST_URI'] = $page['path'];
	$sequence->setValue( ABMM_Classic::instance(), 0 );
	foreach ( $page['headers'] as $header ) {
		$expected = (string) file_get_contents( $abmm_fixtures . '/' . $header['fixture'] );
		$actual   = ABMM_Frontend::instance()->render( $header['id'], $header['args'] ?? array() );
		verify_classic( $normalize( $expected ) === $normalize( $actual ), $page['path'] . ' ' . $header['id'] . ' matches Easy Mega Menu ' . classic_first_difference( $normalize( $expected ), $normalize( $actual ) ) );
		++$checked;
	}
}

// Only classic menus: the classic stylesheet is queued early and Ashbi's is not.
$GLOBALS['abmm_test_styles'] = array();
ABMM_Frontend::instance()->register_assets();
ABMM_Classic::instance()->register_assets();
verify_classic( isset( $GLOBALS['abmm_test_styles']['abmm-classic'] ) && ! isset( $GLOBALS['abmm_test_styles']['abmm-frontend'] ), 'A site whose menus are all classic loads only the classic stylesheet.' );

// The phone scroll fix is off by default (the parity cases above prove the
// markup) and, when chosen, adds one class and one inline stylesheet.
verify_classic( empty( $GLOBALS['abmm_test_inline_css'] ), 'No phone scroll fix CSS unless a menu turns it on.' );
$first_id = array_key_first( $menus );
$fixed    = $menus[ $first_id ];
$fixed['settings']['classic_phone_scroll_fix'] = true;
$fixed = ABMM_Data::instance()->sanitize_menu( $fixed );
verify_classic( true === $fixed['settings']['classic_phone_scroll_fix'], 'The phone scroll fix is kept on classic menus.' );
$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ][ $first_id ] = $fixed;
$with_fix = ABMM_Frontend::instance()->render( $first_id );
verify_classic( 1 === preg_match( '/<header class="emm-header [^"]*\bemm-phone-scroll-fix"/', $with_fix ), 'The fix adds its class to the header.' );
ABMM_Classic::instance()->register_assets();
verify_classic( false !== strpos( $GLOBALS['abmm_test_inline_css'] ?? '', '.emm-header.emm-phone-scroll-fix .emm-mega__panel{max-height:none!important;overflow:visible!important}' ), 'The fix prints its stylesheet.' );
$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ][ $first_id ] = $menus[ $first_id ];
$ashbi_fix = ABMM_Data::instance()->sanitize_menu( array( 'title' => 'Ashbi', 'items' => array(), 'settings' => array( 'classic_phone_scroll_fix' => true ) ) );
verify_classic( ! isset( $ashbi_fix['settings']['classic_phone_scroll_fix'] ), 'Ashbi menus never store classic settings.' );

// Menus created in Ashbi keep Ashbi's markup.
$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ]['plain'] = ABMM_Data::instance()->sanitize_menu( array( 'title' => 'Plain', 'items' => array( array( 'id' => 'a', 'label' => 'A', 'type' => 'link', 'url' => '/a/' ) ) ) );
$plain = ABMM_Frontend::instance()->render( 'plain' );
verify_classic( false !== strpos( $plain, 'class="abmm-header' ) && false === strpos( $plain, 'emm-header' ), 'Ashbi menus keep Ashbi markup.' );
verify_classic( ! isset( $GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ]['plain']['settings']['render_mode'] ), 'Ashbi menus store no render mode.' );

// The icon list stored at import time is used instead of reading the sprite.
$reset_icons = new ReflectionProperty( 'ABMM_Classic', 'icons' );
$reset_icons->setAccessible( true );
$reset_icons->setValue( ABMM_Classic::instance(), null );
update_option( ABMM_Classic::ASSETS_OPTION, array( 'css' => '', 'sprite' => 'emm-sprite-0123456789.svg', 'version' => $abmm_cases['version'], 'keys' => array( 'link' ) ) );
$listed = ABMM_Frontend::instance()->render( array_key_first( $menus ) );
preg_match_all( '#<use href="[^"]*\#emm-icon-([a-z0-9_-]+)"#', $listed, $listed_icons );
verify_classic( $listed_icons[1] && array( 'link' ) === array_values( array_unique( $listed_icons[1] ) ), 'Icons come from the stored list, without parsing the sprite.' );

// Without a copied sprite the bundled generic sprite is used.
update_option( ABMM_Classic::ASSETS_OPTION, array() );
$reset = new ReflectionProperty( 'ABMM_Classic', 'icons' );
$reset->setAccessible( true );
$reset->setValue( ABMM_Classic::instance(), null );
$first    = array_key_first( $menus );
$fallback = ABMM_Frontend::instance()->render( $first );
preg_match_all( '#<use href="([^"]+)"#', $fallback, $uses );
verify_classic( $uses[1] && array() === array_filter( $uses[1], function ( $href ) { return 0 !== strpos( $href, '/wp-content/plugins/ashbi-mega-menu/assets/classic/emm-sprite.svg?ver=test#emm-icon-' ); } ), 'Without a copied sprite, icons use the bundled sprite.' );

// The importer's sprite sanitizer removes scripts and handlers.
$dirty = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><symbol id="emm-icon-x" onload="alert(1)"><path d="M0 0" /><image href="https://evil.test/x.png" /></symbol></svg>';
$clean = ABMM_EMM_Migration::sanitize_sprite( $dirty );
verify_classic( false === stripos( $clean, 'script' ) && false === stripos( $clean, 'onload' ) && false === stripos( $clean, 'evil' ) && false !== strpos( $clean, 'emm-icon-x' ), 'Copied sprites keep only drawing elements.' );

array_map( 'unlink', glob( $abmm_uploads . '/' . ABMM_Classic::UPLOAD_DIR . '/*' ) ?: array() );
echo "Classic markup: $checked Easy Mega Menu headers match byte for byte; mapping is exact and stable.\n";
