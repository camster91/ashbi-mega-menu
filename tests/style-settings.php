<?php
/**
 * Fonts and dropdown height (settings.style), the toolbar "Edit mega menu"
 * link and the Elementor widget.
 *
 * Menus without style settings store and render exactly what they did before;
 * set values are sanitized and printed as CSS scoped to that menu's ID.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'ABMM_OPTION_KEY', 'abmm_menus' );
define( 'ABMM_ARCHIVE_OPTION', 'abmm_archives' );
define( 'ABMM_VERSION', 'test' );
define( 'ABMM_PLUGIN_URL', 'https://example.test/wp-content/plugins/ashbi-mega-menu/' );
define( 'ABMM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['abmm_test_options'] = array();
$GLOBALS['abmm_test_inline']  = array();
$GLOBALS['abmm_test_admin']   = true;
$GLOBALS['abmm_test_is_admin'] = false;
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
function esc_url_raw( $url ) { return preg_replace( '/[\x00-\x20"<>`]+/', '', trim( (string) $url ) ); }
function esc_url( $url ) { return htmlspecialchars( esc_url_raw( $url ), ENT_QUOTES, 'UTF-8' ); }
function wp_kses_no_null( $text ) { return str_replace( "\0", '', (string) $text ); }
function wp_kses( $html ) { return $html; }
function sanitize_text_field( $text ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_html_class( $class ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class ); }
function sanitize_hex_color( $color ) { return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? $color : null; }
function absint( $n ) { return abs( (int) $n ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_unslash( $value ) { return $value; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_generate_password( $length = 12 ) { return substr( md5( uniqid( '', true ) ), 0, $length ); }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function is_preview() { return false; }
function is_admin() { return $GLOBALS['abmm_test_is_admin']; }
function get_post_status() { return 'publish'; }
function current_user_can() { return $GLOBALS['abmm_test_admin']; }
function add_action() {}
function add_filter() {}
function apply_filters( $tag, $value ) { return $value; }
function wp_register_style() {}
function wp_register_script() {}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script() {}
function wp_add_inline_script() {}
function wp_add_inline_style( $handle, $css ) { $GLOBALS['abmm_test_inline'][ $handle ] = ( $GLOBALS['abmm_test_inline'][ $handle ] ?? '' ) . $css; }
function wp_upload_dir() { return array( 'error' => 'none' ); }
function abmm_menu_search_strings() { return array(); }
function abmm_contextual_navigation_enabled() { return false; }
function get_option( $key, $default = false ) { return $GLOBALS['abmm_test_options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['abmm_test_options'][ $key ] = $value; return true; }
function wp_cache_delete() {}
function is_wp_error( $value ) { return false; }

require_once dirname( __DIR__ ) . '/includes/class-abmm-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-data.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-style.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-frontend.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-classic.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-classic-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-elementor.php';

function verify_style( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

$data = ABMM_Data::instance();
$base = array( 'title' => 'Main', 'items' => array( array( 'id' => 'products', 'label' => 'Products', 'type' => 'mega', 'categories' => array( array( 'id' => 'c1', 'title' => 'Finance', 'groups' => array( array( 'title' => 'Get paid', 'links' => array( array( 'id' => 'l1', 'label' => 'Invoices', 'url' => '/invoices/' ) ) ) ) ) ) ) ) );

// No style settings: nothing is stored, nothing is printed, markup unchanged.
$plain = $data->sanitize_menu( $base );
verify_style( ! isset( $plain['settings']['style'] ), 'A menu without style settings stores no style group.' );
$empty = $data->sanitize_menu( array_merge( $base, array( 'settings' => array( 'style' => array( 'panel_height' => '520', 'font_family' => 'Ignored', 'top_font_size' => '', 'top_font_weight' => 'heavy' ) ) ) ) );
verify_style( ! isset( $empty['settings']['style'] ), 'Values that do nothing on their own (a height without a mode, a font without "custom") are not stored.' );
$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ] = array( 'main' => $plain );
$before = ABMM_Frontend::instance()->render( 'main' );
ABMM_Frontend::instance()->register_assets();
verify_style( array() === $GLOBALS['abmm_test_inline'], 'No style CSS is printed for menus without style settings.' );

// Sanitizing.
$style = ABMM_Style::sanitize(
	array(
		'panel_height_mode'  => 'max',
		'panel_height'       => '5000',
		'font_family_mode'   => 'custom',
		'font_family'        => 'Supreme, "Helvetica Neue"; } body { color: red',
		'top_font_size'      => '3',
		'top_font_weight'    => '600',
		'top_letter_spacing' => '0.55',
		'top_text_transform' => 'uppercase',
		'cat_font_size'      => '99',
		'link_font_weight'   => '450',
		'desc_font_size'     => 'x',
	)
);
verify_style(
	array(
		'panel_height_mode'  => 'max',
		'panel_height'       => 1200,
		'font_family_mode'   => 'custom',
		'font_family'        => 'Supreme, "Helvetica Neue" body color red',
		'top_font_size'      => 10,
		'top_font_weight'    => '600',
		'cat_font_size'      => 40,
		'top_letter_spacing' => '0.6',
		'top_text_transform' => 'uppercase',
	) === $style,
	'Style values are clamped, unknown values dropped and the font list cannot close the CSS rule: ' . json_encode( $style )
);

// The exact CSS (tests/editor-style-history.test.js checks the editor builds the same).
$sample = array( 'font_family_mode' => 'inherit', 'top_font_size' => 18, 'top_font_weight' => '500', 'top_letter_spacing' => '0.5', 'top_text_transform' => 'uppercase', 'link_font_size' => 15, 'panel_height_mode' => 'fixed', 'panel_height' => 420 );
$css    = ABMM_Style::css( $sample, 'SCOPE', 'abmm' );
$expect = 'SCOPE,SCOPE *{font-family:inherit}@media (min-width:901px){SCOPE .abmm-nav__list > .abmm-nav__item > .abmm-nav__link{font-size:18px;font-weight:500;letter-spacing:0.5px;text-transform:uppercase}SCOPE .abmm-mega__col-label,SCOPE .abmm-mega__grid-label{font-size:15px}SCOPE .abmm-mega__panel{height:420px;max-height:420px;overflow-y:auto}}';
verify_style( $expect === $css, "The style CSS is exact:\n$css" );
verify_style( false !== strpos( ABMM_Style::css( array( 'panel_height_mode' => 'auto' ), 'S', 'emm' ), 'S .emm-mega__panel{max-height:none}' ), 'Classic menus get the same rules with emm- classes.' );
verify_style( '.abmm-header.abmm-header.abmm-header[data-abmm-id="a\"b"]' === ABMM_Style::scope( 'a"b', 'abmm' ), 'Menu IDs are escaped inside the scope selector.' );

// Stored on the menu, printed for it, markup still unchanged.
// Built from the stored menu so generated IDs match the earlier render.
$with_style = $plain;
$with_style['settings']['style'] = $sample;
$styled = $data->sanitize_menu( $with_style );
verify_style( ABMM_Style::sanitize( $sample ) === $styled['settings']['style'] && $styled === $data->sanitize_menu( $styled ), 'Style settings are stored and stable across saves.' );
$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ] = array( 'main' => $styled );
$GLOBALS['abmm_test_inline'] = array();
$seq = new ReflectionProperty( 'ABMM_Frontend', 'render_sequence' );
$seq->setAccessible( true );
$seq->setValue( ABMM_Frontend::instance(), 0 );
// Column title IDs come from uniqid(), so they differ on every render.
$same = function ( $html ) { return preg_replace( '/abmm-col-title-[0-9a-f]+/', 'abmm-col-title-N', $html ); };
verify_style( $same( $before ) === $same( ABMM_Frontend::instance()->render( 'main' ) ), 'Style settings never change the menu markup.' );
ABMM_Frontend::instance()->register_assets();
verify_style( ( $GLOBALS['abmm_test_inline']['abmm-frontend'] ?? '' ) === ABMM_Style::css( $sample, ABMM_Style::scope( 'main', 'abmm' ), 'abmm' ), 'Ashbi menus print their CSS with the Ashbi stylesheet.' );

// Classic menus print theirs with the classic stylesheet.
$classic = $styled;
$classic['settings']['render_mode'] = 'emm-classic';
$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ] = array( 'old' => $data->sanitize_menu( $classic ) );
$GLOBALS['abmm_test_inline'] = array();
ABMM_Frontend::instance()->register_assets();
ABMM_Classic::instance()->register_assets();
verify_style( ! isset( $GLOBALS['abmm_test_inline']['abmm-frontend'] ) && false !== strpos( $GLOBALS['abmm_test_inline']['abmm-classic'] ?? '', '.emm-header.emm-header.emm-header[data-emm-id="old"] .emm-mega__panel{height:420px' ), 'Classic menus print their CSS with the classic stylesheet.' );

// Toolbar: "Edit mega menu" for the menus on this page, administrators only.
class ABMM_Test_Admin_Bar {
	public $nodes = array();
	public function add_node( $node ) { $this->nodes[ $node['id'] ] = $node; }
}
$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ] = array( 'main' => $plain, 'second' => array_merge( $plain, array( 'title' => 'Second <b>menu</b>' ) ) );
$frontend = ABMM_Frontend::instance();
$rendered = new ReflectionProperty( 'ABMM_Frontend', 'rendered' );
$rendered->setAccessible( true );
$rendered->setValue( $frontend, array() );
$bar = new ABMM_Test_Admin_Bar();
$frontend->admin_bar_edit_link( $bar );
verify_style( array() === $bar->nodes, 'No toolbar link on pages without a menu.' );
$frontend->render( 'main' );
$frontend->admin_bar_edit_link( $bar );
verify_style( 1 === count( $bar->nodes ) && false !== strpos( $bar->nodes['abmm-edit-menu']['href'], 'page=ashbi-mega-menu&edit=main' ), 'One menu on the page links straight to its editor.' );
$frontend->render( 'second' );
$bar = new ABMM_Test_Admin_Bar();
$frontend->admin_bar_edit_link( $bar );
verify_style( 3 === count( $bar->nodes ) && 'abmm-edit-menu' === $bar->nodes['abmm-edit-menu-second']['parent'] && false === strpos( $bar->nodes['abmm-edit-menu-second']['title'], '<b>' ), 'Several menus get one escaped link each.' );
$GLOBALS['abmm_test_admin'] = false;
$bar = new ABMM_Test_Admin_Bar();
$frontend->admin_bar_edit_link( $bar );
verify_style( array() === $bar->nodes, 'Visitors and editors without manage_options see no link.' );
$GLOBALS['abmm_test_admin'] = true;
$GLOBALS['abmm_test_is_admin'] = true;
$frontend->admin_bar_edit_link( $bar );
verify_style( array() === $bar->nodes, 'No link inside wp-admin.' );
$GLOBALS['abmm_test_is_admin'] = false;

// Elementor widget, with a minimal stand-in for Elementor's classes.
eval( '
namespace Elementor;
class Controls_Manager { const SELECT = "select"; const SWITCHER = "switcher"; const RAW_HTML = "raw_html"; const COLOR = "color"; const NUMBER = "number"; const TAB_STYLE = "style"; }
class Group_Control_Typography { public static function get_type() { return "typography"; } }
abstract class Widget_Base {
	public $controls = array(); public $groups = array(); public $settings = array();
	public function start_controls_section( $id, $args ) {} public function end_controls_section() {}
	public function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
	public function add_group_control( $type, $args ) { $this->groups[ $args["name"] ] = $args; }
	public function get_settings_for_display() { return $this->settings; }
	public function get_id() { return "abc123"; }
	public function test_register() { $this->register_controls(); }
	public function test_render() { ob_start(); $this->render(); return ob_get_clean(); }
}
class Widgets_Manager { public $widgets = array(); public function register( $w ) { $this->widgets[] = $w; } }
' );
$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ] = array( 'main' => $plain );
$manager = new \Elementor\Widgets_Manager();
ABMM_Elementor::register_widget( $manager );
$widget = $manager->widgets[0];
verify_style( 'ashbi-mega-menu' === $widget->get_name() && 'Ashbi Mega Menu' === $widget->get_title(), 'The Elementor widget registers.' );
$widget->test_register();
verify_style( array( 'main' => 'Main (main)' ) === $widget->controls['menu_id']['options'] && 'main' === $widget->controls['menu_id']['default'], 'The widget lists stored menus.' );
verify_style( false !== strpos( $widget->groups['top_typography']['selector'], '{{WRAPPER}} .abmm-nav__list > .abmm-nav__item > .abmm-nav__link' ) && false !== strpos( $widget->groups['top_typography']['selector'], '{{WRAPPER}} .emm-nav__list > .emm-nav__item > .emm-nav__link' ), 'Typography controls style both looks, so Elementor global fonts apply.' );
verify_style( isset( $widget->groups['cat_typography'], $widget->groups['link_typography'], $widget->groups['desc_typography'], $widget->controls['link_hover_color'], $widget->controls['panel_background'] ), 'Category, link and description typography, colours and the dropdown background are offered.' );
$widget->settings = array( 'menu_id' => 'main', 'panel_height_mode' => 'max', 'panel_height' => 380 );
$seq->setValue( ABMM_Frontend::instance(), 0 );
$out = $widget->test_render();
verify_style( false !== strpos( $out, '<style>' ) && false !== strpos( $out, '.elementor-element-abc123 .abmm-header.abmm-header .abmm-mega__panel{max-height:380px;overflow-y:auto}' ), 'The widget scopes its dropdown height to itself.' );
verify_style( false !== strpos( $out, 'class="abmm-header' ), 'The widget renders the menu.' );
$widget->settings = array( 'menu_id' => 'main' );
verify_style( false === strpos( $widget->test_render(), '<style>' ), 'No widget CSS without a height choice.' );

echo "Style settings: sanitizing, exact CSS, unchanged markup, both looks, toolbar link and Elementor widget passed.\n";
