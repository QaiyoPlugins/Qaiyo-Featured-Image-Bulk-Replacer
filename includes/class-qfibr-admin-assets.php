<?php
/**
 * Admin CSS/JS registration for this plugin's screen.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the admin assets and hands the translated strings to JavaScript.
 */
final class Qfibr_Admin_Assets {

	/**
	 * Bind the enqueue hook.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Enqueue assets on this plugin's screen only.
	 *
	 * @param mixed $hook Current admin screen hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( 'toplevel_page_' . Qfibr_Admin_Page::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style( 'qfibr-admin', QFIBR_PLUGIN_URL . 'assets/css/admin.css', array(), qfibr_asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'qfibr-admin', QFIBR_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'wp-a11y' ), qfibr_asset_version( 'assets/js/admin.js' ), true );

		wp_localize_script( 'qfibr-admin', 'qfibr_data', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( Qfibr_Ajax::NONCE ),
			'i18n'     => self::strings(),
		) );
	}

	/**
	 * Translated strings used by admin.js.
	 *
	 * @return array<string, string>
	 */
	public static function strings() {
		return array(
			'media_title'      => __( 'Select the new featured image', 'qaiyo-featured-image-bulk-replacer' ),
			'media_title_many' => __( 'Select the images to assign at random', 'qaiyo-featured-image-bulk-replacer' ),
			'media_button'     => __( 'Use this image', 'qaiyo-featured-image-bulk-replacer' ),
			'media_button_many' => __( 'Use these images', 'qaiyo-featured-image-bulk-replacer' ),
			'choose_one'       => __( 'Choose from Media Library', 'qaiyo-featured-image-bulk-replacer' ),
			'choose_many'      => __( 'Choose images', 'qaiyo-featured-image-bulk-replacer' ),
			/* translators: %d: number of selected images. */
			'selected'         => __( 'Images selected: %d', 'qaiyo-featured-image-bulk-replacer' ),
			'remove_image'     => __( 'Remove this image from the selection', 'qaiyo-featured-image-bulk-replacer' ),
			'previewing'       => __( 'Loading preview…', 'qaiyo-featured-image-bulk-replacer' ),
			'preview_ready'    => __( 'Preview loaded.', 'qaiyo-featured-image-bulk-replacer' ),
			'preview_error'    => __( 'Could not load the preview.', 'qaiyo-featured-image-bulk-replacer' ),
			'need_image'       => __( 'Choose an image from the Media Library or paste an image URL.', 'qaiyo-featured-image-bulk-replacer' ),
			'need_pool'        => __( 'Choose at least two images for random assignment.', 'qaiyo-featured-image-bulk-replacer' ),
			'need_date'        => __( 'Enter the “before” date.', 'qaiyo-featured-image-bulk-replacer' ),
			'preparing'        => __( 'Preparing…', 'qaiyo-featured-image-bulk-replacer' ),
			'processing'       => __( 'Processing…', 'qaiyo-featured-image-bulk-replacer' ),
			/* translators: 1: processed posts, 2: skipped posts, 3: errors. */
			'finished'         => __( 'Finished: %1$d processed, %2$d skipped, %3$d errors.', 'qaiyo-featured-image-bulk-replacer' ),
			'nothing'          => __( 'Nothing to process.', 'qaiyo-featured-image-bulk-replacer' ),
			'failed'           => __( 'Something went wrong. Please try again.', 'qaiyo-featured-image-bulk-replacer' ),
			'scanning'         => __( 'Scanning…', 'qaiyo-featured-image-bulk-replacer' ),
			'confirm_test'     => __( 'Run the TEST replacement on the first few matching posts?', 'qaiyo-featured-image-bulk-replacer' ),
			'confirm_live'     => __( 'Run the LIVE bulk featured-image replacement on ALL matching posts?', 'qaiyo-featured-image-bulk-replacer' ),
			'confirm_restore'  => __( 'Restore the original featured image of every affected post?', 'qaiyo-featured-image-bulk-replacer' ),
			'confirm_cleanup'  => __( 'Remove the broken featured image references? You can undo it with “Restore originals”.', 'qaiyo-featured-image-bulk-replacer' ),
		);
	}
}
