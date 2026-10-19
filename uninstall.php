<?php
/**
 * Uninstall handler for Qaiyo Featured Image Bulk Replacer.
 *
 * Removes the per-post backup meta, the last-run summary and any leftover job
 * transients. Featured images themselves are never touched on uninstall — use
 * the in-app "Restore originals" button before uninstalling if you want the
 * original images back.
 *
 * On multisite every site is cleaned, not just the one the request happens to
 * run on: the data is per-site, so a network uninstall that only visited the
 * current blog would leave every other site's rows behind.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete this plugin's data on the current site.
 *
 * @return void
 */
function qfibr_uninstall_site() {
	global $wpdb;
	/** @var \wpdb $wpdb */

	delete_post_meta_by_key( '_qfibr_original_thumbnail_id' );
	delete_option( 'qfibr_last_run' );
	delete_transient( 'qfibr_more_plugins_wporg' );

	// Clear any in-progress job transients left behind. There is no options or
	// caching API to bulk-delete by prefix, and the keys are plugin-prefixed.
	$sql = $wpdb->prepare(
		'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
		$wpdb->options,
		$wpdb->esc_like( '_transient_qfibr_job_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_qfibr_job_' ) . '%'
	);
	if ( is_string( $sql ) ) {
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; there is no API to bulk-delete transients by prefix.
	}
}

if ( is_multisite() ) {
	$qfibr_sites = get_sites( array(
		'fields' => 'ids',
		'number' => 0,
	) );

	foreach ( $qfibr_sites as $qfibr_site_id ) {
		switch_to_blog( (int) $qfibr_site_id );
		qfibr_uninstall_site();
		restore_current_blog();
	}

	unset( $qfibr_sites, $qfibr_site_id );
} else {
	qfibr_uninstall_site();
}
