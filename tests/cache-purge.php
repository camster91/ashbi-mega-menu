<?php
/**
 * The page cache is cleared after a menu changes: at most once per request, only
 * when something changed, and never with a fatal error when there is no cache.
 *
 * NitroPack is not installed here. The stand-in below has the same signature as
 * nitropack_sdk_purge() in the NitroPack plugin's functions.php.
 */
define( 'ABSPATH', __DIR__ . '/wordpress/' );

$GLOBALS['abmm_hooks']  = array();
$GLOBALS['abmm_purges'] = array();

function add_action( $hook, $callback ) { $GLOBALS['abmm_hooks'][ $hook ][] = $callback; }
function do_action( $hook, ...$args ) {
	foreach ( $GLOBALS['abmm_hooks'][ $hook ] ?? array() as $callback ) {
		call_user_func_array( $callback, $args );
	}
}
function verify_purge( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-abmm-cache.php';

$request = function () {
	$property = new ReflectionProperty( 'ABMM_Cache', 'instance' );
	$property->setAccessible( true );
	$property->setValue( null, null );
	$GLOBALS['abmm_hooks'] = array();
	return ABMM_Cache::instance();
};

// With no page cache, nothing happens and nothing fails.
$cache = $request();
do_action( 'abmm_menu_saved', 'main', array(), null );
verify_purge( null === $cache->flush(), 'No cache means nothing to report.' );
do_action( 'shutdown' );

// Declared only from here on, so the checks above really had no cache.
if ( ! function_exists( 'nitropack_sdk_purge' ) ) {
	function nitropack_sdk_purge( $url = null, $tag = null, $reason = null, $type = 1 ) {
		$GLOBALS['abmm_purges'][] = array( $url, $tag, $reason );
		if ( isset( $GLOBALS['abmm_purge_throws'] ) ) {
			throw new Exception( 'Network down' );
		}
		return $GLOBALS['abmm_purge_result'] ?? true;
	}
}

// A request that changed nothing clears nothing.
$cache = $request();
verify_purge( null === $cache->flush(), 'Nothing changed, so nothing to report.' );
do_action( 'shutdown' );
verify_purge( array() === $GLOBALS['abmm_purges'], 'Nothing changed, so nothing is cleared.' );

// Several changes in one request clear once, with the whole site as the target.
$cache = $request();
do_action( 'abmm_menu_saved', 'main', array(), null );
do_action( 'abmm_menu_saved', 'other', array(), null );
do_action( 'abmm_menu_deleted', 'old', array() );
verify_purge( array() === $GLOBALS['abmm_purges'], 'Nothing is cleared inside the save lock.' );
verify_purge( true === $cache->flush(), 'The save reports the cache was cleared.' );
verify_purge( true === $cache->flush(), 'Asking again gives the same answer.' );
do_action( 'abmm_menu_saved', 'later', array(), null );
do_action( 'shutdown' );
verify_purge( 1 === count( $GLOBALS['abmm_purges'] ), 'It clears once per request.' );
verify_purge( null === $GLOBALS['abmm_purges'][0][0] && null === $GLOBALS['abmm_purges'][0][1] && is_string( $GLOBALS['abmm_purges'][0][2] ), 'It clears the whole site and gives a reason.' );

// Requests that do not report, such as an import or a deleted menu, clear at the end.
$GLOBALS['abmm_purges'] = array();
$cache                  = $request();
do_action( 'abmm_menu_deleted', 'old', array() );
do_action( 'shutdown' );
verify_purge( 1 === count( $GLOBALS['abmm_purges'] ), 'A delete clears the cache when the request ends.' );

// A failure is reported, never fatal, and a save is not retried in the same request.
$GLOBALS['abmm_purges']       = array();
$GLOBALS['abmm_purge_result'] = false;
$cache                        = $request();
do_action( 'abmm_menu_saved', 'main', array(), null );
verify_purge( false === $cache->flush(), 'A failed clear is reported.' );
unset( $GLOBALS['abmm_purge_result'] );
$GLOBALS['abmm_purge_throws'] = true;
$cache                        = $request();
do_action( 'abmm_menu_saved', 'main', array(), null );
verify_purge( false === $cache->flush(), 'An error while clearing is reported, not thrown.' );

echo "Page cache clearing: once per request, after the save, and safe with no cache passed.\n";
