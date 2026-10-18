<?php
/**
 * Batch execution over a queued job.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walks a job's ID queue and keeps its counters.
 */
final class Qfibr_Runner {

	/**
	 * Build a fresh job array.
	 *
	 * @param string $type          'replace' or 'restore'.
	 * @param int[]  $ids           Queued post IDs.
	 * @param array  $extra         Extra job fields (mode, attachment_id).
	 * @return array<string, mixed>
	 */
	public static function make_job( $type, array $ids, array $extra = array() ) {
		return array_merge( array(
			'type'      => ( 'restore' === $type ) ? 'restore' : 'replace',
			'ids'       => array_values( array_map( 'intval', $ids ) ),
			'total'     => count( $ids ),
			'done'      => 0,
			'processed' => 0,
			'skipped'   => 0,
			'errors'    => 0,
		), $extra );
	}

	/**
	 * Process the next slice of a job, updating its counters in place.
	 *
	 * @param array<string, mixed> $job  Job data, mutated.
	 * @param int                  $size How many posts to handle.
	 * @return array<string, mixed> The updated job.
	 */
	public static function process_slice( array $job, $size ) {
		$slice = array_slice( $job['ids'], (int) $job['done'], (int) $size );

		foreach ( $slice as $pid ) {
			if ( 'restore' === $job['type'] ) {
				$status = Qfibr_Replacer::restore_one( (int) $pid );
				$ok_key = 'restored';
			} else {
				$status = Qfibr_Replacer::replace_one( (int) $pid, (int) $job['attachment_id'] );
				$ok_key = 'replaced';
			}

			$job = self::tally( $job, $status, $ok_key );
			++$job['done'];
		}

		return $job;
	}

	/**
	 * Map a per-post status onto the job counters.
	 *
	 * @param array<string, mixed> $job    Job data.
	 * @param string               $status Status returned by the replacer.
	 * @param string               $ok_key Status counting as success.
	 * @return array<string, mixed>
	 */
	public static function tally( array $job, $status, $ok_key ) {
		if ( $ok_key === $status ) {
			++$job['processed'];
		} elseif ( 'skipped' === $status ) {
			++$job['skipped'];
		} else {
			++$job['errors'];
		}
		return $job;
	}

	/**
	 * Whether every queued post has been handled.
	 *
	 * @param array<string, mixed> $job Job data.
	 * @return bool
	 */
	public static function is_finished( array $job ) {
		return (int) $job['done'] >= (int) $job['total'];
	}
}
