<?php
/** Numeric-zero menu IDs must reach every placement and admin action. */
require __DIR__ . '/import-roundtrip.php';
class ABMM_Frontend {
 public static function instance() { static $instance; return $instance ??= new self(); }
 public function render($id, $args = array()) { return '<nav data-menu="' . (string) $id . '"></nav>'; }
}
class WP_Widget { public function __construct() {} }
function add_shortcode() {}
function shortcode_atts($defaults, $atts, $tag = '') { return array_replace($defaults, $atts); }
function wp_kses_post($html) { return $html; }
function current_user_can($capability) { return true; }
function check_ajax_referer($action, $field) { return true; }
function wp_unslash($value) { return $value; }
class ABMM_Entrypoint_Response extends Exception { public $success; public $payload; public function __construct($success, $payload) { $this->success=$success; $this->payload=$payload; } }
function wp_send_json_error($data, $status=200) { throw new ABMM_Entrypoint_Response(false, $data); }
function wp_send_json_success($data, $status=200) { throw new ABMM_Entrypoint_Response(true, $data); }
require __DIR__ . '/../includes/class-abmm-shortcode.php';
require __DIR__ . '/../includes/class-abmm-block.php';
require __DIR__ . '/../includes/class-abmm-widget.php';
require __DIR__ . '/../includes/class-abmm-admin.php';
$database_options[ABMM_OPTION_KEY]=array('first'=>array('title'=>'First','items'=>array()),0=>array('title'=>'Zero','items'=>array()));
$cached_options=array();
$shortcode=ABMM_Shortcode::instance();
verify_roundtrip(strpos($shortcode->shortcode(array('id'=>'0')), 'data-menu="0"')!==false, 'Shortcode zero fell through to first menu');
verify_roundtrip(strpos($shortcode->shortcode(array()), 'data-menu="first"')!==false, 'Default shortcode no longer renders first menu');
verify_roundtrip(''===$shortcode->shortcode(array('id'=>array('0'))), 'Malformed shortcode ID was not rejected');
$block=ABMM_Block::instance();
verify_roundtrip(strpos($block->render_block(array('menuId'=>'0'), '', null), 'data-menu="0"')!==false, 'Block zero did not render');
verify_roundtrip(''===$block->render_block(array('menuId'=>array('0')), '', null), 'Malformed block ID was not rejected');
ob_start();
(new ABMM_Widget())->widget(array('before_widget'=>'','after_widget'=>''), array('menu_id'=>'0'));
$html=ob_get_clean();
verify_roundtrip(strpos($html, 'data-menu="0"')!==false, 'Widget zero did not render');
$attributes=array('menuId'=>'0');
ob_start(); require __DIR__ . '/../src/blocks/mega-menu/render.php'; $html=ob_get_clean();
verify_roundtrip(strpos($html, 'data-menu="0"')!==false, 'Block template zero did not render');
$admin=(new ReflectionClass('ABMM_Admin'))->newInstanceWithoutConstructor();
$_POST=array('menu_id'=>'0');
try { $admin->ajax_export_menu(); } catch(ABMM_Entrypoint_Response $response) { verify_roundtrip($response->success && strpos($response->payload['json'], 'Zero')!==false, 'Admin zero export was rejected'); }
try { $admin->ajax_delete_menu(); } catch(ABMM_Entrypoint_Response $response) { verify_roundtrip($response->success && null===$store->get('0'), 'Admin zero archive was rejected'); }
echo "Numeric-zero shortcode, block, widget and admin entrypoints passed.\n";
