<?php
/**
 * Switching from Easy Mega Menu: the importer only reads Easy Mega Menu's data,
 * keeps menu IDs, respects existing Ashbi menus, copies product navigation and
 * the site's styles and icons, survives an Ashbi export/import round trip, and
 * old [easy_mega_menu] codes are answered whenever Easy Mega Menu is not running,
 * and Finish switching rewrites them with a backup and an undo.
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
	public $last_error = '';
	public $options  = 'wp_options';
	public $postmeta = 'wp_postmeta';
	public $posts    = 'wp_posts';
	public function esc_like( $text ) { return addcslashes( $text, '_%\\' ); }
	public function prepare( $query, ...$args ) { $GLOBALS['abmm_last_args'] = $args; return $query; }
	public function query( $query ) {
		if ( 0 !== strpos( $query, 'UPDATE ' ) ) {
			return 1;
		}
		list( $after, $id, $before ) = $GLOBALS['abmm_last_args'];
		if ( 0 === strpos( $query, 'UPDATE wp_posts ' ) ) {
			$value =& $GLOBALS['abmm_test_posts'][ $id ];
		} elseif ( 0 === strpos( $query, 'UPDATE wp_postmeta ' ) ) {
			$value =& $GLOBALS['abmm_test_elementor_rows'][ $id ]['value'];
		} else {
			$value =& $GLOBALS['abmm_options'][ $id ];
			$after  = maybe_unserialize( $after );
			$before = maybe_unserialize( $before );
		}
		if ( isset( $GLOBALS['abmm_test_concurrent_edits'][ $id ] ) ) {
			$value = $GLOBALS['abmm_test_concurrent_edits'][ $id ];
		}
		if ( in_array( $id, $GLOBALS['abmm_test_failed_rows'] ?? array(), true ) ) {
			return false;
		}
		if ( $before !== $value ) {
			return 0;
		}
		$value = $after;
		return 1;
	}
	public function get_var( $query ) {
		if ( isset( $GLOBALS['abmm_test_content'] ) ) {
			foreach ( $GLOBALS['abmm_last_args'] as $arg ) {
				if ( 0 === strpos( (string) $arg, '%id=' ) && false !== strpos( $GLOBALS['abmm_test_content'], stripslashes( trim( $arg, '%' ) ) ) ) {
					return '7';
				}
			}
			return null;
		}
		if ( 0 === strpos( $query, 'SELECT post_content' ) ) {
			return $GLOBALS['abmm_test_posts'][ $GLOBALS['abmm_last_args'][0] ] ?? null;
		}
		if ( 0 === strpos( $query, 'SELECT meta_value' ) ) {
			return $GLOBALS['abmm_test_elementor_rows'][ $GLOBALS['abmm_last_args'][0] ]['value'] ?? null;
		}
		return false !== strpos( $query, ' LIKE ' ) ? ( $GLOBALS['abmm_content_uses_shortcode'] ? '7' : null ) : '1';
	}
	public function get_col( $query ) {
		return $GLOBALS['abmm_test_elementor'] ?? array();
	}
	public function get_results( $query ) {
		if ( isset( $GLOBALS['abmm_test_failed_scan'] ) && false !== strpos( $query, $GLOBALS['abmm_test_failed_scan'] ) ) {
			$this->last_error = 'Simulated read failure';
			return null;
		}
		$this->last_error = '';
		if ( false !== strpos( $query, 'SELECT ID, post_content' ) ) {
			$rows = array();
			foreach ( $GLOBALS['abmm_test_posts'] ?? array() as $id => $content ) {
				if ( false !== strpos( $content, 'easy_mega_menu' ) ) {
					$rows[] = array( 'ID' => $id, 'post_content' => $content );
				}
			}
			return $rows;
		}
		if ( false !== strpos( $query, 'pm.meta_id' ) ) {
			$rows = array();
			foreach ( $GLOBALS['abmm_test_elementor_rows'] ?? array() as $id => $row ) {
				if ( false !== strpos( $row['value'], 'easy_mega_menu' ) ) {
					$rows[] = array( 'meta_id' => $id, 'post_id' => $row['post'], 'meta_value' => $row['value'] );
				}
			}
			return $rows;
		}
		if ( false !== strpos( $query, 'SELECT option_name' ) ) {
			return array_map( function ( $name ) { return array( 'option_name' => $name, 'option_value' => maybe_serialize( get_option( $name ) ) ); }, $GLOBALS['abmm_test_widgets'] ?? array() );
		}
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
$GLOBALS['abmm_content_uses_shortcode'] = true;
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
function get_bloginfo() { return 'Example Site'; }
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
function maybe_serialize( $value ) { return is_array( $value ) ? serialize( $value ) : $value; }
function maybe_unserialize( $value ) { return is_string( $value ) && 0 === strpos( $value, 'a:' ) ? unserialize( $value ) : $value; }
function get_option( $key, $default = false ) { return $GLOBALS['abmm_options'][ $key ] ?? $default; }
function delete_option( $key ) { unset( $GLOBALS['abmm_options'][ $key ] ); return true; }
function delete_transient( $key ) { unset( $GLOBALS['abmm_transients'][ $key ] ); return true; }
function clean_post_cache( $id ) { $GLOBALS['abmm_cleaned_posts'][] = $id; }
function add_submenu_page( $parent, $title, $label, $cap, $slug ) { $GLOBALS['abmm_pages'][] = array( $parent, $label ); }
function add_management_page( $title, $label, $cap, $slug ) { $GLOBALS['abmm_pages'][] = array( 'tools.php', $label ); }
function update_option( $key, $value, $autoload = null ) {
	if ( $key === ( $GLOBALS['abmm_test_failed_option'] ?? null ) ) { return false; }
	if ( ! empty( $GLOBALS['abmm_test_backup_fails'] ) && ABMM_EMM_Migration::BACKUP_OPTION === $key ) { return false; }
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
if ( ! function_exists( 'get_transient' ) ) { function get_transient( $key ) { return $GLOBALS['abmm_transients'][ $key ] ?? false; } }
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
require_once dirname( __DIR__ ) . '/includes/class-abmm-style.php';
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

// A failed branding save must leave a newly imported profile eligible for a normal retry.
$GLOBALS['abmm_options']['emm_product_profiles']['retry_profile'] = array( 'label' => 'First label' );
$GLOBALS['abmm_options']['emm_product_branding']['retry_profile'] = $GLOBALS['abmm_options']['emm_product_branding']['product_a'];
$GLOBALS['abmm_test_failed_option'] = ABMM_Product_Branding::OPTION_KEY;
$partial_import = $migration->import();
unset( $GLOBALS['abmm_test_failed_option'] );
verify_migration( is_wp_error( $partial_import ) && 'First label' === get_option( ABMM_Product_Profiles::OPTION_KEY )['retry_profile']['label'], 'Profiles can save before branding fails.' );
$GLOBALS['abmm_options']['emm_product_profiles']['retry_profile']['label'] = 'Updated label';
$retry_import = $migration->import();
verify_migration( ! is_wp_error( $retry_import ) && 'Updated label' === get_option( ABMM_Product_Profiles::OPTION_KEY )['retry_profile']['label'], 'A normal retry updates the profile created by the partial import.' );
foreach ( array( 'emm_product_profiles', 'emm_product_branding', ABMM_Product_Profiles::OPTION_KEY, ABMM_Product_Branding::OPTION_KEY ) as $option ) {
	unset( $GLOBALS['abmm_options'][ $option ]['retry_profile'] );
}

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

// An option write failure cannot remove the stylesheet that the site still uses.
$GLOBALS['abmm_test_failed_option'] = ABMM_Classic::ASSETS_OPTION;
file_put_contents( $plugin_dir . '/assets/css/frontend.css', ".emm-header{color:blue}\n" );
$failed_copy = $migration->copy_assets();
unset( $GLOBALS['abmm_test_failed_option'] );
verify_migration( ! $failed_copy['copied'] && $assets === get_option( ABMM_Classic::ASSETS_OPTION ) && file_exists( $dir . '/' . $assets['css'] ), 'An unsuccessful asset update preserves the recorded stylesheet.' );

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

// Keep a removed assignment removed and ignore a reference to a missing profile.
$GLOBALS['abmm_meta'][44] = array( '_emm_product_profile' => 'missing_profile' );
unset( $GLOBALS['abmm_meta'][42]['_abmm_product_profile'] );
$migration->import( false );
verify_migration( '' === get_post_meta( 42, ABMM_Page_Context::META_KEY ) && '' === get_post_meta( 44, ABMM_Page_Context::META_KEY ), 'Re-import preserves intentional removals and skips missing profiles.' );
unset( $GLOBALS['abmm_meta'][44] );
$GLOBALS['abmm_meta'][42]['_abmm_product_profile'] = 'product_b';

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

// Old codes: never while Easy Mega Menu is active, always otherwise, with no switch to manage.
$GLOBALS['abmm_shortcodes']['easy_mega_menu'] = 'emm_original';
$migration->answer_old_shortcodes();
verify_migration( 'emm_original' === $GLOBALS['abmm_shortcodes']['easy_mega_menu'], 'An active Easy Mega Menu keeps its own shortcode.' );
unset( $GLOBALS['abmm_shortcodes']['easy_mega_menu'] );
verify_migration( ! get_option( ABMM_EMM_Migration::TAKEOVER_OPTION ), 'No old setting is on.' );
$migration->answer_old_shortcodes();
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
$_POST = array( '_wpnonce' => 'stale' );
foreach ( array( 'handle_finish', 'handle_undo' ) as $abmm_handler ) {
	try { $migration->$abmm_handler(); verify_migration( false, 'unreachable' ); } catch ( ABMM_Test_Redirect $stop ) { verify_migration( 'nonce' === $stop->getMessage(), $abmm_handler . ' requires a valid nonce.' ); }
	$GLOBALS['abmm_can'] = false;
	try { $migration->$abmm_handler(); verify_migration( false, 'unreachable' ); } catch ( ABMM_Test_Redirect $stop ) { verify_migration( 'die' === $stop->getMessage(), $abmm_handler . ' requires manage_options.' ); }
	$GLOBALS['abmm_can'] = true;
}
$_POST = array( '_wpnonce' => 'valid' );

// Nothing warns about a code that is answered; only a menu that was never copied matters.
class ABMM_Test_Screen { public $id; public function __construct( $id ) { $this->id = $id; } }
function get_current_screen() { return new ABMM_Test_Screen( $GLOBALS['abmm_screen'] ); }
$notice = function ( $screen ) use ( $migration ) { $GLOBALS['abmm_screen'] = $screen; ob_start(); $migration->missing_menus_notice(); return ob_get_clean(); };
verify_migration( '' === $notice( 'dashboard' ), 'No warning when every placed menu is copied.' );

// The import screen names the menus pages really use; menu_demo must not match menu_demo-copy.
$GLOBALS['abmm_test_content'] = '[easy_mega_menu id="menu_demo-copy"] [easy_mega_menu id=\\"product_nav\\"]';
verify_migration( array( 'menu_demo-copy', 'product_nav' ) === $migration->menus_in_use( array( 'menu_demo', 'menu_demo-copy', 'product_nav', 'unused' ) ), 'Only menus placed on pages are listed, in plain or Elementor-escaped quotes.' );
$GLOBALS['abmm_test_content'] = '';
verify_migration( array( 'menu_demo-copy' ) === $migration->menus_in_use( array( 'menu_demo-copy' ) ), 'The in-use list is cached.' );
unset( $GLOBALS['abmm_transients']['abmm_emm_menus_in_use'] );
verify_migration( array() === $migration->menus_in_use( array( 'menu_demo-copy' ) ), 'Nothing is listed when no page places a menu.' );
unset( $GLOBALS['abmm_test_content'], $GLOBALS['abmm_transients']['abmm_emm_menus_in_use'] );

// Classic options stay with a menu switched to the Ashbi look, and no other menu gains them.
$plain = ABMM_Data::instance()->sanitize_menu( array( 'title' => 'Plain', 'items' => array(), 'settings' => array( 'classic_edge_bleed' => 8, 'classic_toggle_label' => 'No' ) ) );
verify_migration( ! array_filter( array_keys( $plain['settings'] ), function ( $key ) { return 0 === strpos( $key, 'classic_' ) || 'render_mode' === $key; } ), 'An ordinary menu gains no Classic settings.' );
$switched = ABMM_Data::instance()->sanitize_menu( array( 'title' => 'Switched', 'items' => array(), 'settings' => array( 'classic_origin' => true, 'classic_edge_bleed' => 8, 'classic_toggle_label' => '<b>Products</b>' ) ) );
verify_migration( true === $switched['settings']['classic_origin'] && ! isset( $switched['settings']['render_mode'] ), 'A switched menu records its origin and uses the Ashbi look.' );
verify_migration( 8 === $switched['settings']['classic_edge_bleed'] && 'Products' === $switched['settings']['classic_toggle_label'], 'A switched menu keeps its Classic options, sanitized.' );
$classic_menu = ABMM_Data::instance()->sanitize_menu( array( 'title' => 'Classic', 'items' => array(), 'settings' => array( 'render_mode' => 'emm-classic', 'classic_origin' => true, 'classic_toggle_label' => str_repeat( 'x', 60 ) ) ) );
verify_migration( 'emm-classic' === $classic_menu['settings']['render_mode'] && ! isset( $classic_menu['settings']['classic_origin'] ), 'A Classic menu needs no origin flag.' );
verify_migration( 40 === strlen( $classic_menu['settings']['classic_toggle_label'] ), 'The toggle label is capped.' );
verify_migration( 'Clean' === ABMM_Data::design_presets()['classic']['label'], 'The default preset no longer shares a name with the Classic look.' );

// A copied menu whose links have no IDs of their own does not look edited.
$abmm_no_ids = array( 'title' => 'No IDs', 'items' => array( array( 'label' => 'Shop', 'url' => '#', 'type' => 'mega', 'mega_style' => 'simple', 'links' => array( array( 'label' => 'One', 'url' => 'https://example.test/one/' ) ) ) ) );
$GLOBALS['abmm_options']['emm_menus']['no_ids'] = $abmm_no_ids;
$migration->import( false, array( 'no_ids' ) );
$abmm_rows = array_column( $migration->preview()['menus'], null, 'id' );
verify_migration( ! empty( $abmm_rows['no_ids']['exists'] ) && empty( $abmm_rows['no_ids']['edited'] ), 'A fresh copy of a menu with no item IDs is not reported as edited.' );
unset( $GLOBALS['abmm_options']['emm_menus']['no_ids'] );
ABMM_Data::instance()->archive( 'no_ids' );

// A re-import keeps edits unless a row is ticked, and each replaced menu can be rolled back.
$edited                 = ABMM_Data::instance()->get_all();
$edited['main_nav']['title'] = 'My edits';
ABMM_Data::instance()->replace_all( $edited );
$rows = array_column( $migration->preview()['menus'], null, 'id' );
verify_migration( ! empty( $rows['main_nav']['edited'] ), 'Preview spots an imported menu that no longer matches Easy Mega Menu.' );
$_POST = array( '_wpnonce' => 'valid', 'abmm_select_menus' => '1' );
try { $migration->handle_import(); } catch ( ABMM_Test_Redirect $stop ) { verify_migration( false !== strpos( $stop->getMessage(), 'abmm_notice=imported' ), 'Importing nothing still finishes.' ); }
verify_migration( 'My edits' === ABMM_Data::instance()->get_all()['main_nav']['title'], 'An unticked row keeps its edits.' );
$_POST = array( '_wpnonce' => 'valid', 'abmm_select_menus' => '1', 'menus' => array( 'main_nav' ) );
try { $migration->handle_import(); } catch ( ABMM_Test_Redirect $stop ) { verify_migration( false !== strpos( $stop->getMessage(), 'abmm_notice=imported' ), 'Importing a ticked row finishes.' ); }
verify_migration( 'My edits' !== ABMM_Data::instance()->get_all()['main_nav']['title'], 'A ticked row replaces the edits.' );
verify_migration( 'My edits' === ( ABMM_Data::instance()->menu_versions( 'main_nav' )[0]['title'] ?? '' ), 'The replaced menu keeps a saved version.' );

// Finish switching will not blank a placed header.
$GLOBALS['abmm_test_content'] = '[easy_mega_menu id="not_imported"]';
$saved_source                 = $GLOBALS['abmm_options']['emm_menus'];
$GLOBALS['abmm_options']['emm_menus']['not_imported'] = $saved_source['main_nav'];
unset( $GLOBALS['abmm_transients']['abmm_emm_menus_in_use'] );
verify_migration( array( 'not_imported' ) === $migration->placed_but_not_imported( true ), 'Placed menus that were not imported are found.' );
verify_migration( $migration->is_source_menu( 'not_imported' ) && ! $migration->is_source_menu( 'unknown_id' ), 'Easy Mega Menu IDs are recognised.' );
verify_migration( is_wp_error( $migration->finish_switching() ) && ! get_option( ABMM_EMM_Migration::BACKUP_OPTION ), 'Finish switching stops while a placed menu is not copied.' );
verify_migration( false !== strpos( $notice( 'dashboard' ), 'not_imported' ), 'Administrators are told which headers are empty.' );
verify_migration( '' === $notice( 'edit-post' ) && '' === $notice( 'mega-menu_page_' . ABMM_EMM_Migration::PAGE_SLUG ), 'Other screens and the import screen stay quiet.' );
$GLOBALS['abmm_can'] = false;
verify_migration( '' === $notice( 'dashboard' ), 'Only administrators see the warning.' );
$GLOBALS['abmm_can'] = true;
$GLOBALS['abmm_options']['emm_menus'] = $saved_source;
unset( $GLOBALS['abmm_test_content'], $GLOBALS['abmm_transients']['abmm_emm_menus_in_use'] );

// The rewrite changes the shortcode's name and nothing else, in every form it is stored.
$abmm_rewrites = array(
	'[easy_mega_menu id="a"]'                                  => '[ashbi_mega_menu id="a"]',
	'[easy_mega_menu]'                                         => '[ashbi_mega_menu]',
	'[easy_mega_menu/]'                                        => '[ashbi_mega_menu/]',
	"[easy_mega_menu\tid='a']"                                => "[ashbi_mega_menu\tid='a']",
	'[easy_mega_menu id=\\"a\\" context=\\"page\\"]'   => '[ashbi_mega_menu id=\\"a\\" context=\\"page\\"]',
	'[easy_mega_menu id="a"]x[\/easy_mega_menu]'              => '[ashbi_mega_menu id="a"]x[\/ashbi_mega_menu]',
	'[easy_mega_menu id="a"]x[/easy_mega_menu]'                => '[ashbi_mega_menu id="a"]x[/ashbi_mega_menu]',
	'{"shortcode":"[easy_mega_menu id=\\"a\\"]"}'          => '{"shortcode":"[ashbi_mega_menu id=\\"a\\"]"}',
	'[easy_mega_menu_extra id="a"]'                            => '[easy_mega_menu_extra id="a"]',
	'easy_mega_menu is a word in a sentence'                   => 'easy_mega_menu is a word in a sentence',
	'[ashbi_mega_menu id="a"]'                                 => '[ashbi_mega_menu id="a"]',
);
foreach ( $abmm_rewrites as $abmm_from => $abmm_to ) {
	verify_migration( $abmm_to === ABMM_EMM_Migration::rewrite_codes( $abmm_from ), 'Rewrite: ' . $abmm_from );
}

// A real run over posts, Elementor data and widgets, with a backup and an undo.
$abmm_posts = array(
	5 => 'Intro [easy_mega_menu id="main_nav"] outro',
	6 => 'No menu here, just the words easy_mega_menu.',
);
$abmm_elementor = array(
	21 => array( 'post' => 5, 'value' => '[{"settings":{"shortcode":"[easy_mega_menu id=\\"main_nav\\"]"}}]' ),
	22 => array( 'post' => 8, 'value' => '[{"settings":{"title":"Other"}}]' ),
);
$abmm_widget_value = array( 2 => array( 'text' => 'Menu [easy_mega_menu id="main_nav"]' ), '_multiwidget' => 1 );
$GLOBALS['abmm_test_posts']          = $abmm_posts;
$GLOBALS['abmm_test_elementor_rows'] = $abmm_elementor;
$GLOBALS['abmm_test_widgets']        = array( 'widget_text' );
$GLOBALS['abmm_options']['widget_text'] = $abmm_widget_value;

$GLOBALS['abmm_test_failed_scan'] = 'SELECT pm.meta_id, pm.post_id';
$abmm_failed_scan = $migration->finish_switching();
unset( $GLOBALS['abmm_test_failed_scan'] );
verify_migration( is_wp_error( $abmm_failed_scan ) && $abmm_posts === $GLOBALS['abmm_test_posts'] && ! $migration->has_backup(), 'A failed content scan stops the switch before any page is changed.' );

$GLOBALS['abmm_test_backup_fails'] = true;
$abmm_failed = $migration->finish_switching();
unset( $GLOBALS['abmm_test_backup_fails'] );
verify_migration( is_wp_error( $abmm_failed ) && $abmm_posts === $GLOBALS['abmm_test_posts'] && $abmm_elementor === $GLOBALS['abmm_test_elementor_rows'] && $abmm_widget_value === $GLOBALS['abmm_options']['widget_text'], 'Nothing is changed when the backup cannot be saved.' );

$GLOBALS['abmm_transients']['abmm_emm_shortcode_in_use'] = 'yes';
$abmm_done = $migration->finish_switching();
verify_migration( 1 === $abmm_done['posts'] && 1 === $abmm_done['elementor'] && 1 === $abmm_done['widgets'] && 0 === $abmm_done['failed'], 'Finish switching counts what it changed.' );
verify_migration( 'Intro [ashbi_mega_menu id="main_nav"] outro' === $GLOBALS['abmm_test_posts'][5], 'Post content is rewritten.' );
verify_migration( $abmm_posts[6] === $GLOBALS['abmm_test_posts'][6], 'A post with only the words is left alone.' );
verify_migration( '[{"settings":{"shortcode":"[ashbi_mega_menu id=\\"main_nav\\"]"}}]' === $GLOBALS['abmm_test_elementor_rows'][21]['value'], 'Elementor data keeps its escaped quotes.' );
verify_migration( $abmm_elementor[22] === $GLOBALS['abmm_test_elementor_rows'][22], 'Other Elementor data is left alone.' );
verify_migration( 'Menu [ashbi_mega_menu id="main_nav"]' === $GLOBALS['abmm_options']['widget_text'][2]['text'], 'Widgets are rewritten.' );
verify_migration( array( 5 ) === $GLOBALS['abmm_cleaned_posts'], 'The rewritten post leaves the cache.' );
verify_migration( ! isset( $GLOBALS['abmm_transients']['abmm_emm_shortcode_in_use'] ), 'The next check for old codes searches again.' );
verify_migration( ABMM_EMM_Migration::instance()->has_backup(), 'A backup is kept.' );
verify_migration( true === get_option( ABMM_EMM_Migration::TAKEOVER_OPTION ), 'A rollback to an older version keeps answering any code left.' );

$abmm_rendered = $migration->shortcode( array( 'id' => 'main_nav', 'context' => 'page', 'presentation' => 'unified', 'review_post_id' => '7' ) );
require_once dirname( __DIR__ ) . '/includes/class-abmm-shortcode.php';
$abmm_new_code = ABMM_Shortcode::instance()->shortcode( array( 'id' => 'main_nav', 'context' => 'page', 'presentation' => 'unified', 'review_post_id' => '7' ) );
verify_migration( $same( $abmm_rendered ) === $same( $abmm_new_code ), 'Both codes render the same header, with every option.' );
$abmm_same_any = function ( $markup ) { return trim( preg_replace( '/emm-[a-z0-9_-]+-\d+/', 'emm-N', $markup ) ); };
verify_migration( '' !== $migration->shortcode( array() ) && $abmm_same_any( $migration->shortcode( array() ) ) === $abmm_same_any( ABMM_Shortcode::instance()->shortcode( array() ) ), 'Both codes pick the same menu with no ID.' );

// A second run with nothing to do keeps the backup. Undo leaves edited places alone.
$abmm_again = $migration->finish_switching();
verify_migration( 0 === $abmm_again['posts'] + $abmm_again['elementor'] + $abmm_again['widgets'] && $migration->has_backup(), 'Running it again changes nothing and keeps the backup.' );
$GLOBALS['abmm_test_elementor_rows'][21]['value'] = 'edited after the switch';
$abmm_undone = $migration->undo_switching();
verify_migration( 2 === $abmm_undone['restored'] && 1 === $abmm_undone['skipped'], 'Undo counts what it restored and what it left.' );
verify_migration( $abmm_posts === $GLOBALS['abmm_test_posts'], 'Undo restores post content exactly.' );
verify_migration( 'edited after the switch' === $GLOBALS['abmm_test_elementor_rows'][21]['value'], 'Undo keeps a later edit.' );
verify_migration( $abmm_widget_value === $GLOBALS['abmm_options']['widget_text'], 'Undo restores widgets exactly.' );
verify_migration( ! $migration->has_backup() && is_wp_error( $migration->undo_switching() ), 'Undo works once, then there is nothing to undo.' );

// Concurrent saves win at write time, in both directions.
$GLOBALS['abmm_test_concurrent_edits'] = array( 5 => 'New page edit', 'widget_text' => array( 2 => array( 'text' => 'New widget edit' ) ) );
$abmm_conflicts = $migration->finish_switching();
verify_migration( 2 === $abmm_conflicts['skipped'] && 'New page edit' === $GLOBALS['abmm_test_posts'][5] && $GLOBALS['abmm_test_concurrent_edits']['widget_text'] === get_option( 'widget_text' ), 'Finish switching preserves edits made after the snapshot.' );
unset( $GLOBALS['abmm_test_concurrent_edits'] );
$GLOBALS['abmm_test_posts'] = $abmm_posts;
$GLOBALS['abmm_test_elementor_rows'] = $abmm_elementor;
$GLOBALS['abmm_options']['widget_text'] = $abmm_widget_value;
$migration->finish_switching();
$GLOBALS['abmm_test_concurrent_edits'] = array( 21 => 'New Elementor edit' );
$GLOBALS['abmm_test_failed_rows'] = array( 5, 'widget_text' );
$abmm_partial = $migration->undo_switching();
verify_migration( 2 === $abmm_partial['failed'] && 1 === $abmm_partial['skipped'] && 0 === $abmm_partial['restored'] && $migration->has_backup(), 'Undo retains failed page and widget writes while leaving concurrent edits alone.' );
unset( $GLOBALS['abmm_test_concurrent_edits'], $GLOBALS['abmm_test_failed_rows'] );
$abmm_retry = $migration->undo_switching();
verify_migration( 2 === $abmm_retry['restored'] && ! $migration->has_backup() && $abmm_posts === $GLOBALS['abmm_test_posts'] && $abmm_widget_value === get_option( 'widget_text' ) && 'New Elementor edit' === $GLOBALS['abmm_test_elementor_rows'][21]['value'], 'Retry restores only the outstanding failures.' );

// Undo restores Elementor data byte for byte too, through the handlers.
$GLOBALS['abmm_test_elementor_rows'] = $abmm_elementor;
$_POST = array( '_wpnonce' => 'valid' );
try { $migration->handle_finish(); } catch ( ABMM_Test_Redirect $stop ) { verify_migration( false !== strpos( $stop->getMessage(), 'abmm_notice=finished' ), 'The button reports it finished.' ); }
verify_migration( false === strpos( $GLOBALS['abmm_test_elementor_rows'][21]['value'], 'easy_mega_menu' ), 'The button rewrites Elementor data.' );
try { $migration->handle_undo(); } catch ( ABMM_Test_Redirect $stop ) { verify_migration( false !== strpos( $stop->getMessage(), 'abmm_notice=undone' ), 'The undo button reports it undid.' ); }
verify_migration( $abmm_elementor === $GLOBALS['abmm_test_elementor_rows'], 'Undo restores Elementor data exactly.' );
unset( $GLOBALS['abmm_test_posts'], $GLOBALS['abmm_test_elementor_rows'], $GLOBALS['abmm_test_widgets'], $GLOBALS['abmm_options']['widget_text'] );

// Where the page is listed: in the plugin's menu until switching is done, then under Tools, and nowhere on a site that never used it.
$abmm_listed = function () use ( $migration ) {
	$GLOBALS['abmm_pages'] = array();
	unset( $GLOBALS['abmm_transients']['abmm_emm_source_installed'], $GLOBALS['abmm_transients']['abmm_emm_shortcode_in_use'] );
	$migration->register_page();
	return $GLOBALS['abmm_pages'][0][0] ?? '';
};
verify_migration( 'ashbi-mega-menu' === $abmm_listed(), 'The page is in the plugin menu while Easy Mega Menu is installed.' );
$abmm_saved_plugins      = $GLOBALS['abmm_plugins'];
$GLOBALS['abmm_plugins'] = array();
verify_migration( 'ashbi-mega-menu' === $abmm_listed(), 'It stays there while old codes remain.' );
$GLOBALS['abmm_content_uses_shortcode'] = false;
verify_migration( 'tools.php' === $abmm_listed(), 'With Easy Mega Menu gone and no codes left it moves under Tools.' );
verify_migration( false !== strpos( $migration->page_url(), 'tools.php?page=' ), 'Links follow it.' );
$abmm_saved_options = $GLOBALS['abmm_options'];
unset( $GLOBALS['abmm_options']['emm_menus'], $GLOBALS['abmm_options']['emm_product_profiles'], $GLOBALS['abmm_options'][ ABMM_EMM_Migration::ORIGIN_OPTION ], $GLOBALS['abmm_options'][ ABMM_EMM_Migration::BACKUP_OPTION ] );
verify_migration( '' === $abmm_listed(), 'A site with nothing from Easy Mega Menu lists nothing.' );
$GLOBALS['abmm_options']                = $abmm_saved_options;
$GLOBALS['abmm_content_uses_shortcode'] = true;
$GLOBALS['abmm_plugins']                = $abmm_saved_plugins;
unset( $GLOBALS['abmm_transients']['abmm_emm_source_installed'] );

// Deleting Easy Mega Menu takes nothing the site needs.
$abmm_id_source = (string) array_key_first( $GLOBALS['abmm_options']['emm_menus'] );
$migration->upgrade();
$abmm_saved_options = $GLOBALS['abmm_options'];
$abmm_asset_keys    = ABMM_Classic::instance()->site_assets();
$GLOBALS['abmm_plugins'] = array();
unset( $GLOBALS['abmm_options']['emm_menus'], $GLOBALS['abmm_options']['emm_product_profiles'], $GLOBALS['abmm_options']['emm_product_branding'] );
$GLOBALS['abmm_transients'] = array();
verify_migration( $migration->is_source_menu( $abmm_id_source ), 'A menu is still known to have started in Easy Mega Menu.' );
verify_migration( $abmm_asset_keys === ABMM_Classic::instance()->site_assets() && $migration->assets_copied(), 'The copied styles and icons stay in place.' );
verify_migration( true === $migration->copy_assets()['copied'] && $migration->assets_copied(), 'Copying again with the plugin gone keeps them.' );
verify_migration( 0 === strpos( trim( $migration->shortcode( array( 'id' => 'main_nav' ) ) ), '<header class="emm-header' ), 'The old code still renders the header.' );
$GLOBALS['abmm_options'] = $abmm_saved_options;
$GLOBALS['abmm_plugins'] = $abmm_saved_plugins;
$GLOBALS['abmm_transients'] = array();

// The upgrade copies missing styles, and an assignment made after the import, but never replaces anything.
$abmm_assets = $GLOBALS['abmm_options'][ ABMM_Classic::ASSETS_OPTION ];
unset( $GLOBALS['abmm_options'][ ABMM_Classic::ASSETS_OPTION ], $GLOBALS['abmm_options'][ ABMM_EMM_Migration::ORIGIN_OPTION ] );
$GLOBALS['abmm_meta'][901] = array( '_emm_product_profile' => 'product_a' );
$GLOBALS['abmm_meta'][902] = array( '_emm_product_profile' => 'product_a', ABMM_Page_Context::META_KEY => 'product_b' );
$GLOBALS['abmm_meta'][903] = array( '_emm_product_profile' => 'not_an_ashbi_profile' );
$migration->upgrade();
verify_migration( $migration->assets_copied(), 'The upgrade copies styles and icons that are missing.' );
verify_migration( $migration->is_source_menu( $abmm_id_source ), 'The upgrade records which menus came from Easy Mega Menu.' );
verify_migration( 'product_a' === get_post_meta( 901, ABMM_Page_Context::META_KEY ), 'The upgrade copies a page assignment made after the import.' );
verify_migration( 'product_b' === get_post_meta( 902, ABMM_Page_Context::META_KEY ), 'The upgrade keeps a page assignment made in Ashbi.' );
verify_migration( '' === get_post_meta( 903, ABMM_Page_Context::META_KEY ), 'The upgrade skips a profile Ashbi does not have.' );
$GLOBALS['abmm_meta'][901][ ABMM_Page_Context::META_KEY ] = '';
$migration->upgrade();
verify_migration( '' === get_post_meta( 901, ABMM_Page_Context::META_KEY ), 'An assignment removed afterwards stays removed.' );
unset( $GLOBALS['abmm_meta'][901], $GLOBALS['abmm_meta'][902], $GLOBALS['abmm_meta'][903] );

// Archiving is the safe way to remove a menu: it leaves the choices and comes back whole.
$abmm_archive_id = 'main_nav';
$abmm_before     = ABMM_Data::instance()->get_all()[ $abmm_archive_id ];
verify_migration( true === ABMM_Data::instance()->archive( $abmm_archive_id ), 'A menu can be archived.' );
verify_migration( ! isset( ABMM_Data::instance()->get_all()[ $abmm_archive_id ] ) && isset( ABMM_Data::instance()->get_archived()[ $abmm_archive_id ] ), 'An archived menu is not an active choice.' );
verify_migration( '' === $migration->shortcode( array( 'id' => $abmm_archive_id ) ), 'An archived menu does not render.' );
verify_migration( true === ABMM_Data::instance()->restore( $abmm_archive_id ), 'An archived menu can be restored.' );
verify_migration( $abmm_before === ABMM_Data::instance()->get_all()[ $abmm_archive_id ], 'A restored menu comes back unchanged.' );

// Backups with no menus are not made, not listed and cannot be restored.
$abmm_io       = ABMM_Import_Export::instance();
$abmm_active   = $GLOBALS['abmm_options'][ ABMM_OPTION_KEY ];
$abmm_real     = array( 'id' => 'real', 'created_at' => '2026-01-01T00:00:00Z', 'menus' => $abmm_active );
$abmm_empty    = array( 'id' => 'empty', 'created_at' => '2026-01-02T00:00:00Z', 'menus' => array() );
$GLOBALS['abmm_options']['abmm_import_backups'] = array( $abmm_empty, $abmm_real );
verify_migration( array( 'real' ) === array_column( $abmm_io->get_backups(), 'id' ), 'A backup with no menus is not listed.' );
$abmm_refused = $abmm_io->restore_backup( 'empty', ABMM_Data::instance()->revision( ABMM_Data::instance()->get_all() ) );
verify_migration( is_wp_error( $abmm_refused ) && $abmm_active === $GLOBALS['abmm_options'][ ABMM_OPTION_KEY ], 'A backup with no menus cannot be restored.' );
verify_migration( is_wp_error( $abmm_io->export_backup( 'empty' ) ), 'A backup with no menus cannot be downloaded.' );
$abmm_make = new ReflectionMethod( $abmm_io, 'backup_current_menus' );
$abmm_make->setAccessible( true );
$GLOBALS['abmm_options'][ ABMM_OPTION_KEY ] = array();
verify_migration( true === $abmm_make->invoke( $abmm_io ) && 2 === count( $GLOBALS['abmm_options']['abmm_import_backups'] ), 'No backup is made when there are no menus.' );
$GLOBALS['abmm_options'][ ABMM_OPTION_KEY ] = $abmm_active;
verify_migration( true === $abmm_make->invoke( $abmm_io ) && array( 'real' ) === array_slice( array_column( $GLOBALS['abmm_options']['abmm_import_backups'], 'id' ), 1 ), 'The next backup also clears out empty ones.' );

// The plugin's own Elementor widget counts as a placement, nested at any depth.
$abmm_widget_tree = json_encode(
	array(
		array(
			'elType'   => 'container',
			'elements' => array(
				array( 'elType' => 'widget', 'widgetType' => 'ashbi-mega-menu', 'settings' => array( 'menu_id' => 'widget_menu' ), 'elements' => array() ),
				array( 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'menu_id' => 'not_ours' ), 'elements' => array() ),
			),
		),
	)
);
$GLOBALS['abmm_test_elementor'] = array( $abmm_widget_tree, '{not json' );
unset( $GLOBALS['abmm_transients']['abmm_emm_menus_in_use'] );
verify_migration( $migration->elementor_widget_uses_menu( 'widget_menu' ), 'A menu shown by the Elementor widget is found.' );
verify_migration( ! $migration->elementor_widget_uses_menu( 'not_ours' ), 'Other widgets with the same setting name do not count.' );
verify_migration( ! $migration->elementor_widget_uses_menu( 'widget' ), 'A partial menu ID does not match.' );
$GLOBALS['abmm_test_elementor'] = array();
verify_migration( $migration->elementor_widget_uses_menu( 'widget_menu' ), 'The widget search is cached.' );
verify_migration( ! $migration->elementor_widget_uses_menu( 'widget_menu', true ), 'A fresh search sees the widget has gone.' );
$abmm_first_menu = (string) array_key_first( ABMM_Data::instance()->get_all() );
$GLOBALS['abmm_test_elementor'] = array( json_encode( array( array( 'widgetType' => 'ashbi-mega-menu', 'settings' => array() ) ) ) );
verify_migration( '' !== $abmm_first_menu && $migration->elementor_widget_uses_menu( $abmm_first_menu, true ), 'A widget left at its default shows the first menu.' );
unset( $GLOBALS['abmm_test_elementor'], $GLOBALS['abmm_transients']['abmm_emm_menus_in_use'] );

// While Easy Mega Menu still runs a site with imported menus, Ashbi adds no body classes.
define( 'EMM_VERSION', '1.6.76' );
verify_migration( array( 'page' ) === ABMM_Product_Branding::instance()->body_class( array( 'page' ) ) && array( 'page' ) === ABMM_Classic::instance()->body_class( array( 'page' ) ), 'No Ashbi body classes while Easy Mega Menu is active.' );

echo "Easy Mega Menu migration: read-only import, ID conflicts, product navigation, asset copy, round trip, old codes, finish switching and undo passed.\n";
