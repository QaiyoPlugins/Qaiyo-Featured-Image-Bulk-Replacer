<?php
/**
 * Broken featured images: posts whose `_thumbnail_id` points at an attachment
 * that no longer exists (deleted outside WordPress, a failed migration, a raw
 * database edit). Such a reference renders as a missing image on the front end
 * and confuses themes that check has_post_thumbnail().
 *
 * Only dangling references are handled. An attachment whose FILE is missing is
 * deliberately left alone: offloaded media (S3, CDN plugins) has no local file
 * by design, so treating that as "broken" would strip valid images.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finds and cleans dangling featured-image references.
 */
final class Qfibr_Orphans {

	/** Upper bound for one scan — keeps the queue and the request bounded. */
	const SCAN_LIMIT = 10000;

	/** Rows shown in the scan result. */
	const SAMPLE = 20;

	/**
	 * Posts whose featured image reference is dangling.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<int> Post IDs.
	 */
	public static function find( $limit = self::SCAN_LIMIT ) {
		global $wpdb;
		/** @var \wpdb $wpdb */

		$sql = $wpdb->prepare(
			'SELECT pm.post_id FROM %i AS pm
			INNER JOIN %i AS p ON p.ID = pm.post_id
			LEFT JOIN %i AS a ON a.ID = pm.meta_value AND a.post_type = %s
			WHERE pm.meta_key = %s AND pm.meta_value <> %s AND pm.meta_value <> %s AND a.ID IS NULL
			ORDER BY pm.post_id ASC LIMIT %d',
			$wpdb->postmeta,
			$wpdb->posts,
			$wpdb->posts,
			'attachment',
			'_thumbnail_id',
			'',
			'0',
			max( 1, (int) $limit )
		);

		if ( ! is_string( $sql ) ) {
			return array();
		}

		$rows = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; a JOIN across postmeta/posts has no API equivalent, and this is an explicit admin scan.

		return Qfibr_Query::ids( $rows );
	}

	/**
	 * Whether a post's featured image reference is dangling right now.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_orphan( $post_id ) {
		$thumb = Qfibr_Cast::int( get_post_meta( (int) $post_id, '_thumbnail_id', true ) );
		if ( $thumb <= 0 ) {
			return false;
		}
		return 'attachment' !== get_post_type( $thumb );
	}

	/**
	 * Clean one post, re-checking first: between the scan and this batch an
	 * editor may already have set a valid image.
	 *
	 * @param int $post_id Post ID.
	 * @return string 'removed' | 'skipped' | 'error'
	 */
	public static function clean_one( $post_id ) {
		if ( ! self::is_orphan( $post_id ) ) {
			return 'skipped';
		}
		return Qfibr_Replacer::remove_one( (int) $post_id );
	}

	/**
	 * Scan result for the admin screen.
	 *
	 * @return array{total: int, sample: list<array{id: int, title: string, edit: string}>}
	 */
	public static function summary() {
		$ids    = self::find();
		$sample = array();

		foreach ( array_slice( $ids, 0, self::SAMPLE ) as $id ) {
			$title    = get_the_title( $id );
			$sample[] = array(
				'id'    => $id,
				'title' => '' !== $title ? $title : sprintf( '#%d', $id ),
				'edit'  => (string) get_edit_post_link( $id, 'raw' ),
			);
		}

		return array(
			'total'  => count( $ids ),
			'sample' => $sample,
		);
	}
}
