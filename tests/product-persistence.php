<?php
/** Regression coverage for concurrent product edits and verified persistence. */
require __DIR__ . '/menu-save-cache.php';
function add_filter() {}
function esc_url_raw( $value ) { return $value; }
function sanitize_title( $value ) { return strtolower( str_replace( ' ', '-', $value ) ); }
require __DIR__ . '/../includes/class-abmm-option-store.php';
require __DIR__ . '/../includes/class-abmm-product-profiles.php';
require __DIR__ . '/../includes/class-abmm-product-branding.php';
$profiles = ABMM_Product_Profiles::instance();
$brands = ABMM_Product_Branding::instance();
$one = $profiles->sanitize_profile( array( 'title'=>'One', 'label'=>'One', 'url'=>'/one/' ) );
$two = $profiles->sanitize_profile( array( 'title'=>'Two', 'label'=>'Two', 'url'=>'/two/' ) );
$latest = $one; $latest['label'] = 'Another administrator';
$database_options[ABMM_Product_Profiles::OPTION_KEY] = array('one'=>$latest,'two'=>$two);
$cached_options[ABMM_Product_Profiles::OPTION_KEY] = array('one'=>$one);
$result = $profiles->store_profile('one',$one,ABMM_Option_Store::revision($one));
check(is_wp_error($result) && 'abmm_product_conflict' === $result->get_error_code(), 'Stale cache allowed overwrite');
check($database_options[ABMM_Product_Profiles::OPTION_KEY]['one'] === $latest, 'Conflict changed profile');
$one['label'] = 'My reviewed changes';
$result = $profiles->store_profile('one',$one,ABMM_Option_Store::revision($latest));
check('one' === $result && isset($database_options[ABMM_Product_Profiles::OPTION_KEY]['two']), 'Save discarded unaffected profile');
$fail_option = ABMM_Product_Profiles::OPTION_KEY;
check('one' === $profiles->store_profile('one',$one,ABMM_Option_Store::revision($one)), 'No-op reported failure');
$changed = $one; $changed['label'] = 'Failed value';
check(is_wp_error($profiles->store_profile('one',$changed,ABMM_Option_Store::revision($one))), 'Failed update reported saved');
$fail_option = null;
check(is_wp_error($profiles->store_profile('', $one, ABMM_Option_Store::revision(null))), 'Duplicate create overwrote existing profile');
check('three' === $profiles->store_profile('',array('title'=>'Three','label'=>'Three','url'=>'/three/'),ABMM_Option_Store::revision(null)), 'New create failed');
$config = $brands->sanitize_config(array());
$new_config = $config; $new_config['alt'] = 'Updated';
$database_options[ABMM_Product_Branding::OPTION_KEY] = array('one'=>$new_config,'two'=>$config);
$cached_options[ABMM_Product_Branding::OPTION_KEY] = array('one'=>$config);
check(is_wp_error($brands->store_config('one',$config,ABMM_Option_Store::revision($config),ABMM_Option_Store::revision($one))), 'Stale branding cache overwrote latest');
check(true === $brands->store_config('one',$config,ABMM_Option_Store::revision($new_config),ABMM_Option_Store::revision($one)), 'Branding save failed');
check(isset($database_options[ABMM_Product_Branding::OPTION_KEY]['two']), 'Branding save lost other product');
$fail_option = ABMM_Product_Branding::OPTION_KEY;
check(true === $brands->store_config('one',$config,ABMM_Option_Store::revision($config),ABMM_Option_Store::revision($one)), 'Branding no-op failed');
check(is_wp_error($brands->store_config('one',$new_config,ABMM_Option_Store::revision($config),ABMM_Option_Store::revision($one))), 'Branding false success');
check(is_wp_error($profiles->remove_profile('one',ABMM_Option_Store::revision($latest),ABMM_Option_Store::revision($config))), 'Stale delete allowed');
check(is_wp_error($profiles->remove_profile('one',ABMM_Option_Store::revision($one),ABMM_Option_Store::revision($config))), 'Failed branding cleanup reported deleted');
check(isset($database_options[ABMM_Product_Profiles::OPTION_KEY]['one']), 'Cleanup failure did not restore profile');
$fail_option = null;
check(true === $profiles->remove_profile('one',ABMM_Option_Store::revision($one),ABMM_Option_Store::revision($config)), 'Delete failed');
check(!isset($database_options[ABMM_Product_Profiles::OPTION_KEY]['one']) && !isset($database_options[ABMM_Product_Branding::OPTION_KEY]['one']), 'Delete left orphan');
check(is_wp_error($brands->store_config('one',$config,ABMM_Option_Store::revision($config),ABMM_Option_Store::revision($one))), 'Save recreated deleted branding');
$wpdb->lock_result = '0';
check(is_wp_error($profiles->store_profile('two',$two,ABMM_Option_Store::revision($two))), 'Unavailable lock allowed profile save');
$wpdb->lock_result = '1';
echo "Product persistence regressions passed.\n";

function current_user_can( $capability ) { return true; }
function get_current_user_id() { global $draft_user; return $draft_user; }
function wp_unslash( $value ) { return $value; }
function wp_verify_nonce( $nonce, $action ) { return $nonce === 'valid-' . $action; }
function get_transient( $key ) { global $drafts; return $drafts[ $key ] ?? false; }
$draft_user = 7;
$drafts = array( 'abmm_product_draft_7_token' => array( 'page'=>'ashbi-mega-menu-products', 'id'=>'two', 'record'=>$two, 'error'=>'Save failed' ) );
$_GET = array( 'abmm_draft'=>'token', '_abmm_draft_nonce'=>'valid-abmm_product_draft_token' );
check( ABMM_Option_Store::draft('ashbi-mega-menu-products')['record'] === $two, 'Recovery did not retain profile values' );
check( null === ABMM_Option_Store::draft('ashbi-mega-menu-product-branding'), 'Draft leaked across editors' );
$draft_user = 8;
check( null === ABMM_Option_Store::draft('ashbi-mega-menu-products'), 'Recovery draft leaked across administrators' );
$draft_user = 7; $_GET['_abmm_draft_nonce'] = 'invalid';
check( null === ABMM_Option_Store::draft('ashbi-mega-menu-products'), 'Recovery draft ignored nonce' );
echo "Private recovery draft regressions passed.\n";
