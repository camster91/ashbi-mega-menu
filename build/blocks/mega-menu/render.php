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

// Same output as the block's render callback, including its alignment wrapper.
echo ABMM_Block::instance()->render_block( $attributes, $content ?? '', $block ?? null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
