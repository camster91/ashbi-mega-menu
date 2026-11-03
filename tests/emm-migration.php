<?php
/**
 * Switching from Easy Mega Menu: the importer only reads Easy Mega Menu's data,
 * keeps menu IDs, respects existing Ashbi menus, copies product navigation and
 * the site's styles and icons, survives an Ashbi export/import round trip, and
 * the [easy_mega_menu] takeover only answers while Easy Mega Menu is inactive.
 */
define( 'ABSPATH', __DIR__ . '/wordpress/' );
define( 'ABMM_OPTION_KEY', 'abmm_menus' );
define( 'ABMM_ARCHIVE_OPTION', 'abmm_archives' );
define( 'ABMM_CONTEXTUAL_NAV_OPTION', 'abmm_contextual_navigation_enabled' );
define( 'ABMM_VERSION', 'test' );
define( 'ABMM_PLUGIN_URL', 'https://example.test/wp-content/plugins/ashbi-mega-menu/' );
define( 'ABMM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'FS_CHMOD_FILE', 0644 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );

$abmm_tmp = sys_get_temp_dir() . '/abmm-emm-migration-' . getmypid();
define( 'WP_PLUGIN_DIR', $abmm_tmp . '/plugins' );

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}

class ABMM_Test_DB {
	public $options  = 'wp_options';
	public $postmeta = 'wp_postmeta';
	public function prepare( $query ) { return $query; }
	public function query( $query ) { return 1; }
	public function get_var( $query ) { return '1'; }
	public function get_results( $query ) {
		$rows = array();
		foreach ( $GLOBALS['abmm_meta'] as $post_id => $meta ) {
			if ( isset( $meta['_emm_product_profile'] ) ) {
				$rows[] = array( 'post_id' => $post_id, 'meta_value' => $meta['_emm_product_profile'] );
			}
		}
		return $rows;
	}
}
class ABMM_Test_FS {
	public function put_contents( $path, $data ) { return false !== file_put_contents( $path, $data ); }
}
class ABMM_Test_Redirect extends Exception {}

$GLOBALS['abmm_options']    = array();
$GLOBALS['abmm_meta']       = array();
$GLOBALS['abmm_shortcodes'] = array();
$GLOBALS['abmm_can']        = true;
$GLOBALS['wpdb']            = new ABMM_Test_DB();
$GLOBALS['wp_filesystem']   = new ABMM_Test_FS();
$_SERVER['REQUEST_URI']     = '/';

function __( $text ) { return $text; }
function _n( $single, $plural, $number ) { return 1 === (int) $number ? $single : $plural; }
function esc_html__( $text ) { return $text; }
function esc_html_e( $text ) { echo esc_html( $text ); }
function esc_attr_e( $text ) { echo esc_attr( $text ); }
function esc_attr_x( $text ) { return esc_attr( $text ); }
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
function sanitize_file_name( $name ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_unslash( $value ) { return $value; }
function wp_json_encode( $data, $flags = 0, $depth = 512 ) { return json_encode( $data, $flags, $depth ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function wp_generate_password( $length = 12 ) { return substr( md5( uniqid( '', true ) ), 0, $length ); }
function wp_generate_uuid4() { static $i = 0; return 'backup-' . ++$i; }
function is_preview() { return false; }
function get_post_status() { return 'publish'; }
function get_queried_object_id() { return 0; }
function get_the_ID() { return 0; }
function add_action() {}
function add_filter() {}
function apply_filters( $tag, $value ) { return $value; }
function wp_register_style() {}
function wp_register_script() {}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_add_inline_script() {}
function wp_cache_delete() {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_option( $key, $default = false ) { return $GLOBALS['abmm_options'][ $key ] ?? $default; }
function update_option( $key, $value ) {
	if ( isset( $GLOBALS['abmm_options'][ $key ] ) && $GLOBALS['abmm_options'][ $key ] === $value ) { return false; }
	$GLOBALS['abmm_options'][ $key ] = $value;
	return true;
}
function get_post_meta( $id, $key ) { return $GLOBALS['abmm_meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['abmm_meta'][ $id ][ $key ] = $value; return true; }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['abmm_tmp'] . '/uploads', 'baseurl' => 'https://example.test/wp-content/uploads', 'error' => false ); }
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
function WP_Filesystem() { return true; }
function get_plugins() { return $GLOBALS['abmm_plugins']; }
function shortcode_exists( $tag ) { return isset( $GLOBALS['abmm_shortcodes'][ $tag ] ); }
function add_shortcode( $tag, $callback ) { $GLOBALS['abmm_shortcodes'][ $tag ] = $callback; }
function shortcode_atts( $pairs, $atts ) { return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) ); }
function current_user_can() { return $GLOBALS['abmm_can']; }
function check_admin_referer( $action ) { if ( 'valid' !== ( $_POST['_wpnonce'] ?? '' ) ) { throw new ABMM_Test_Redirect( 'nonce' ); } return true; }
function wp_die( $message ) { throw new ABMM_Test_Redirect( 'die' ); }
function wp_create_nonce() { return 'n'; }
function set_transient( $key, $value ) { $GLOBALS['abmm_transients'][ $key ] = $value; return true; }
function wp_delete_file( $file ) { @unlink( $file ); }
function get_current_user_id() { return 1; }
function wp_safe_redirect( $url ) { throw new ABMM_Test_Redirect( $url ); }
function abmm_contextual_navigation_enabled() { return (bool) get_option( ABMM_CONTEXTUAL_NAV_OPTION, false ); }

$GLOBALS['abmm_tmp'] = $abmm_tmp;
require_once dirname( __DIR__ ) . '/includes/class-abmm-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-data.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-import-export.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-option-store.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-product-profiles.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-product-branding.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-page-context.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-frontend.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-classic.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-classic-icons.php';
require_once dirname( __DIR__ ) . '/includes/class-abmm-emm-migration.php';

function verify_migration( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

// A fake Easy Mega Menu install on disk: its stylesheet and sprite.
$plugin_dir = WP_PLUGIN_DIR . '/WordPress-Mega-Menu-9.9.9';
@mkdir( $plugin_dir . '/assets/css', 0777, true );
@mkdir( $plugin_dir . '/assets/icons', 0777, true );
file_put_contents( $plugin_dir . '/assets/css/frontend.css', ".emm-header{color:red}\n" );
copy( __DIR__ . '/fixtures/emm/emm-sprite.svg', $plugin_dir . '/assets/icons/emm-sprite.svg' );
$GLOBALS['abmm_plugins'] = array( 'WordPress-Mega-Menu-9.9.9/mega-menu-plugin.php' => array( 'Name' => 'Easy Mega Menu', 'Version' => '1.6.76' ) );

// Easy Mega Menu's stored data.
$export = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/emm/emm-export.json' ), true );
$GLOBALS['abmm_options']['emm_menus']            = $export['menus'];
$GLOBALS['abmm_options']['emm_product_profiles'] = array(
	'product_a' => array( 'title' => 'Product A', 'label' => 'Product A', 'url' => 'https://example.test/a/', 'root_page_id' => 12, 'rail_style' => 'underline', 'links' => array( array( 'label' => 'Features', 'url' => 'https://example.test/a/features/' ) ), 'cta' => array( 'show' => true, 'label' => 'Try it', 'url' => 'https://example.test/a/try/' ) ),
	'product_b' => array( 'title' => 'Product B', 'label' => 'Product B', 'url' => 'https://example.test/b/', 'root_page_id' => 13, 'rail_style' => 'pills', 'links' => array(), 'cta' => array() ),
	'product_c' => array( 'title' => 'Product C', 'label' => 'Product C from Easy Mega Menu', 'url' => 'https://example.test/c/', 'root_page_id' => 14, 'rail_style' => 'underline', 'links' => array(), 'cta' => array() ),
);
$GLOBALS['abmm_options']['emm_product_branding'] = array(
	'product_a' => array( 'enabled' => true, 'mode' => 'product_text', 'shared_menu_id' => 'main_nav', 'logo_url' => 'https://example.test/logo.svg', 'alt' => 'Example', 'logo_height' => 42, 'wordmark_url' => 'https://example.test/a.svg', 'wordmark_height' => 21, 'desktop_interaction' => 'hover', 'show_utility_links' => true, 'about_url' => 'https://example.test/about/', 'contact_url' => 'https://example.test/contact/' ),
	// Decorates a menu whose ID an Ashbi menu already uses: not copied while that menu is kept.
	'product_b' => array( 'enabled' => true, 'mode' => 'product_text', 'shared_menu_id' => 'product_secondary', 'logo_url' => 'https://example.test/logo.svg', 'alt' => 'Example' ),
);
// The administrator's own Ashbi profile, with an ID Easy Mega Menu also uses.
$GLOBALS['abmm_options']['abmm_product_profiles'] = array( 'product_c' => array( 'title' => 'Mine', 'label' => 'My product C', 'url' => 'https://example.test/mine/' ) );
$GLOBALS['abmm_meta'][42] = array( '_emm_product_profile' => 'product_a' );
$GLOBALS['abmm_meta'][43] = array( '_emm_product_profile' => 'product_a', '_abmm_product_profile' => 'kept' );
// An Ashbi menu that shares an ID with an Easy Mega Menu menu.
$GLOBALS['abmm_options'][ ABMM_OPTION_KEY ] = array( 'product_secondary' => ABMM_Data::instance()->sanitize_menu( array( 'title' => 'Mine', 'items' => array() ) ) );
$source_before = array_intersect_key( $GLOBALS['abmm_options'], array_flip( array( 'emm_menus', 'emm_product_profiles', 'emm_product_branding' ) ) );
$files_before  = array( md5_file( $plugin_dir . '/assets/css/frontend.css' ), md5_file( $plugin_dir . '/assets/icons/emm-sprite.svg' ) );

$migration = ABMM_EMM_Migration::instance();

// Preview changes nothing and reports the conflict.
$options_before = $GLOBALS['abmm_options'];
$preview        = $migration->preview();
verify_migration( $options_before === $GLOBALS['abmm_options'], 'Preview stores nothing.' );
$rows = array_column( $preview['menus'], null, 'id' );
verify_migration( 2 === count( $rows ) && $rows['product_secondary']['conflict'] && ! $rows['main_nav']['conflict'], 'Preview lists both menus and flags the ID conflict.' );
verify_migration( $preview['assets_ready'] && 3 === $preview['profiles'] && 2 === $preview['branding'] && 2 === $preview['assignments'], 'Preview counts styles, profiles, branding and page assignments.' );
verify_migration( in_array( 'Icon "not-a-real-icon" is not in the copied icon set; the link icon is shown instead.', $rows['main_nav']['issues'], true ), 'Preview lists icons that are not available.' );

// Import: IDs kept, existing Ashbi menu kept, everything else copied.
@mkdir( ABMM_Classic::upload_dir(), 0777, true );
file_put_contents( ABMM_Classic::upload_dir() . '/emm-frontend-stale0000.css', 'old' );
$result = $migration->import( false );
verify_migration( ! is_wp_error( $result ), 'Import succeeds.' );
$menus = $GLOBALS['abmm_options'][ ABMM_OPTION_KEY ];
verify_migration( isset( $menus['main_nav'] ) && ABMM_Classic::is_classic( $menus['main_nav'] ), 'Imported menus keep their IDs and use classic mode.' );
verify_migration( 'Mine' === $menus['product_secondary']['title'] && array( 'product_secondary' ) === $result['skipped'], 'An existing Ashbi menu with the same ID is kept.' );
verify_migration( ! empty( $GLOBALS['abmm_options']['abmm_import_backups'] ), 'A recovery snapshot is saved before importing.' );
verify_migration( $source_before === array_intersect_key( $GLOBALS['abmm_options'], $source_before ), "Easy Mega Menu's options are unchanged." );
verify_migration( $files_before === array( md5_file( $plugin_dir . '/assets/css/frontend.css' ), md5_file( $plugin_dir . '/assets/icons/emm-sprite.svg' ) ), "Easy Mega Menu's files are unchanged." );
verify_migration( 'product_a' === ( $GLOBALS['abmm_meta'][42]['_emm_product_profile'] ?? '' ), "Easy Mega Menu's page meta is unchanged." );

// Product navigation, branding and page assignments.
verify_migration( true === get_option( ABMM_CONTEXTUAL_NAV_OPTION ), 'Contextual navigation is turned on for imported profiles.' );
$profiles = get_option( ABMM_Product_Profiles::OPTION_KEY );
verify_migration( 'Product A' === $profiles['product_a']['label'] && 12 === $profiles['product_a']['root_page_id'] && 'Try it' === $profiles['product_a']['cta']['label'], 'Product profiles are copied with their hub page and CTA.' );
$branding = get_option( ABMM_Product_Branding::OPTION_KEY );
verify_migration( 'product_text' === $branding['product_a']['mode'] && 21 === $branding['product_a']['wordmark_height'] && 'main_nav' === $branding['product_a']['shared_menu_id'], 'Product branding is copied.' );
verify_migration( ! isset( $branding['product_b'] ) && array( 'product_b' ) === $result['kept_branding'], 'Branding for a menu that was not imported is not copied onto the unrelated Ashbi menu.' );
verify_migration( 'My product C' === $profiles['product_c']['label'] && array( 'product_c' ) === $result['kept_profiles'] && 'Product B' === $profiles['product_b']['label'], 'The administrator\'s own profile is kept; the others are copied.' );
verify_migration( 2 === $result['profiles'] && 1 === $result['branding'], 'Only records actually written are counted.' );
verify_migration( 'product_a' === $GLOBALS['abmm_meta'][42]['_abmm_product_profile'] && 'kept' === $GLOBALS['abmm_meta'][43]['_abmm_product_profile'], 'Page assignments are copied without replacing Ashbi ones.' );

// Styles and icons copied into uploads, exactly.
$assets = get_option( ABMM_Classic::ASSETS_OPTION );
$dir    = ABMM_Classic::upload_dir();
verify_migration( $result['assets']['copied'] && '1.6.76' === $assets['version'], 'Styles and icons are copied and versioned.' );
verify_migration( file_get_contents( $dir . '/' . $assets['css'] ) === ".emm-header{color:red}\n", 'The stylesheet copy is byte for byte.' );
verify_migration( 1 === preg_match( '/^emm-sprite-[0-9a-f]{10}\.svg$/', $assets['sprite'] ) && 1 === preg_match( '/^emm-frontend-[0-9a-f]{10}\.css$/', $assets['css'] ), 'Copied files are named by their content, so a changed file gets a new URL.' );
verify_migration( isset( ABMM_Classic_Icons::sprite_keys( $dir . '/' . $assets['sprite'] )['wallet'] ) && in_array( 'wallet', $assets['keys'], true ), 'The sprite copy keeps its icons and the icon list is stored.' );
verify_migration( ! file_exists( $dir . '/emm-frontend-stale0000.css' ), 'Copies from earlier imports are removed.' );

// A changed stylesheet gets a new file; the previous copy is removed.
$first_css = $assets['css'];
file_put_contents( $plugin_dir . '/assets/css/frontend.css', ".emm-header{color:blue}\n" );
$migration->copy_assets();
$assets = get_option( ABMM_Classic::ASSETS_OPTION );
verify_migration( $first_css !== $assets['css'] && ! file_exists( $dir . '/' . $first_css ) && file_exists( $dir . '/' . $assets['css'] ) && file_exists( $dir . '/' . $assets['sprite'] ), 'A changed stylesheet replaces the old copy.' );
file_put_contents( $plugin_dir . '/assets/css/frontend.css', ".emm-header{color:red}\n" );

// Re-importing refreshes what earlier imports created, and only that.
$GLOBALS['abmm_options']['emm_product_profiles']['product_a']['cta']['label'] = 'Start a trial';
$GLOBALS['abmm_options']['emm_product_profiles']['product_c']['label']        = 'Changed in Easy Mega Menu';
$GLOBALS['abmm_meta'][42]['_emm_product_profile']                             = 'product_b';
$source_before = array_intersect_key( $GLOBALS['abmm_options'], $source_before );
$migration->import( false );
$profiles = get_option( ABMM_Product_Profiles::OPTION_KEY );
verify_migration( 'Start a trial' === $profiles['product_a']['cta']['label'] && 'My product C' === $profiles['product_c']['label'], 'Re-importing updates imported profiles and keeps your own.' );
verify_migration( 'product_b' === $GLOBALS['abmm_meta'][42]['_abmm_product_profile'] && 'kept' === $GLOBALS['abmm_meta'][43]['_abmm_product_profile'], 'Re-importing updates imported page assignments and keeps your own.' );

// Overwrite replaces the conflicting Ashbi menu when asked; a second import updates classic menus in place.
$result = $migration->import( true );
verify_migration( ABMM_Classic::is_classic( $GLOBALS['abmm_options'][ ABMM_OPTION_KEY ]['product_secondary'] ) && array() === $result['skipped'], 'Overwrite replaces an Ashbi menu with the same ID.' );
$branding = get_option( ABMM_Product_Branding::OPTION_KEY );
$profiles = get_option( ABMM_Product_Profiles::OPTION_KEY );
verify_migration( 'product_secondary' === $branding['product_b']['shared_menu_id'] && 'Changed in Easy Mega Menu' === $profiles['product_c']['label'], 'With Replace, branding follows its now-imported menu and conflicting profiles are replaced.' );
verify_migration( $source_before === array_intersect_key( $GLOBALS['abmm_options'], $source_before ), 'Easy Mega Menu\'s options are still unchanged.' );
$again = $migration->import( false );
verify_migration( array() === $again['skipped'] && 'updated' === $again['menus']['main_nav']['action'], 'Re-importing updates earlier classic imports.' );

// Round trip through Ashbi's own export and import.
$before   = $GLOBALS['abmm_options'][ ABMM_OPTION_KEY ];
$exported = ABMM_Import_Export::instance()->export_json();
$GLOBALS['abmm_options'][ ABMM_OPTION_KEY ] = array();
ABMM_Import_Export::instance()->import_json( json_decode( wp_json_encode( $exported ), true ), 'merge' );
verify_migration( $before === $GLOBALS['abmm_options'][ ABMM_OPTION_KEY ], 'Classic menus survive an Ashbi export and import unchanged.' );

// Takeover: off by default; never while Easy Mega Menu is active.
$migration->maybe_take_over();
verify_migration( ! shortcode_exists( 'easy_mega_menu' ), 'The takeover is off by default.' );
update_option( ABMM_EMM_Migration::TAKEOVER_OPTION, true );
$GLOBALS['abmm_shortcodes']['easy_mega_menu'] = 'emm_original';
$migration->maybe_take_over();
verify_migration( 'emm_original' === $GLOBALS['abmm_shortcodes']['easy_mega_menu'], 'An active Easy Mega Menu keeps its own shortcode.' );
unset( $GLOBALS['abmm_shortcodes']['easy_mega_menu'] );
$migration->maybe_take_over();
verify_migration( array( $migration, 'shortcode' ) === $GLOBALS['abmm_shortcodes']['easy_mega_menu'] && function_exists( 'emm_render_menu' ) && function_exists( 'emm_get_menu' ), 'With Easy Mega Menu inactive, Ashbi answers [easy_mega_menu] and the theme helpers.' );
$html = $migration->shortcode( array( 'id' => 'main_nav', 'context' => 'page', 'presentation' => 'unified', 'review_post_id' => '7' ) );
verify_migration( 0 === strpos( trim( $html ), '<header class="emm-header' ) && false !== strpos( $html, 'data-emm-id="main_nav"' ), 'The shortcode renders the classic header.' );
$same = function ( $markup ) { return trim( preg_replace( '/emm-main_nav-\d+/', 'emm-main_nav-N', $markup ) ); };
verify_migration( $same( $html ) === $same( emm_get_menu( 'main_nav', array( 'context' => 'page', 'presentation' => 'unified', 'review_post_id' => 7 ) ) ), 'emm_get_menu() renders the same header.' );
verify_migration( '' === $migration->shortcode( array( 'id' => 'does_not_exist' ) ), 'A missing menu renders nothing, as before.' );
verify_migration( '' === $migration->shortcode( array( 'id' => array( 'x' ) ) ), 'A non-scalar ID renders nothing.' );

// A failed import shows its real reason.
$saved_menus = $GLOBALS['abmm_options']['emm_menus'];
$GLOBALS['abmm_options']['emm_menus'] = array();
$_POST = array( '_wpnonce' => 'valid' );
try { $migration->handle_import(); } catch ( ABMM_Test_Redirect $stop ) { verify_migration( false !== strpos( $stop->getMessage(), 'abmm_notice=error' ), 'A failed import redirects with an error notice.' ); }
verify_migration( 'Easy Mega Menu has no menus to import.' === ( $GLOBALS['abmm_transients']['abmm_emm_import_result_1']['error'] ?? '' ), 'The failure reason is kept for the notice.' );
$GLOBALS['abmm_options']['emm_menus'] = $saved_menus;

// Admin actions need the capability and a valid nonce.
$GLOBALS['abmm_can'] = false;
try { $migration->handle_import(); verify_migration( false, 'unreachable' ); } catch ( ABMM_Test_Redirect $stop ) { verify_migration( 'die' === $stop->getMessage(), 'Import requires manage_options.' ); }
$GLOBALS['abmm_can'] = true;
$_POST = array( '_wpnonce' => 'stale', 'takeover' => '' );
try { $migration->handle_takeover(); verify_migration( false, 'unreachable' ); } catch ( ABMM_Test_Redirect $stop ) { verify_migration( 'nonce' === $stop->getMessage() && ABMM_EMM_Migration::takeover_enabled(), 'The takeover setting requires a valid nonce.' ); }
$_POST = array( '_wpnonce' => 'valid' );
try { $migration->handle_takeover(); } catch ( ABMM_Test_Redirect $stop ) { verify_migration( ! ABMM_EMM_Migration::takeover_enabled(), 'The takeover can be turned off again.' ); }

// While Easy Mega Menu still runs a site with imported menus, Ashbi adds no body classes.
define( 'EMM_VERSION', '1.6.76' );
verify_migration( array( 'page' ) === ABMM_Product_Branding::instance()->body_class( array( 'page' ) ) && array( 'page' ) === ABMM_Classic::instance()->body_class( array( 'page' ) ), 'No Ashbi body classes while Easy Mega Menu is active.' );

echo "Easy Mega Menu migration: read-only import, ID conflicts, product navigation, asset copy, round trip and takeover passed.\n";
