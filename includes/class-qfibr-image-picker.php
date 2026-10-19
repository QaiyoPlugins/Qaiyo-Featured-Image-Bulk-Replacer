<?php
/**
 * Chooses which image a post gets when a run assigns from a pool.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Deterministic "random" assignment.
 *
 * The choice is a hash of the run's seed and the post ID rather than a fresh
 * random number: a batch that is retried after a lost response then gives the
 * same post the same image, instead of re-rolling it.
 */
final class Qfibr_Image_Picker {

	/**
	 * Pick an attachment for a post.
	 *
	 * @param list<int> $pool    Candidate attachment IDs.
	 * @param string    $seed    Run seed.
	 * @param int       $post_id Post ID.
	 * @return int Attachment ID, or 0 for an empty pool.
	 */
	public static function pick( array $pool, $seed, $post_id ) {
		$count = count( $pool );
		if ( 0 === $count ) {
			return 0;
		}

		$hash  = (int) sprintf( '%u', crc32( $seed . ':' . $post_id ) );
		$index = $hash % $count;

		return $pool[ $index ];
	}

	/**
	 * A fresh seed for a new run.
	 *
	 * @return string
	 */
	public static function new_seed() {
		return wp_generate_password( 12, false );
	}
}
