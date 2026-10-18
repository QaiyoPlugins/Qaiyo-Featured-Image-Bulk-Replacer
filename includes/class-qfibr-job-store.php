<?php
/**
 * Foreground job storage.
 *
 * A browser-driven run is split into small AJAX batches, so the queue of post
 * IDs and the running counters have to survive between requests. They live in a
 * short-lived transient keyed by a random token, plus a small option holding the
 * summary of the last completed run.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores in-progress jobs and the last-run summary.
 */
final class Qfibr_Job_Store {

	/** Transient key prefix for an in-progress job's queued IDs + counters. */
	const JOB_PREFIX = 'qfibr_job_';

	/** Option holding a summary of the last completed run. */
	const LAST_RUN_OPTION = 'qfibr_last_run';

	/** How long an abandoned job stays around before it expires. */
	const TTL = HOUR_IN_SECONDS;

	/**
	 * Persist a new job and return its token.
	 *
	 * @param array<string, mixed> $job Job data.
	 * @return string Token.
	 */
	public static function create( array $job ) {
		$token = wp_generate_password( 20, false );
		set_transient( self::JOB_PREFIX . $token, $job, self::TTL );
		return $token;
	}

	/**
	 * Load a job by token.
	 *
	 * @param string $token Job token.
	 * @return array<string, mixed>|null
	 */
	public static function get( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}
		$job = get_transient( self::JOB_PREFIX . $token );
		return is_array( $job ) ? $job : null;
	}

	/**
	 * Save an updated job.
	 *
	 * @param string               $token Job token.
	 * @param array<string, mixed> $job   Job data.
	 * @return void
	 */
	public static function save( $token, array $job ) {
		set_transient( self::JOB_PREFIX . $token, $job, self::TTL );
	}

	/**
	 * Drop a finished job.
	 *
	 * @param string $token Job token.
	 * @return void
	 */
	public static function delete( $token ) {
		delete_transient( self::JOB_PREFIX . $token );
	}

	/**
	 * Record the summary of a completed run.
	 *
	 * @param array<string, mixed> $job Completed job.
	 * @return void
	 */
	public static function store_last_run( array $job ) {
		update_option( self::LAST_RUN_OPTION, array(
			'time'      => time(),
			'type'      => $job['type'],
			'mode'      => isset( $job['mode'] ) ? $job['mode'] : '',
			'total'     => (int) $job['total'],
			'processed' => (int) $job['processed'],
			'skipped'   => (int) $job['skipped'],
			'errors'    => (int) $job['errors'],
		), false );
	}

	/**
	 * The last completed run, if any.
	 *
	 * @return array<string, mixed>
	 */
	public static function last_run() {
		$run = get_option( self::LAST_RUN_OPTION, array() );
		return is_array( $run ) ? $run : array();
	}
}
