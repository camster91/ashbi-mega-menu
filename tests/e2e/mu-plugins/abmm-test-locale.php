<?php
/**
 * Test-only must-use plugin for the browser tests. Installed into a
 * disposable site by tests/e2e/seed.php; never shipped in the plugin ZIP.
 *
 * ?abmm_test_locale=rtl   right-to-left text direction (as for Arabic or Hebrew)
 * ?abmm_test_locale=long  every plugin string about 40% longer, as in German
 *                         or Finnish translations, with accented characters
 */
defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test fixture, read-only.
$abmm_test_locale = isset( $_GET['abmm_test_locale'] ) ? sanitize_key( wp_unslash( $_GET['abmm_test_locale'] ) ) : '';
if ( '' === $abmm_test_locale && isset( $_COOKIE['abmm_test_locale'] ) ) {
	$abmm_test_locale = sanitize_key( wp_unslash( $_COOKIE['abmm_test_locale'] ) );
}

if ( 'rtl' === $abmm_test_locale ) {
	add_action(
		'init',
		function () {
			$GLOBALS['wp_locale']->text_direction = 'rtl';
		},
		1
	);
}

if ( 'long' === $abmm_test_locale ) {
	add_filter(
		'gettext_ashbi-mega-menu',
		function ( $translation ) {
			if ( '' === trim( $translation ) || false !== strpos( $translation, '<' ) ) {
				return $translation;
			}
			$extra = (int) ceil( mb_strlen( $translation ) * 0.4 );
			return $translation . ' ' . mb_substr( str_repeat( 'Überlängé ', 20 ), 0, max( 3, $extra ) );
		}
	);
}
