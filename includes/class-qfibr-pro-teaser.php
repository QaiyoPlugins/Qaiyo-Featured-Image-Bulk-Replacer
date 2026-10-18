<?php
/**
 * Freemium teaser: locked Pro feature cards shown when Pro is not active.
 *
 * The free plugin owns the whole catalogue (labels, descriptions, icons) and
 * renders the locked cards. The Pro plugin does not unlock anything here — it
 * replaces this section with its real UI through the qfibr_pro_admin_cards
 * action, so nothing in the free plugin is gated behind a licence.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the locked Pro cards.
 */
final class Qfibr_Pro_Teaser {

	/**
	 * The teaser catalogue.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function features() {
		return array(
			array(
				'title' => __( 'Background processing & scheduling', 'qaiyo-featured-image-bulk-replacer' ),
				'desc'  => __( 'Offload large runs to a server-friendly background queue with adaptive batch size, throttling and an off-peak window — or schedule a run for later. No need to keep the browser open.', 'qaiyo-featured-image-bulk-replacer' ),
				'icon'  => 'dashicons-clock',
			),
			array(
				'title' => __( 'Automatic featured image', 'qaiyo-featured-image-bulk-replacer' ),
				'desc'  => __( 'Automatically set a fallback featured image when a post is published without one — by post type and category, optionally using the first image found in the content.', 'qaiyo-featured-image-bulk-replacer' ),
				'icon'  => 'dashicons-update',
			),
			array(
				'title' => __( 'Run history & per-run restore', 'qaiyo-featured-image-bulk-replacer' ),
				'desc'  => __( 'Keep a history of every run and roll back any single run independently, plus an email report when a background job finishes.', 'qaiyo-featured-image-bulk-replacer' ),
				'icon'  => 'dashicons-backup',
			),
		);
	}

	/**
	 * The padlock badge drawn over each locked preview.
	 *
	 * @return string
	 */
	public static function lock_svg() {
		return '<svg class="qfibr-lock-svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="#fff" d="M6 10V8a6 6 0 1 1 12 0v2h1a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1h1zm2 0h8V8a4 4 0 1 0-8 0v2z"/></svg>';
	}

	/**
	 * Echo the teaser card.
	 *
	 * @return void
	 */
	public static function output() {
		Qfibr_View::output( 'pro-teaser', array(
			'qfibr_url'      => Qfibr_Plugin::upgrade_url(),
			'qfibr_lock'     => self::lock_svg(),
			'qfibr_unlock'   => esc_html__( 'Unlock in Pro →', 'qaiyo-featured-image-bulk-replacer' ),
			'qfibr_features' => self::features(),
		) );
	}
}
