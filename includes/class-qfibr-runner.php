<?php
/**
 * Batch execution over a queued job.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walks a job's ID queue and keeps its counters.
 *
 * @phpstan-type Qfibr_Job array{
 *     type: string,
 *     mode: string,
 *     ids: list<int>,
 *     total: int,
 *     done: int,
 *     processed: int,
 *     skipped: int,
 *     errors: int,
 *     attachment_id: int,
 *     attachment_ids: list<int>,
 *     seed: string,
 *     changed: list<int>
 * }
 */
final class Qfibr_Runner {

	/** Job types and the status each one counts as success. */
	const TYPES = array(
		'replace' => 'replaced',
		'restore' => 'restored',
		'cleanup' => 'removed',
	);

	/**
	 * Build a fresh job.
	 *
	 * @param string                  $type  'replace', 'restore' or 'cleanup'.
	 * @param array<array-key, mixed> $ids   Queued post IDs.
	 * @param array<array-key, mixed> $extra mode / attachment_id / attachment_ids / seed.
	 * @return Qfibr_Job
	 */
	public static function make_job( $type, array $ids, array $extra = array() ) {
		$queue = Qfibr_Query::ids( $ids );

		return array(
			'type'           => isset( self::TYPES[ $type ] ) ? $type : 'replace',
			'mode'           => ( 'test' === Qfibr_Cast::string( $extra['mode'] ?? '' ) ) ? 'test' : 'live',
			'ids'            => $queue,
			'total'          => count( $queue ),
			'done'           => 0,
			'processed'      => 0,
			'skipped'        => 0,
			'errors'         => 0,
			'attachment_id'  => absint( Qfibr_Cast::int( $extra['attachment_id'] ?? 0 ) ),
			'attachment_ids' => Qfibr_Criteria::parse_ids( $extra['attachment_ids'] ?? array() ),
			'seed'           => Qfibr_Cast::string( $extra['seed'] ?? '' ),
			'changed'        => array(),
		);
	}

	/**
	 * Narrow a stored (untyped) job back to its shape.
	 *
	 * @param mixed $value Stored value.
	 * @return Qfibr_Job|null
	 */
	public static function from_store( $value ) {
		if ( ! is_array( $value ) || ! isset( $value['type'], $value['ids'] ) ) {
			return null;
		}

		$job = self::make_job( Qfibr_Cast::string( $value['type'] ), Qfibr_Cast::arr( $value['ids'] ), $value );

		foreach ( array( 'done', 'processed', 'skipped', 'errors' ) as $counter ) {
			$job[ $counter ] = max( 0, Qfibr_Cast::int( $value[ $counter ] ?? 0 ) );
		}
		$job['done']    = min( $job['done'], $job['total'] );
		$job['changed'] = Qfibr_Query::ids( $value['changed'] ?? array() );

		return $job;
	}

	/**
	 * Process the next slice of a job.
	 *
	 * @param Qfibr_Job $job  Job data.
	 * @param int       $size How many posts to handle.
	 * @return Qfibr_Job The updated job.
	 */
	public static function process_slice( array $job, $size ) {
		$slice = array_slice( $job['ids'], $job['done'], max( 1, (int) $size ) );

		$ok_key = self::TYPES[ $job['type'] ] ?? 'replaced';

		foreach ( $slice as $pid ) {
			$status = self::process_one( $job, $pid );
			$job    = self::tally( $job, $status, $ok_key );
			if ( $ok_key === $status ) {
				// Only posts this run actually changed: a per-run restore must
				// not revert posts it merely skipped.
				$job['changed'][] = $pid;
			}
			++$job['done'];
		}

		return $job;
	}

	/**
	 * Handle a single post of a job.
	 *
	 * @param Qfibr_Job $job Job data.
	 * @param int       $pid Post ID.
	 * @return string Status.
	 */
	public static function process_one( array $job, $pid ) {
		switch ( $job['type'] ) {
			case 'restore':
				return Qfibr_Replacer::restore_one( $pid );
			case 'cleanup':
				return Qfibr_Orphans::clean_one( $pid );
			default:
				return Qfibr_Replacer::replace_one( $pid, self::attachment_for( $job, $pid ) );
		}
	}

	/**
	 * The attachment a replace job sets on a post.
	 *
	 * @param Qfibr_Job $job Job data.
	 * @param int       $pid Post ID.
	 * @return int
	 */
	public static function attachment_for( array $job, $pid ) {
		if ( count( $job['attachment_ids'] ) >= 2 ) {
			return Qfibr_Image_Picker::pick( $job['attachment_ids'], $job['seed'], $pid );
		}
		return $job['attachment_id'];
	}

	/**
	 * Map a per-post status onto the job counters.
	 *
	 * @param Qfibr_Job $job    Job data.
	 * @param string    $status Status returned by the worker.
	 * @param string    $ok_key Status counting as success.
	 * @return Qfibr_Job
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
	 * @param Qfibr_Job $job Job data.
	 * @return bool
	 */
	public static function is_finished( array $job ) {
		return $job['done'] >= $job['total'];
	}
}
