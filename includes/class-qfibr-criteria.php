<?php
/**
 * Replacement criteria — reading, sanitising and validating the filter set that
 * decides which posts a run touches and which image(s) they get.
 *
 * Kept free of request handling on purpose: the AJAX layer verifies the nonce
 * and capability, then hands the raw input here. That keeps the rules that
 * decide "which posts get overwritten" unit-testable on their own.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sanitises and validates run criteria.
 *
 * @phpstan-type Qfibr_Criteria_Shape array{
 *     attachment_id: int,
 *     attachment_ids: list<int>,
 *     assign_mode: string,
 *     image_url: string,
 *     before_date: string,
 *     after_date: string,
 *     post_type: string,
 *     scope: string,
 *     category: int,
 *     tag: int,
 *     author: int,
 *     taxonomy: string,
 *     term: int,
 *     alt_text: string,
 *     extra: array<string, string>
 * }
 */
final class Qfibr_Criteria {

	/** Most images a random pool may hold. */
	const MAX_POOL = 50;

	/** Most add-on fields accepted through the generic "extra" channel. */
	const MAX_EXTRA = 20;

	/**
	 * Default criteria shape.
	 *
	 * @return Qfibr_Criteria_Shape
	 */
	public static function defaults() {
		return array(
			'attachment_id'  => 0,
			'attachment_ids' => array(),
			'assign_mode'    => 'single',
			'image_url'      => '',
			'before_date'    => '',
			'after_date'     => '',
			'post_type'      => 'post',
			'scope'          => 'all',
			'category'       => 0,
			'tag'            => 0,
			'author'         => 0,
			'taxonomy'       => '',
			'term'           => 0,
			'alt_text'       => '',
			'extra'          => array(),
		);
	}

	/**
	 * Build a sanitised criteria array from raw (unslashed) input.
	 *
	 * Also the single narrowing point for anything partially filled — presets,
	 * the public API, the Pro queue and WP-CLI all come through here.
	 *
	 * @param array<array-key, mixed> $raw Raw values.
	 * @return Qfibr_Criteria_Shape
	 */
	public static function from_array( array $raw ) {
		$scope = sanitize_key( Qfibr_Cast::string( $raw['scope'] ?? 'all' ) );
		$type  = sanitize_key( Qfibr_Cast::string( $raw['post_type'] ?? 'post' ) );

		list( $taxonomy, $term ) = self::parse_tax_term( $raw );

		$pool = self::parse_ids( $raw['attachment_ids'] ?? array() );
		$one  = absint( Qfibr_Cast::int( $raw['attachment_id'] ?? 0 ) );
		$mode = ( 'random' === Qfibr_Cast::string( $raw['assign_mode'] ?? '' ) && count( $pool ) >= 2 ) ? 'random' : 'single';

		if ( 'single' === $mode && 0 === $one && array() !== $pool ) {
			$one = $pool[0];
		}

		return array(
			'attachment_id'  => $one,
			'attachment_ids' => 'random' === $mode ? $pool : array(),
			'assign_mode'    => $mode,
			'image_url'      => esc_url_raw( Qfibr_Cast::string( $raw['image_url'] ?? '' ) ),
			'before_date'    => sanitize_text_field( Qfibr_Cast::string( $raw['before_date'] ?? '' ) ),
			'after_date'     => sanitize_text_field( Qfibr_Cast::string( $raw['after_date'] ?? '' ) ),
			'post_type'      => '' !== $type ? $type : 'post',
			'scope'          => ( 'missing' === $scope ) ? 'missing' : 'all',
			'category'       => absint( Qfibr_Cast::int( $raw['category'] ?? 0 ) ),
			'tag'            => absint( Qfibr_Cast::int( $raw['tag'] ?? 0 ) ),
			'author'         => absint( Qfibr_Cast::int( $raw['author'] ?? 0 ) ),
			'taxonomy'       => $taxonomy,
			'term'           => $term,
			'alt_text'       => sanitize_text_field( Qfibr_Cast::string( $raw['alt_text'] ?? '' ) ),
			'extra'          => self::parse_extra( $raw['extra'] ?? array() ),
		);
	}

	/**
	 * Normalise a partially filled criteria array (public API, presets).
	 *
	 * @param array<array-key, mixed> $criteria Raw or partial criteria.
	 * @return Qfibr_Criteria_Shape
	 */
	public static function normalize( array $criteria ) {
		return self::from_array( array_merge( self::defaults(), $criteria ) );
	}

	/**
	 * Run the criteria through the `qfibr_criteria` filter and narrow the
	 * result again, so an add-on returning garbage cannot break the run.
	 *
	 * @param Qfibr_Criteria_Shape $criteria Sanitised criteria.
	 * @return Qfibr_Criteria_Shape
	 */
	public static function filtered( array $criteria ) {
		/**
		 * Filters the sanitised run criteria. Add-ons read their own keys from
		 * `extra` (already sanitised strings) and may adjust the criteria.
		 *
		 * @param array $criteria Criteria.
		 */
		$filtered = apply_filters( 'qfibr_criteria', $criteria );

		return is_array( $filtered ) ? self::normalize( $filtered ) : $criteria;
	}

	/**
	 * A run must be bounded. Without a valid "before" date every published post
	 * of the chosen type would match, which is never what a bulk replacement is
	 * asked to do — the free UI marks the field required and the Pro queue and
	 * WP-CLI rely on this same check before they write anything.
	 *
	 * @param array<array-key, mixed> $criteria Criteria to check.
	 * @return bool
	 */
	public static function is_bounded( array $criteria ) {
		return self::is_valid_date( $criteria['before_date'] ?? '' );
	}

	/**
	 * Strict Y-m-d validation, including real calendar dates.
	 *
	 * @param mixed $date Candidate date string.
	 * @return bool
	 */
	public static function is_valid_date( $date ) {
		if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		$parts = explode( '-', $date );
		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
	}

	/**
	 * Parse a list of attachment IDs (array or comma-separated string).
	 *
	 * @param mixed $value Raw value.
	 * @return list<int> Unique positive IDs, at most MAX_POOL.
	 */
	public static function parse_ids( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		$ids = array();
		foreach ( Qfibr_Cast::arr( $value ) as $item ) {
			$id = absint( Qfibr_Cast::int( $item ) );
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
			if ( count( $ids ) >= self::MAX_POOL ) {
				break;
			}
		}

		return $ids;
	}

	/**
	 * Read the taxonomy filter — either explicit keys, or the UI's combined
	 * "taxonomy:term_id" value.
	 *
	 * @param array<array-key, mixed> $raw Raw values.
	 * @return array{0: string, 1: int}
	 */
	private static function parse_tax_term( array $raw ) {
		$taxonomy = sanitize_key( Qfibr_Cast::string( $raw['taxonomy'] ?? '' ) );
		$term     = absint( Qfibr_Cast::int( $raw['term'] ?? 0 ) );

		$combined = Qfibr_Cast::string( $raw['tax_term'] ?? '' );
		if ( '' !== $combined && false !== strpos( $combined, ':' ) ) {
			list( $tax_part, $term_part ) = explode( ':', $combined, 2 );
			$taxonomy = sanitize_key( $tax_part );
			$term     = absint( $term_part );
		}

		if ( '' === $taxonomy || 0 === $term ) {
			return array( '', 0 );
		}

		return array( $taxonomy, $term );
	}

	/**
	 * Narrow the generic add-on field channel to short sanitised strings.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, string>
	 */
	private static function parse_extra( $value ) {
		$extra = array();
		foreach ( Qfibr_Cast::arr( $value ) as $key => $item ) {
			$key = sanitize_key( Qfibr_Cast::string( $key ) );
			if ( '' === $key ) {
				continue;
			}
			$extra[ $key ] = substr( sanitize_text_field( Qfibr_Cast::string( $item ) ), 0, 200 );
			if ( count( $extra ) >= self::MAX_EXTRA ) {
				break;
			}
		}
		return $extra;
	}
}
