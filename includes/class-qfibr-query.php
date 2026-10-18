<?php
/**
 * Post enumeration for a run.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the list of post IDs a run will touch.
 */
final class Qfibr_Query {

	/**
	 * Target post IDs matching the criteria.
	 *
	 * @param array<string, mixed> $c     Normalised criteria.
	 * @param int|null             $limit Null = no limit.
	 * @return int[]
	 */
	public static function target_ids( array $c, $limit = null ) {
		$args = array(
			'post_type'              => $c['post_type'],
			'post_status'            => 'publish',
			'posts_per_page'         => $limit ? (int) $limit : -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin-only bulk maintenance tool must enumerate every matching post; processing itself is batched.
			'fields'                 => 'ids',
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$date_query = array( 'relation' => 'AND' );
		if ( Qfibr_Criteria::is_valid_date( $c['before_date'] ) ) {
			$date_query[] = array(
				'before'    => $c['before_date'] . ' 00:00:00',
				'inclusive' => false,
				'column'    => 'post_date',
			);
		}
		if ( Qfibr_Criteria::is_valid_date( $c['after_date'] ) ) {
			$date_query[] = array(
				'after'     => $c['after_date'] . ' 00:00:00',
				'inclusive' => true,
				'column'    => 'post_date',
			);
		}
		if ( count( $date_query ) > 1 ) {
			$args['date_query'] = $date_query;
		}

		if ( 'missing' === $c['scope'] ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only maintenance tool, explicit user action.
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				),
			);
		}

		if ( $c['category'] > 0 && is_object_in_taxonomy( $c['post_type'], 'category' ) ) {
			$args['category__in'] = array( $c['category'] );
		}

		$query = new WP_Query( $args );
		return array_map( 'intval', $query->posts );
	}

	/**
	 * IDs of every post that still carries a stored original.
	 *
	 * @return int[]
	 */
	public static function restorable_ids() {
		$query = new WP_Query( array(
			'post_type'              => 'any',
			'post_status'            => 'any',
			'posts_per_page'         => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin-only restore enumeration; processing is batched.
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only maintenance tool, explicit user action.
				array(
					'key'     => Qfibr_Replacer::META_BACKUP_KEY,
					'compare' => 'EXISTS',
				),
			),
		) );
		return array_map( 'intval', $query->posts );
	}

	/**
	 * How many posts are currently restorable.
	 *
	 * @return int
	 */
	public static function restorable_count() {
		return count( self::restorable_ids() );
	}
}
