<?php
/**
 * Server-side render template for the Ashbi Mega Menu block.
 *
 * @package Ashbi_Mega_Menu
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $attributes['menuId'] ) ) {
	return;
}

echo ABMM_Frontend::instance()->render( $attributes['menuId'], array( 'context' => 'page' === ( $attributes['context'] ?? '' ) ? 'page' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
