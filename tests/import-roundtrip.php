<?php
/**
 * Import/export recovery regressions using the real importer and collection storage.
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
 private $message;

	public function __construct( $code, $message = '', $data = null ) {
 $this->message = $message;
		$this->code = $code;
	}

	public function get_error_message() { return $this->message; }

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

function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
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
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function absint($value) { return abs((int)$value); }
require_once __DIR__ . '/../includes/class-abmm-data.php';
require_once __DIR__ . '/../includes/class-abmm-import-export.php';

class ABMM_Test_Data extends ABMM_Data {
	public function sanitize_menu( $data ) {
		return $data;
	}
}


function verify_roundtrip($condition, $message) { if (!$condition) { throw new Exception($message); } }
$wpdb = new ABMM_Test_DB();
$store = new ABMM_Test_Data();
$property = new ReflectionProperty('ABMM_Data', 'instance');
$property->setAccessible(true);
$property->setValue(null, $store);
$exporter = ABMM_Import_Export::instance();
$fail_option = null;
$cached_options = array();
$database_options = array(ABMM_OPTION_KEY => array(), ABMM_ARCHIVE_OPTION => array());

// Both existing and incoming numeric IDs must still resolve through saved shortcodes.
$database_options[ABMM_OPTION_KEY] = array(42 => array('title'=>'Existing','items'=>array()));
$result = $exporter->import_json(array('menus'=>array(73=>array('title'=>'Incoming','items'=>array()))));
verify_roundtrip(!is_wp_error($result) && $store->get('42')['title']==='Existing' && $store->get('73')['title']==='Incoming', 'Numeric menu placements were renumbered during merge');
$result = $exporter->import_json(array('menus'=>array(42=>array('title'=>'Updated','items'=>array()))));
verify_roundtrip($store->get('42')['title']==='Updated' && count($store->get_all())===2, 'Numeric ID update created or renumbered a menu');
$selected = $exporter->export_json(array('73'));
verify_roundtrip(!is_wp_error($selected) && array_keys($selected['menus'])===array(73), 'Numeric selected export omitted its menu');

$result = $exporter->import_json(array('menus'=>array(0=>array('title'=>'Zero','items'=>array()))));
verify_roundtrip(!is_wp_error($result) && $store->get('0')['title']==='Zero', 'Numeric zero ID was rejected or renumbered');
verify_roundtrip(!is_wp_error($exporter->export_single_menu('0')), 'Numeric zero single-menu export failed');

// A complete collection above the old 50-menu cap survives an actual JSON file round trip.
$menus = array();
for ($i=1; $i<=51; ++$i) { $menus['menu_'.$i] = array('title'=>'Menu '.$i, 'items'=>array(array('label'=>'Résumé 🌍', 'url'=>'/menu/'.$i))); }
$database_options[ABMM_OPTION_KEY] = $menus;
$cached_options = array();
$payload = $exporter->export_json();
verify_roundtrip(!is_wp_error($payload), 'A 51-menu collection cannot be exported');
$json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
verify_roundtrip(strlen($json)<=ABMM_Import_Export::MAX_IMPORT_BYTES, 'Export exceeds importer byte limit');
$file = tempnam(sys_get_temp_dir(), 'abmm-roundtrip-');
file_put_contents($file, $json);
(new ABMM_WP_CLI_Command())->import(array(), array('file'=>$file, 'dry-run'=>true));
$store->replace_all(array('old'=>array('title'=>'Old','items'=>array())));
(new ABMM_WP_CLI_Command())->import(array(), array('file'=>$file, 'mode'=>'replace'));
unlink($file);
verify_roundtrip($store->get_all()===$menus, 'CLI JSON round trip changed IDs or menu content');
$backup = $exporter->get_backups()[0];
verify_roundtrip(!is_wp_error($exporter->export_backup($backup['id'])), 'A recovery export is not importable');

// The menu-count boundary remains finite; successful exports never promise an impossible recovery.
$at_limit = array();
for ($i=1; $i<=ABMM_Import_Export::MAX_IMPORT_MENUS; ++$i) { $at_limit['m_'.$i] = array('title'=>'Menu', 'items'=>array()); }
$database_options[ABMM_OPTION_KEY] = $at_limit; $cached_options = array();
verify_roundtrip(!is_wp_error($exporter->export_json()), 'Maximum supported menu count was rejected');
$database_options[ABMM_OPTION_KEY]['overflow']=array('title'=>'Overflow','items'=>array()); $cached_options=array();
verify_roundtrip(is_wp_error($exporter->export_json()), 'Export silently produced an unrecoverable menu count');
verify_roundtrip(!is_wp_error($exporter->export_json(array('m_1'))), 'Selecting a smaller collection cannot recover an oversized collection');
$oversized_snapshot = $store->get_all();
$database_options['abmm_import_backups']=array(array('id'=>'legacy-large','created_at'=>'2026-10-13T00:00:00Z','menus'=>$oversized_snapshot));
$cached_options=array();
verify_roundtrip(is_wp_error($exporter->export_backup('legacy-large')), 'Recovery-point download emitted a non-importable oversized collection');
$store->replace_all(array('small'=>array('title'=>'Small','items'=>array())));
verify_roundtrip(true===$exporter->restore_backup('legacy-large', $store->revision($store->get_all())) && $store->get_all()===$oversized_snapshot, 'Portable safety caps broke exact in-place recovery of a legacy collection');


// Unicode escapes and indentation count toward bytes; one huge menu fails clearly.
$huge = array('title'=>'Huge','items'=>array(), 'description'=>str_repeat('é', 200000));
$database_options[ABMM_OPTION_KEY]=array('huge'=>$huge); $cached_options=array();
$failure=$exporter->export_single_menu('huge');
verify_roundtrip(is_wp_error($failure) && strpos($failure->get_error_message(), '1 MB per-menu')!==false, 'An oversized Unicode menu exported without a useful recovery error');
verify_roundtrip(!empty($exporter->validate_payload(array('menus'=>array('huge'=>$huge)))), 'Importer accepts a menu the exporter cannot recover');
$many_large = array();
for($i=0;$i<20;++$i) { $many_large['large_'.$i]=array('title'=>'Large','items'=>array(),'description'=>str_repeat('a', 600000)); }
verify_roundtrip(!empty($exporter->validate_payload(array('menus'=>$many_large))), 'Recovery byte cap was removed');
$too_many_nodes=array('menus'=>array('dense'=>array('title'=>'Dense','items'=>array_fill(0,ABMM_Import_Export::MAX_IMPORT_NODES+1,0))));
verify_roundtrip(strpos(implode(' ', $exporter->validate_payload($too_many_nodes)), 'too many nested items')!==false, 'Recovery node cap was removed');
$deep=array(); for($i=0;$i<40;++$i) { $deep=array('nested'=>$deep); }
verify_roundtrip(!empty($exporter->validate_payload(array('menus'=>array('deep'=>array('title'=>'Deep','items'=>$deep))))), 'Recovery nesting cap was removed');
echo "Numeric placement stability and bounded export/import recovery regressions passed.\n";
