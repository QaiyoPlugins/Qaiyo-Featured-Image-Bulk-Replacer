<?php
/**
 * AJAX endpoints for the browser-driven run.
 *
 * Every endpoint verifies the nonce and the capability before it reads a single
 * request value, and a replacement is refused unless the criteria are bounded by
 * a valid "before" date — an unbounded run would rewrite every published post.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and serves the plugin's AJAX endpoints.
 */
final class Qfibr_Ajax {

	const NONCE      = 'qfibr_ajax';
	const CAPABILITY = 'manage_options';

	/** How many posts the test run touches. */
	const TEST_LIMIT = 3;

	/** Posts processed per AJAX batch — keeps each request light, avoids timeouts. */
	const BATCH_SIZE = 25;

	/**
	 * Bind the endpoints.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_ajax_qfibr_preview', array( __CLASS__, 'preview' ) );
		add_action( 'wp_ajax_qfibr_prepare', array( __CLASS__, 'prepare' ) );
		add_action( 'wp_ajax_qfibr_batch', array( __CLASS__, 'batch' ) );
	}

	/**
	 * Verify nonce + capability for every endpoint.
	 *
	 * @return void
	 */
	private static function verify() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'qaiyo-featured-image-bulk-replacer' ) ), 403 );
		}
	}

	/**
	 * Collect the criteria from the verified request.
	 *
	 * The nonce-verification sniff cannot see check_ajax_referer() across the
	 * helper boundary, hence the scoped disable.
	 *
	 * @return array<string, mixed>
	 */
	private static function read_criteria() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by self::verify() in the calling endpoint.
		$raw = array(
			'attachment_id' => isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0,
			'image_url'     => isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '',
			'before_date'   => isset( $_POST['before_date'] ) ? sanitize_text_field( wp_unslash( $_POST['before_date'] ) ) : '',
			'after_date'    => isset( $_POST['after_date'] ) ? sanitize_text_field( wp_unslash( $_POST['after_date'] ) ) : '',
			'post_type'     => isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post',
			'scope'         => isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'all',
			'category'      => isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0,
			'alt_text'      => isset( $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return Qfibr_Criteria::from_array( $raw );
	}

	/**
	 * Read-only preview of the matching posts.
	 *
	 * @return void
	 */
	public static function preview() {
		self::verify();
		$c = self::read_criteria();

		if ( ! Qfibr_Criteria::is_bounded( $c ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid “before” date.', 'qaiyo-featured-image-bulk-replacer' ) ) );
		}

		$attachment_id = Qfibr_Attachment::resolve( $c['attachment_id'], $c['image_url'] );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		wp_send_json_success( array( 'html' => Qfibr_Preview::build( $c, $attachment_id ) ) );
	}

	/**
	 * Build the job queue and return a token.
	 *
	 * @return void
	 */
	public static function prepare() {
		self::verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by self::verify() above.
		$job_raw  = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( $_POST['job'] ) ) : '';
		$job_type = ( 'restore' === $job_raw ) ? 'restore' : 'replace';

		$job = ( 'restore' === $job_type ) ? self::prepare_restore() : self::prepare_replace();

		if ( 0 === $job['total'] ) {
			wp_send_json_success( array( 'token' => '', 'total' => 0 ) );
		}

		wp_send_json_success( array(
			'token' => Qfibr_Job_Store::create( $job ),
			'total' => $job['total'],
		) );
	}

	/**
	 * Queue every restorable post.
	 *
	 * @return array<string, mixed>
	 */
	private static function prepare_restore() {
		return Qfibr_Runner::make_job( 'restore', Qfibr_Query::restorable_ids() );
	}

	/**
	 * Queue the posts a replacement would touch.
	 *
	 * @return array<string, mixed>
	 */
	private static function prepare_replace() {
		$c = self::read_criteria();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by self::verify() in prepare().
		$mode_raw = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$mode     = ( 'test' === $mode_raw ) ? 'test' : 'live';

		if ( ! Qfibr_Criteria::is_bounded( $c ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid “before” date.', 'qaiyo-featured-image-bulk-replacer' ) ) );
		}

		$attachment_id = Qfibr_Attachment::resolve( $c['attachment_id'], $c['image_url'] );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		// Optionally set a fixed alt/title on the chosen attachment, once.
		Qfibr_Attachment::apply_alt_text( $attachment_id, $c['alt_text'] );

		$limit = ( 'test' === $mode ) ? self::TEST_LIMIT : null;

		return Qfibr_Runner::make_job(
			'replace',
			Qfibr_Query::target_ids( $c, $limit ),
			array(
				'mode'          => $mode,
				'attachment_id' => (int) $attachment_id,
			)
		);
	}

	/**
	 * Process the next slice of a queued job.
	 *
	 * @return void
	 */
	public static function batch() {
		self::verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by self::verify() above.
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$job   = Qfibr_Job_Store::get( $token );

		if ( null === $job ) {
			wp_send_json_error( array( 'message' => __( 'The job expired. Please start again.', 'qaiyo-featured-image-bulk-replacer' ) ) );
		}

		$job      = Qfibr_Runner::process_slice( $job, self::BATCH_SIZE );
		$finished = Qfibr_Runner::is_finished( $job );

		if ( $finished ) {
			Qfibr_Job_Store::store_last_run( $job );
			Qfibr_Job_Store::delete( $token );
			/**
			 * Fires when a foreground run/restore completes. Pro uses this to log history.
			 *
			 * @param array $job Completed job (type, mode, ids, counters).
			 */
			do_action( 'qfibr_run_complete', $job );
		} else {
			Qfibr_Job_Store::save( $token, $job );
		}

		wp_send_json_success( array(
			'done'      => (int) $job['done'],
			'total'     => (int) $job['total'],
			'processed' => (int) $job['processed'],
			'skipped'   => (int) $job['skipped'],
			'errors'    => (int) $job['errors'],
			'finished'  => $finished,
		) );
	}
}
