<?php
/**
 * Replacement criteria — reading, sanitising and validating the filter set that
 * decides which posts a run touches.
 *
 * Kept free of WordPress request handling on purpose: the AJAX layer verifies
 * the nonce and capability, then hands the raw input here. That makes the rules
 * that decide "which posts get overwritten" unit-testable on their own.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sanitises and validates run criteria.
 */
final class Qfibr_Criteria {

	/**
	 * Default criteria shape.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'attachment_id' => 0,
			'image_url'     => '',
			'before_date'   => '',
			'after_date'    => '',
			'post_type'     => 'post',
			'scope'         => 'all',
			'category'      => 0,
			'alt_text'      => '',
		);
	}

	/**
	 * Build a sanitised criteria array from raw request input.
	 *
	 * @param array<string, mixed> $raw Raw, unslashed request values.
	 * @return array<string, mixed>
	 */
	public static function from_array( array $raw ) {
		$scope = isset( $raw['scope'] ) ? sanitize_key( (string) $raw['scope'] ) : 'all';

		return array(
			'attachment_id' => isset( $raw['attachment_id'] ) ? absint( $raw['attachment_id'] ) : 0,
			'image_url'     => isset( $raw['image_url'] ) ? esc_url_raw( (string) $raw['image_url'] ) : '',
			'before_date'   => isset( $raw['before_date'] ) ? sanitize_text_field( (string) $raw['before_date'] ) : '',
			'after_date'    => isset( $raw['after_date'] ) ? sanitize_text_field( (string) $raw['after_date'] ) : '',
			'post_type'     => isset( $raw['post_type'] ) ? sanitize_key( (string) $raw['post_type'] ) : 'post',
			'scope'         => ( 'missing' === $scope ) ? 'missing' : 'all',
			'category'      => isset( $raw['category'] ) ? absint( $raw['category'] ) : 0,
			'alt_text'      => isset( $raw['alt_text'] ) ? sanitize_text_field( (string) $raw['alt_text'] ) : '',
		);
	}

	/**
	 * Normalise a partially filled criteria array coming from the public API.
	 *
	 * @param array<string, mixed> $criteria Raw or partial criteria.
	 * @return array<string, mixed>
	 */
	public static function normalize( array $criteria ) {
		$c = wp_parse_args( $criteria, self::defaults() );

		$c['post_type']   = sanitize_key( (string) $c['post_type'] );
		$c['scope']       = ( 'missing' === $c['scope'] ) ? 'missing' : 'all';
		$c['category']    = absint( $c['category'] );
		$c['before_date'] = sanitize_text_field( (string) $c['before_date'] );
		$c['after_date']  = sanitize_text_field( (string) $c['after_date'] );

		return $c;
	}

	/**
	 * A run must be bounded. Without a valid "before" date every published post
	 * of the chosen type would match, which is never what a bulk replacement is
	 * asked to do — the free UI marks the field required and the Pro background
	 * queue relies on this same check before it writes anything.
	 *
	 * @param array<string, mixed> $criteria Criteria to check.
	 * @return bool
	 */
	public static function is_bounded( array $criteria ) {
		return self::is_valid_date( isset( $criteria['before_date'] ) ? $criteria['before_date'] : '' );
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
}
