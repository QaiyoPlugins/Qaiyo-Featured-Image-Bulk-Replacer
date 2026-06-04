<?php
/**
 * Uninstall handler for Qaiyo Featured Image Bulk Replacer.
 *
 * Removes the per-post backup meta and the last-run summary option. Featured
 * images themselves are never touched on uninstall — use the in-app
 * "Restore originals" button before uninstalling if you want to revert.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_post_meta_by_key( '_qfibr_original_thumbnail_id' );
delete_option( 'qfibr_last_run' );

// Clear any in-progress job transients left behind.
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off cleanup on uninstall; transient keys are plugin-prefixed and there is no caching/options API to bulk-delete by prefix.
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_qfibr\_job\_%' OR option_name LIKE '\_transient\_timeout\_qfibr\_job\_%'"
);
