<?php
/**
 * Read-only preview of what a run would do.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the preview table and summary.
 *
 * @phpstan-import-type Qfibr_Criteria_Shape from Qfibr_Criteria
 */
final class Qfibr_Preview {

	/** Max rows rendered in the preview table (the count reflects the full match). */
	const PREVIEW_ROWS = 100;

	/** Posts per meta-cache priming query. */
	const CACHE_CHUNK = 200;

	/**
	 * Build the preview markup for a criteria set.
	 *
	 * @param Qfibr_Criteria_Shape $c             Normalised criteria.
	 * @param int                  $attachment_id Resolved attachment ID (single mode).
	 * @param list<int>            $pool          Random pool (empty in single mode).
	 * @return string Markup.
	 */
	public static function build( array $c, $attachment_id, array $pool = array() ) {
		$attachment_id = (int) $attachment_id;
		$post_ids      = Qfibr_Query::target_ids( $c );
		$total         = count( $post_ids );
		$sample        = array_slice( $post_ids, 0, self::PREVIEW_ROWS );
		$random        = count( $pool ) >= 2;

		// The enumeration query deliberately skips the meta cache (it only asks
		// for IDs), so prime it in chunks. Without this the counting loop below
		// fires one query per matched post.
		self::prime_thumbnail_cache( $post_ids );

		$would_replace = 0;
		$would_skip    = 0;
		foreach ( $post_ids as $pid ) {
			if ( ! $random && (int) get_post_thumbnail_id( $pid ) === $attachment_id ) {
				++$would_skip;
			} else {
				++$would_replace;
			}
		}

		$rows = '';
		foreach ( $sample as $pid ) {
			$rows .= self::row( $pid, $attachment_id, $random );
		}

		if ( $random ) {
			$summary = sprintf(
				/* translators: 1: number of images in the pool, 2: total matched posts. */
				__( 'Random assignment from %1$d images. Matched posts: %2$d.', 'qaiyo-featured-image-bulk-replacer' ),
				count( $pool ),
				$total
			);
		} else {
			$summary = sprintf(
				/* translators: 1: attachment ID, 2: total matched, 3: to replace, 4: to skip. */
				__( 'Media Library attachment ID: %1$d. Matched posts: %2$d — to replace: %3$d, to skip: %4$d.', 'qaiyo-featured-image-bulk-replacer' ),
				$attachment_id,
				$total,
				$would_replace,
				$would_skip
			);
		}

		if ( '' === $rows ) {
			return '<p class="qfibr-summary" role="status">' . esc_html( $summary ) . '</p>'
				. '<p>' . esc_html__( 'No posts match these criteria.', 'qaiyo-featured-image-bulk-replacer' ) . '</p>';
		}

		$note = '';
		if ( $total > self::PREVIEW_ROWS ) {
			$note = '<p class="qfibr-hint">' . esc_html( sprintf(
				/* translators: 1: rows shown, 2: total matched. */
				__( 'Showing the first %1$d of %2$d matching posts.', 'qaiyo-featured-image-bulk-replacer' ),
				self::PREVIEW_ROWS,
				$total
			) ) . '</p>';
		}

		return '<p class="qfibr-summary" role="status">' . esc_html( $summary ) . '</p>' . $note
			. '<table class="qfibr-preview-table">'
			. '<caption class="screen-reader-text">' . esc_html__( 'Posts affected by this run', 'qaiyo-featured-image-bulk-replacer' ) . '</caption>'
			. '<thead><tr>'
			. '<th scope="col">' . esc_html__( 'ID', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Image', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Title', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Date', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Action', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
			. '</tr></thead><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * One preview row.
	 *
	 * @param int  $pid           Post ID.
	 * @param int  $attachment_id Attachment that would be set (single mode).
	 * @param bool $random        Whether the run picks from a pool.
	 * @return string
	 */
	private static function row( $pid, $attachment_id, $random ) {
		$current = (int) get_post_thumbnail_id( $pid );

		if ( $random ) {
			$action_label = __( 'replace (random image)', 'qaiyo-featured-image-bulk-replacer' );
			$action_class = 'qfibr-replace';
		} elseif ( $current === $attachment_id ) {
			$action_label = __( 'skip (already correct)', 'qaiyo-featured-image-bulk-replacer' );
			$action_class = 'qfibr-skip';
		} else {
			$action_label = __( 'replace', 'qaiyo-featured-image-bulk-replacer' );
			$action_class = 'qfibr-replace';
		}

		$title = get_the_title( $pid );

		$thumb = $current
			? wp_get_attachment_image( $current, array( 44, 44 ), true, array( 'alt' => '' ) )
			: '<span class="qfibr-nothumb" aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No featured image', 'qaiyo-featured-image-bulk-replacer' ) . '</span>';

		return sprintf(
			'<tr><td>%1$d</td><td class="qfibr-thumb-cell">%2$s</td><td>%3$s</td><td>%4$s</td><td class="%5$s">%6$s</td></tr>',
			$pid,
			$thumb, // wp_get_attachment_image() returns escaped, attribute-safe HTML.
			esc_html( $title ),
			esc_html( (string) get_the_date( 'Y-m-d', $pid ) ),
			esc_attr( $action_class ),
			esc_html( $action_label )
		);
	}

	/**
	 * Warm the post-meta cache so thumbnail lookups do not hit the database
	 * once per post.
	 *
	 * @param list<int> $post_ids Post IDs.
	 * @return void
	 */
	private static function prime_thumbnail_cache( array $post_ids ) {
		foreach ( array_chunk( $post_ids, self::CACHE_CHUNK ) as $chunk ) {
			update_meta_cache( 'post', $chunk );
		}
	}
}
