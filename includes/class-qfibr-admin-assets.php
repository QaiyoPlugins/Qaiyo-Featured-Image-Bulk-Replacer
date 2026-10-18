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
	 * @param string $hook Current admin screen hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( 'toplevel_page_' . Qfibr_Admin_Page::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();

		$css_path = QFIBR_PLUGIN_DIR . 'assets/css/admin.css';
		$js_path  = QFIBR_PLUGIN_DIR . 'assets/js/admin.js';
		$css_ver  = file_exists( $css_path ) ? QFIBR_VERSION . '.' . filemtime( $css_path ) : QFIBR_VERSION;
		$js_ver   = file_exists( $js_path ) ? QFIBR_VERSION . '.' . filemtime( $js_path ) : QFIBR_VERSION;

		wp_enqueue_style( 'qfibr-admin', QFIBR_PLUGIN_URL . 'assets/css/admin.css', array(), $css_ver );
		wp_enqueue_script( 'qfibr-admin', QFIBR_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), $js_ver, true );

		wp_localize_script( 'qfibr-admin', 'qfibr_data', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( Qfibr_Ajax::NONCE ),
			'i18n'     => array(
				'media_title'     => __( 'Select the new featured image', 'qaiyo-featured-image-bulk-replacer' ),
				'media_button'    => __( 'Use this image', 'qaiyo-featured-image-bulk-replacer' ),
				'previewing'      => __( 'Loading preview…', 'qaiyo-featured-image-bulk-replacer' ),
				'preview_error'   => __( 'Could not load the preview.', 'qaiyo-featured-image-bulk-replacer' ),
				'need_image_date' => __( 'Please choose an image and a date first.', 'qaiyo-featured-image-bulk-replacer' ),
				'preparing'       => __( 'Preparing…', 'qaiyo-featured-image-bulk-replacer' ),
				'processing'      => __( 'Processing…', 'qaiyo-featured-image-bulk-replacer' ),
				'nothing'         => __( 'Nothing to process.', 'qaiyo-featured-image-bulk-replacer' ),
				'failed'          => __( 'Something went wrong. Please try again.', 'qaiyo-featured-image-bulk-replacer' ),
				'confirm_test'    => __( 'Run the TEST replacement on the first few matching posts?', 'qaiyo-featured-image-bulk-replacer' ),
				'confirm_live'    => __( 'Run the LIVE bulk featured-image replacement on ALL matching posts?', 'qaiyo-featured-image-bulk-replacer' ),
				'confirm_restore' => __( 'Restore the original featured image of every affected post?', 'qaiyo-featured-image-bulk-replacer' ),
			),
		) );
	}
}
