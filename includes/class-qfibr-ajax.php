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
 *
 * @phpstan-import-type Qfibr_Criteria_Shape from Qfibr_Criteria
 * @phpstan-import-type Qfibr_Job from Qfibr_Runner
 */
final class Qfibr_Ajax {

	const NONCE      = 'qfibr_ajax';
	const CAPABILITY = 'manage_options';

	/** How many posts the test run touches. */
	const TEST_LIMIT = 3;

	/** Posts processed per AJAX batch — keeps each request light, avoids timeouts. */
	const BATCH_SIZE = 25;

	/** Request keys that make up the criteria. */
	const CRITERIA_KEYS = array(
		'attachment_id',
		'attachment_ids',
		'assign_mode',
		'image_url',
		'before_date',
		'after_date',
		'post_type',
		'scope',
		'category',
		'tag',
		'author',
		'tax_term',
		'alt_text',
	);

	/**
	 * Bind the endpoints.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_ajax_qfibr_preview', array( __CLASS__, 'preview' ) );
		add_action( 'wp_ajax_qfibr_prepare', array( __CLASS__, 'prepare' ) );
		add_action( 'wp_ajax_qfibr_batch', array( __CLASS__, 'batch' ) );
		add_action( 'wp_ajax_qfibr_orphan_scan', array( __CLASS__, 'orphan_scan' ) );
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
	 * One sanitised string from the verified request.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private static function post_string( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce and capability verified by every caller (free and Pro endpoints) before this runs.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Collect the criteria from the request.
	 *
	 * Public so the Pro queue reads the form exactly like the free run does.
	 * Callers MUST have verified a nonce and the capability first.
	 *
	 * @return Qfibr_Criteria_Shape
	 */
	public static function criteria_from_request() {
		$raw = array();
		foreach ( self::CRITERIA_KEYS as $key ) {
			$raw[ $key ] = self::post_string( $key );
		}
		$raw['image_url'] = esc_url_raw( $raw['image_url'] );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability verified by every caller (free and Pro endpoints) before this runs.
		$raw['extra'] = isset( $_POST['extra'] ) && is_array( $_POST['extra'] )
			? map_deep( wp_unslash( $_POST['extra'] ), 'sanitize_text_field' )
			: array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return Qfibr_Criteria::filtered( Qfibr_Criteria::from_array( $raw ) );
	}

	/**
	 * Resolve the image(s) a replacement will use, or send an error.
	 *
	 * @param Qfibr_Criteria_Shape $c Criteria.
	 * @return array{attachment_id: int, pool: list<int>}
	 */
	public static function resolve_images( array $c ) {
		if ( 'random' === $c['assign_mode'] ) {
			$pool = Qfibr_Attachment::existing( $c['attachment_ids'] );
			if ( count( $pool ) >= 2 ) {
				return array(
					'attachment_id' => $pool[0],
					'pool'          => $pool,
				);
			}
			wp_send_json_error( array( 'message' => __( 'Choose at least two images for random assignment.', 'qaiyo-featured-image-bulk-replacer' ) ) );
		}

		$attachment_id = Qfibr_Attachment::resolve( $c['attachment_id'], $c['image_url'] );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		return array(
			'attachment_id' => (int) $attachment_id,
			'pool'          => array(),
		);
	}

	/**
	 * Refuse an unbounded replacement.
	 *
	 * @param Qfibr_Criteria_Shape $c Criteria.
	 * @return void
	 */
	public static function require_bounded( array $c ) {
		if ( ! Qfibr_Criteria::is_bounded( $c ) ) {
			wp_send_json_error( array(
				'message' => __( 'Please enter a valid “before” date.', 'qaiyo-featured-image-bulk-replacer' ),
				'field'   => 'qfibr_before_date',
			) );
		}
		if ( array() !== Qfibr_Query::inapplicable_filters( $c ) ) {
			wp_send_json_error( array(
				'message' => __( 'A category, tag or taxonomy filter is set that does not apply to the chosen post type. Clear it or pick another post type — otherwise the run would affect more posts than intended.', 'qaiyo-featured-image-bulk-replacer' ),
				'field'   => 'qfibr_post_type',
			) );
		}
	}

	/**
	 * Read-only preview of the matching posts.
	 *
	 * @return void
	 */
	public static function preview() {
		self::verify();
		$c = self::criteria_from_request();
		self::require_bounded( $c );

		$images = self::resolve_images( $c );

		wp_send_json_success( array( 'html' => Qfibr_Preview::build( $c, $images['attachment_id'], $images['pool'] ) ) );
	}

	/**
	 * Build the job queue and return a token.
	 *
	 * @return void
	 */
	public static function prepare() {
		self::verify();

		$type = self::post_string( 'job' );

		switch ( $type ) {
			case 'restore':
				$job = Qfibr_Runner::make_job( 'restore', Qfibr_Query::restorable_ids() );
				break;
			case 'cleanup':
				$job = Qfibr_Runner::make_job( 'cleanup', Qfibr_Orphans::find() );
				break;
			default:
				$job = self::prepare_replace();
		}

		if ( 0 === $job['total'] ) {
			wp_send_json_success( array( 'token' => '', 'total' => 0 ) );
		}

		wp_send_json_success( array(
			'token' => Qfibr_Job_Store::create( $job ),
			'total' => $job['total'],
		) );
	}

	/**
	 * Queue the posts a replacement would touch.
	 *
	 * @return Qfibr_Job
	 */
	private static function prepare_replace() {
		$c    = self::criteria_from_request();
		$mode = ( 'test' === self::post_string( 'mode' ) ) ? 'test' : 'live';

		self::require_bounded( $c );
		$images = self::resolve_images( $c );

		// Optionally set a fixed alt/title on the chosen attachment, once. Only
		// meaningful for a single image: a pool would get the same text on
		// every image.
		if ( array() === $images['pool'] ) {
			Qfibr_Attachment::apply_alt_text( $images['attachment_id'], $c['alt_text'] );
		}

		$limit = ( 'test' === $mode ) ? self::TEST_LIMIT : null;

		return Qfibr_Runner::make_job(
			'replace',
			Qfibr_Query::target_ids( $c, $limit ),
			array(
				'mode'           => $mode,
				'attachment_id'  => $images['attachment_id'],
				'attachment_ids' => $images['pool'],
				'seed'           => Qfibr_Image_Picker::new_seed(),
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

		$token = self::post_string( 'token' );
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
			 * Fires when a foreground run/restore/cleanup completes. Pro uses
			 * this to log history.
			 *
			 * @param array $job Completed job (type, mode, ids, counters).
			 */
			do_action( 'qfibr_run_complete', $job );
		} else {
			Qfibr_Job_Store::save( $token, $job );
		}

		wp_send_json_success( array(
			'done'      => $job['done'],
			'total'     => $job['total'],
			'processed' => $job['processed'],
			'skipped'   => $job['skipped'],
			'errors'    => $job['errors'],
			'finished'  => $finished,
		) );
	}

	/**
	 * Count the dangling featured-image references.
	 *
	 * @return void
	 */
	public static function orphan_scan() {
		self::verify();
		wp_send_json_success( array( 'html' => Qfibr_Admin_Page::orphan_result_html( Qfibr_Orphans::summary() ) ) );
	}
}
