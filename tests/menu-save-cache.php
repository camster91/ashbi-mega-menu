<?php
/**
 * Simulate two requests that loaded the same menu before either save lock ran.
 */

define( 'ABSPATH', __DIR__ . '/wordpress/' );
define( 'ABMM_OPTION_KEY', 'abmm_menus' );
define( 'ABMM_ARCHIVE_OPTION', 'abmm_archives' );
define( 'ABMM_VERSION', 'test' );
define( 'WP_CLI', true );
class WP_CLI_Command {}
class WP_CLI {
 public static function add_command() {}
 public static function success($message) {}
 public static function warning($message) {}
 public static function log($message) {}
 public static function error($message) { throw new Exception($message); }
}

class WP_Error {
	private $code;

	public function __construct( $code ) {
		$this->code = $code;
	}

	public function get_error_code() {
		return $this->code;
	}
}

class ABMM_Test_DB {
	public $options = 'wp_options';

	public function prepare( $query ) {
		return $query;
	}

	public $acquisitions = 0;
 public $lock_result = '1';
 public function get_var($query) {
 if (strpos($query, 'GET_LOCK') !== false) { ++$this->acquisitions; return $this->lock_result; }
		return '1';
	}
}

function __( $text ) {
	return $text;
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function get_option( $key, $default = false ) {
	global $database_options, $cached_options;
	if ( array_key_exists( $key, $cached_options ) ) {
		return $cached_options[ $key ];
	}
	$cached_options[ $key ] = $database_options[ $key ] ?? $default;
	return $cached_options[ $key ];
}

function update_option( $key, $value ) {
 global $fail_option;
 if ($key === $fail_option) { return false; }
	global $database_options, $cached_options;
 if (isset($database_options[$key]) && $database_options[$key] === $value) { return false; }
	$database_options[ $key ] = $value;
	$cached_options[ $key ] = $value;
	return true;
}

function wp_cache_delete( $key, $group = '' ) {
	global $cached_options;
	if ( 'options' === $group ) {
		unset( $cached_options[ $key ] );
 if ('alloptions' === $key) { $cached_options = array(); }
	}
	return true;
}

function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action() {}
function wp_generate_uuid4() { static $i=0; return 'backup-' . ++$i; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function sanitize_text_field($value) { return trim($value); }
function absint($value) { return abs((int)$value); }
require_once __DIR__ . '/../includes/class-abmm-data.php';
require_once __DIR__ . '/../includes/class-abmm-import-export.php';

class ABMM_Test_Data extends ABMM_Data {
	public function sanitize_menu( $data ) {
		return $data;
	}
}

$wpdb = new ABMM_Test_DB();
$store = new ABMM_Test_Data();
$old_menu = array( 'title' => 'Old revision' );
$new_menu = array( 'title' => 'Another editor saved first' );
$database_options = array( ABMM_OPTION_KEY => array( 'menu_demo' => $new_menu ) );
$cached_options = array( ABMM_OPTION_KEY => array( 'menu_demo' => $old_menu ) );

$result = $store->save_if_revision( 'menu_demo', array( 'title' => 'Stale draft' ), $store->revision( $old_menu ) );
if ( ! $result instanceof WP_Error || 'abmm_menu_conflict' !== $result->get_error_code() || $new_menu !== $database_options[ ABMM_OPTION_KEY ]['menu_demo'] ) {
	fwrite( STDERR, "A stale option cache allowed a locked save to overwrite a newer revision.\n" );
	exit( 1 );
}

echo "Menu save cache regression passed.\n";

function check($condition, $message) { if (!$condition) { throw new Exception($message); } }
$fail_option = null;
$database_options = array( ABMM_OPTION_KEY => array('first' => array('title'=>'Fresh', 'items'=>array())), ABMM_ARCHIVE_OPTION => array() );
$cached_options = array( ABMM_OPTION_KEY => array('stale' => array('title'=>'Stale')) );
check($store->save('second', array('title'=>'Second', 'items'=>array())), 'save failed');
check(isset($database_options[ABMM_OPTION_KEY]['first']) && !isset($database_options[ABMM_OPTION_KEY]['stale']), 'Unlocked save overwrote fresh collection');
$before = $wpdb->acquisitions;
$store->with_collection_lock(function() use($store) { $store->save('third', array('title'=>'Third')); });
check($wpdb->acquisitions === $before + 1, 'Nested mutation acquired lock twice');
$wpdb->lock_result = null;
check(is_wp_error($store->delete('second')), 'Delete ignored unavailable lock');
$wpdb->lock_result = '1';
check($store->archive('second'), 'Archive failed');
check($store->restore('second'), 'Archive restore failed');
$property = new ReflectionProperty('ABMM_Data', 'instance'); $property->setAccessible(true); $property->setValue(null, $store);
$exporter = ABMM_Import_Export::instance();
$payload = array('menus'=>array('imported'=>array('title'=>'Import', 'items'=>array())));
$original = $database_options[ABMM_OPTION_KEY];
$fail_option = 'abmm_import_backups';
check(is_wp_error($exporter->import_json($payload)), 'Import ignored backup failure');
check($database_options[ABMM_OPTION_KEY] === $original, 'Backup failure changed live menus');
$fail_option = null;
$result = $exporter->import_json($payload);
check(!is_wp_error($result) && isset($database_options[ABMM_OPTION_KEY]['third']), 'Merge discarded existing menus');
$backup = $exporter->get_backups()[0];
check(is_wp_error($exporter->restore_backup($backup['id'], 'stale')), 'Restore ignored revision');
check(true === $exporter->restore_backup($backup['id'], $store->revision($store->get_all())), 'Backup restore failed');
check($database_options[ABMM_OPTION_KEY] === $original, 'Restore did not recover prior collection');
$file = tempnam(sys_get_temp_dir(), 'abmm-import-');
file_put_contents($file, json_encode($payload));
(new ABMM_WP_CLI_Command())->import(array(), array('file'=>$file, 'dry-run'=>true));
(new ABMM_WP_CLI_Command())->import(array(), array('file'=>$file));
unlink($file);
check(!$store->readiness(array('title'=>'Ready?', 'items'=>array(array('label'=>'Home','url'=>'/'),array('label'=>'home','url'=>'/home'),array('type'=>'mega','label'=>'Empty'))))['ready'], 'Readiness missed duplicate or empty panel');
echo "Collection mutation, backup, readiness and CLI regressions passed.\n";
