<?php
/**
 * Escaping allowlists.
 *
 * Inline SVG cannot go through esc_html()/esc_attr() without being destroyed,
 * and suppressing the escaping sniff with phpcs:ignore only hides the question.
 * These allowlists let wp_kses() do the real work, so every byte that reaches
 * the browser has been through an explicit filter.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * wp_kses() allowlists used by this plugin.
 */
final class Qfibr_Kses {

	/**
	 * Allowlist for the small decorative inline SVGs (the Pro lock badge).
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function svg() {
		return array(
			'svg'  => array(
				'class'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'xmlns'       => true,
				'aria-hidden' => true,
				'focusable'   => true,
				'role'        => true,
			),
			'path' => array(
				'd'         => true,
				'fill'      => true,
				'fill-rule' => true,
				'clip-rule' => true,
			),
			'g'    => array(
				'fill'      => true,
				'transform' => true,
			),
		);
	}
}
