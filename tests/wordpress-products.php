<?php
/** Product persistence acceptance tests in a disposable, real WordPress database. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }
global $wpdb;
function abmm_product_assert( $condition, $message ) { if ( ! $condition ) { WP_CLI::error( $message ); } }
$profiles = ABMM_Product_Profiles::instance();
$brands = ABMM_Product_Branding::instance();
$empty_revision = ABMM_Option_Store::revision( null );
$one = $profiles->sanitize_profile( array( 'title' => 'QA One', 'label' => 'QA One', 'url' => '/qa-one/' ) );
$two = $profiles->sanitize_profile( array( 'title' => 'QA Two', 'label' => 'QA Two', 'url' => '/qa-two/' ) );
$id_one = $profiles->store_profile( '', $one, $empty_revision );
$id_two = $profiles->store_profile( '', $two, $empty_revision );
abmm_product_assert( is_string( $id_one ) && is_string( $id_two ), 'Create products with revision checks.' );
$current = $profiles->get_all();
$stale_revision = ABMM_Option_Store::revision( $current[ $id_one ] );
$other_db = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$lock_name = 'abmm_menus_save_' . md5( $wpdb->options . '|' . ABMM_OPTION_KEY );
abmm_product_assert( '1' === (string) $other_db->get_var( $other_db->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name ) ), 'Independent connection owns shared lock.' );
$blocked = $profiles->store_profile( $id_one, $one, $stale_revision );
abmm_product_assert( is_wp_error( $blocked ) && 'abmm_save_lock_timeout' === $blocked->get_error_code(), 'Products respect a competing lock.' );
$other_db->get_var( $other_db->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
// Simulate another request after this request populated its option cache.
get_option( ABMM_Product_Profiles::OPTION_KEY );
$current[ $id_two ]['label'] = 'Concurrent second product';
$other_db->query( $other_db->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", maybe_serialize( $current ), ABMM_Product_Profiles::OPTION_KEY ) );
$one['label'] = 'Reviewed first product';
abmm_product_assert( $id_one === $profiles->store_profile( $id_one, $one, $stale_revision ), 'Save unaffected product after fresh cache read.' );
abmm_product_assert( 'Concurrent second product' === $profiles->get( $id_two )['label'], 'Concurrent unrelated changes preserved.' );
abmm_product_assert( is_wp_error( $profiles->store_profile( $id_one, $one, $stale_revision ) ), 'Stale editor rejected.' );
$fresh = $profiles->get( $id_one );
abmm_product_assert( $id_one === $profiles->store_profile( $id_one, $fresh, ABMM_Option_Store::revision( $fresh ) ), 'Unchanged saves succeed.' );
$fail_write = function ( $value, $old ) { return $old; };
add_filter( 'pre_update_option_' . ABMM_Product_Profiles::OPTION_KEY, $fail_write, 10, 2 );
$failed_value = $fresh; $failed_value['label'] = 'Must not report saved';
abmm_product_assert( is_wp_error( $profiles->store_profile( $id_one, $failed_value, ABMM_Option_Store::revision( $fresh ) ) ), 'Failed product write is an error.' );
remove_filter( 'pre_update_option_' . ABMM_Product_Profiles::OPTION_KEY, $fail_write, 10 );
abmm_product_assert( $profiles->get( $id_one ) === $fresh, 'Failed save leaves stored product unchanged.' );
$unicode = $profiles->sanitize_profile( array( 'title' => '日本語', 'label' => '日本語', 'url' => '/unicode/' ) );
$unicode_id = $profiles->store_profile( '', $unicode, $empty_revision );
abmm_product_assert( is_string( $unicode_id ) && isset( $profiles->get_all()[ $unicode_id ] ), 'Unicode ID is canonical on create and read.' );
abmm_product_assert( is_wp_error( $profiles->store_profile( '', $unicode, $empty_revision ) ), 'Unicode duplicate create cannot replace existing product.' );
$config = $brands->sanitize_config( array( 'enabled' => false ) );
abmm_product_assert( true === $brands->store_config( $id_one, $config, $empty_revision, ABMM_Option_Store::revision( $fresh ) ), 'Save branding with both revisions.' );
$stored_config = $brands->get_all()[ $id_one ];
add_filter( 'pre_update_option_' . ABMM_Product_Branding::OPTION_KEY, $fail_write, 10, 2 );
$changed_config = $stored_config; $changed_config['alt'] = 'Must not report saved';
abmm_product_assert( is_wp_error( $brands->store_config( $id_one, $changed_config, ABMM_Option_Store::revision( $stored_config ), ABMM_Option_Store::revision( $fresh ) ) ), 'Failed branding save is an error.' );
$delete_result = $profiles->remove_profile( $id_one, ABMM_Option_Store::revision( $fresh ), ABMM_Option_Store::revision( $stored_config ) );
abmm_product_assert( is_wp_error( $delete_result ) && $fresh === $profiles->get( $id_one ), 'Failed branding cleanup rolls back profile deletion.' );
remove_filter( 'pre_update_option_' . ABMM_Product_Branding::OPTION_KEY, $fail_write, 10 );
abmm_product_assert( true === $profiles->remove_profile( $id_one, ABMM_Option_Store::revision( $fresh ), ABMM_Option_Store::revision( $stored_config ) ), 'Reviewed deletion succeeds.' );
$other_db->close();
WP_CLI::success( 'Product contention, fresh cache, unrelated preservation, stale/no-op/failure saves, Unicode IDs, branding failure and delete rollback passed.' );
