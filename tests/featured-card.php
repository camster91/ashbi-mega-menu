<?php
/**
 * Featured card: sanitising, backward compatibility and escaped front-end output.
 *
 * Runs the real ABMM_Data and ABMM_Frontend classes with small WordPress stubs.
 * The URL stubs follow WordPress's protocol allow-list so unsafe schemes are
 * dropped the same way esc_url_raw() and esc_url() drop them.
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

require_once dirname( __DIR__ ) . '/includes/class-abmm-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-data.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-frontend.php';

function verify_featured( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

$data = ABMM_Data::instance();
$mega = function ( $style, $extra = array() ) {
	return array_merge(
		array(
			'id'         => 'products',
			'label'      => 'Products',
			'type'       => 'mega',
			'mega_style' => $style,
			'categories' => array(
				array( 'id' => 'finance', 'title' => 'Finance', 'groups' => array( array( 'title' => 'Get paid', 'links' => array( array( 'label' => 'Invoicing', 'url' => '/invoicing/' ) ) ) ) ),
				array( 'id' => 'teams', 'title' => 'Teams', 'groups' => array( array( 'title' => 'People', 'links' => array( array( 'label' => 'Directory', 'url' => '/directory/' ) ) ) ) ),
			),
			'links'      => array( array( 'label' => 'Blog', 'url' => '/blog/' ) ),
		),
		$extra
	);
};

// Older menus have no card: the key defaults to an empty, hidden card.
$legacy = $data->sanitize_menu( array( 'title' => 'Legacy', 'items' => array( $mega( 'platforms' ), array( 'label' => 'Pricing', 'type' => 'link', 'url' => '/pricing/' ) ) ) );
verify_featured(
	array( 'show' => false, 'title' => '', 'text' => '', 'label' => '', 'url' => '', 'image_url' => '' ) === $legacy['items'][0]['featured'],
	'A mega item without a card gets an empty, hidden card.'
);
verify_featured( ! array_key_exists( 'featured', $legacy['items'][1] ), 'Plain links do not gain a featured card.' );

// A complete card survives sanitising unchanged, and again on a second pass (import after export).
$card = array(
	'show'      => true,
	'title'     => 'Northwind 2026 is here',
	'text'      => 'Live forecasts and 40 new integrations.',
	'label'     => "See what's new",
	'url'       => 'https://example.test/whats-new/',
	'image_url' => '/wp-content/uploads/launch.png',
);
$once  = $data->sanitize_menu( array( 'title' => 'Card', 'items' => array( $mega( 'platforms', array( 'featured' => $card ) ) ) ) );
$twice = $data->sanitize_menu( json_decode( json_encode( $once ), true ) );
verify_featured( $card === $once['items'][0]['featured'], 'A valid featured card is kept as entered.' );
verify_featured( $once['items'][0]['featured'] === $twice['items'][0]['featured'], 'A featured card round-trips through export and import.' );

// Unsafe values are cleaned.
$dirty = $data->sanitize_menu( array( 'title' => 'Dirty', 'items' => array( $mega( 'features', array( 'featured' => array(
	'show'      => '1',
	'title'     => '<script>alert(1)</script>Launch <b>week</b>',
	'text'      => array( 'not', 'text' ),
	'label'     => "Read\nmore",
	'url'       => 'javascript:alert(1)',
	'image_url' => 'data:text/html;base64,PHNjcmlwdD4=',
	'extra'     => 'dropped',
) ) ) ) ) );
$dirty_card = $dirty['items'][0]['featured'];
verify_featured( true === $dirty_card['show'], 'A truthy show flag becomes a boolean.' );
verify_featured( false === strpos( $dirty_card['title'], '<' ), 'Markup is stripped from the title.' );
verify_featured( '' === $dirty_card['text'], 'Structured text input is rejected.' );
verify_featured( 'Read more' === $dirty_card['label'], 'The label is a single line.' );
verify_featured( '' === $dirty_card['url'], 'A javascript: link is removed.' );
verify_featured( '' === $dirty_card['image_url'], 'A data: image URL is removed.' );
verify_featured( ! isset( $dirty_card['extra'] ), 'Unknown card keys are dropped.' );
$malformed = $data->sanitize_menu( array( 'title' => 'Malformed', 'items' => array( $mega( 'platforms', array( 'featured' => 'yes' ) ) ) ) );
verify_featured( false === $malformed['items'][0]['featured']['show'], 'A malformed card value becomes a hidden card.' );

// Front end: rendered on both mega styles, escaped, with a real link and a decorative lazy image.
$render = function ( $item ) use ( $data ) {
	$GLOBALS['abmm_test_options'][ ABMM_OPTION_KEY ] = array( 'test' => $data->sanitize_menu( array( 'title' => 'Test', 'items' => array( $item ) ) ) );
	return ABMM_Frontend::instance()->render( 'test' );
};
$hostile = array_merge( $card, array( 'title' => 'Fast & <em>fresh</em> "news"', 'url' => 'https://example.test/new/?a=1&b=2' ) );
foreach ( array( 'platforms', 'features' ) as $style ) {
	$html = $render( $mega( $style, array( 'featured' => $hostile ) ) );
	verify_featured( false !== strpos( $html, 'abmm-mega--has-featured' ), "The $style panel is marked as having a card." );
	verify_featured( 1 === preg_match( '#<a class="abmm-mega__featured" href="https://example\.test/new/\?a=1&amp;b=2">#', $html ), "The $style card is a real, escaped link." );
	verify_featured( false !== strpos( $html, 'Fast &amp; fresh &quot;news&quot;' ), "The $style card title is escaped." );
	verify_featured( false === strpos( $html, '<em>' ), "No markup leaks from the $style card." );
	verify_featured( 1 === preg_match( '#<span class="abmm-mega__featured-media"><img src="/wp-content/uploads/launch\.png" alt="" loading="lazy"#', $html ), "The $style card image is decorative and lazy." );
	foreach ( array( 'abmm-mega__featured-title', 'abmm-mega__featured-text', 'abmm-mega__featured-link' ) as $class ) {
		verify_featured( false !== strpos( $html, $class ), "The $style card renders $class." );
	}
}

// Hidden or untitled cards render nothing; a card without a link is a plain block.
foreach ( array( array( 'show' => false ), array( 'title' => '' ) ) as $change ) {
	$html = $render( $mega( 'platforms', array( 'featured' => array_merge( $card, $change ) ) ) );
	verify_featured( false === strpos( $html, 'abmm-mega__featured' ), 'A hidden or untitled card is not rendered.' );
}
$html = $render( $mega( 'platforms', array( 'featured' => array_merge( $card, array( 'url' => '', 'image_url' => '' ) ) ) ) );
verify_featured( false !== strpos( $html, '<div class="abmm-mega__featured">' ), 'A card without a link renders as a block.' );
verify_featured( false === strpos( $html, 'abmm-mega__featured-link' ) && false === strpos( $html, '<img' ), 'A card without a link or image has no link label or image.' );

// Several categories become a phone accordion whose buttons control the links and "see all" strip.
$html = $render( $mega( 'platforms' ) );
verify_featured( 2 === substr_count( $html, 'data-abmm-section-toggle' ), 'Each category of a multi-category item gets an accordion button.' );
verify_featured( 1 === preg_match( '#aria-controls="([^"]+-links)"#', $html, $m ) && false !== strpos( $html, 'id="' . $m[1] . '"' ), 'The accordion button controls the category links.' );
$single = $mega( 'platforms' );
$single['categories'] = array( $single['categories'][0] );
verify_featured( false === strpos( $render( $single ), 'data-abmm-section-toggle' ), 'A single category has no accordion button.' );

echo "Featured card sanitising and rendering checks passed.\n";
