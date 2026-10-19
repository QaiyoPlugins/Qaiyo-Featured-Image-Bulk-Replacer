<?php
/**
 * Post enumeration for a run.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the list of post IDs a run will touch.
 *
 * @phpstan-import-type Qfibr_Criteria_Shape from Qfibr_Criteria
 */
final class Qfibr_Query {

	/**
	 * WP_Query arguments for a criteria set.
	 *
	 * Kept separate from the query itself so the filtering rules are testable
	 * without a database.
	 *
	 * @param Qfibr_Criteria_Shape $c     Sanitised criteria.
	 * @param int|null             $limit Null = no limit.
	 * @return array<string, mixed>
	 */
	public static function build_args( array $c, $limit = null ) {
		$args = array(
			'post_type'              => $c['post_type'],
			'post_status'            => 'publish',
			'posts_per_page'         => $limit ? (int) $limit : -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin-only bulk maintenance must enumerate every match; processing itself is batched.
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

		$tax_query = array();

		if ( $c['category'] > 0 && is_object_in_taxonomy( $c['post_type'], 'category' ) ) {
			$tax_query[] = self::tax_clause( 'category', $c['category'] );
		}
		if ( $c['tag'] > 0 && is_object_in_taxonomy( $c['post_type'], 'post_tag' ) ) {
			$tax_query[] = self::tax_clause( 'post_tag', $c['tag'] );
		}
		if ( '' !== $c['taxonomy'] && $c['term'] > 0 && taxonomy_exists( $c['taxonomy'] ) && is_object_in_taxonomy( $c['post_type'], $c['taxonomy'] ) ) {
			$tax_query[] = self::tax_clause( $c['taxonomy'], $c['term'] );
		}

		if ( array() !== $tax_query ) {
			$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Admin-only maintenance tool, explicit user action.
		}

		if ( $c['author'] > 0 ) {
			$args['author'] = $c['author'];
		}

		/**
		 * Filters the WP_Query arguments of a run (add-ons can narrow it, e.g.
		 * WooCommerce stock status). The criteria are passed read-only.
		 *
		 * @param array $args     WP_Query arguments.
		 * @param array $criteria Sanitised criteria.
		 */
		$filtered = apply_filters( 'qfibr_query_args', $args, $c );

		return is_array( $filtered ) ? self::string_keys( $filtered ) : $args;
	}

	/**
	 * Filters that were chosen but do not apply to the chosen post type.
	 *
	 * Silently dropping such a filter would WIDEN a destructive run (a
	 * category picked for Pages would otherwise match every page), so the
	 * caller refuses the run instead.
	 *
	 * @param Qfibr_Criteria_Shape $c Sanitised criteria.
	 * @return list<string> Taxonomy names that do not apply.
	 */
	public static function inapplicable_filters( array $c ) {
		$wanted = array();
		if ( $c['category'] > 0 ) {
			$wanted[] = 'category';
		}
		if ( $c['tag'] > 0 ) {
			$wanted[] = 'post_tag';
		}
		if ( '' !== $c['taxonomy'] && $c['term'] > 0 ) {
			$wanted[] = $c['taxonomy'];
		}

		$bad = array();
		foreach ( $wanted as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $c['post_type'], $taxonomy ) ) {
				$bad[] = $taxonomy;
			}
		}
		return $bad;
	}

	/**
	 * Target post IDs matching the criteria.
	 *
	 * @param Qfibr_Criteria_Shape $c     Sanitised criteria.
	 * @param int|null             $limit Null = no limit.
	 * @return list<int>
	 */
	public static function target_ids( array $c, $limit = null ) {
		$query = new WP_Query( self::build_args( $c, $limit ) );
		return self::ids( $query->posts );
	}

	/**
	 * IDs of every post that still carries a stored original.
	 *
	 * @return list<int>
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
		return self::ids( $query->posts );
	}

	/**
	 * How many posts are currently restorable.
	 *
	 * @return int
	 */
	public static function restorable_count() {
		return count( self::restorable_ids() );
	}

	/**
	 * Narrow a WP_Query result to a list of positive ints.
	 *
	 * @param mixed $posts Query result.
	 * @return list<int>
	 */
	public static function ids( $posts ) {
		$ids = array();
		foreach ( Qfibr_Cast::arr( $posts ) as $post ) {
			$id = ( $post instanceof WP_Post ) ? (int) $post->ID : Qfibr_Cast::int( $post );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * One tax_query clause by term ID.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $term     Term ID.
	 * @return array<string, mixed>
	 */
	private static function tax_clause( $taxonomy, $term ) {
		return array(
			'taxonomy'         => $taxonomy,
			'field'            => 'term_id',
			'terms'            => array( $term ),
			'include_children' => false,
		);
	}

	/**
	 * Keep only string keys of a filtered argument array.
	 *
	 * @param array<array-key, mixed> $args Arguments.
	 * @return array<string, mixed>
	 */
	private static function string_keys( array $args ) {
		$clean = array();
		foreach ( $args as $key => $value ) {
			if ( is_string( $key ) ) {
				$clean[ $key ] = $value;
			}
		}
		return $clean;
	}
}
