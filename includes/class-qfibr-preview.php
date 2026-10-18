<?php
/**
 * Read-only preview of what a run would do.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the preview table and summary.
 */
final class Qfibr_Preview {

	/** Max rows rendered in the preview table (the count reflects the full match). */
	const PREVIEW_ROWS = 100;

	/** Posts per meta-cache priming query. */
	const CACHE_CHUNK = 200;

	/**
	 * Build the preview markup for a criteria set.
	 *
	 * @param array<string, mixed> $c             Normalised criteria.
	 * @param int                  $attachment_id Resolved attachment ID.
	 * @return string Markup.
	 */
	public static function build( array $c, $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$post_ids      = Qfibr_Query::target_ids( $c );
		$total         = count( $post_ids );
		$sample        = array_slice( $post_ids, 0, self::PREVIEW_ROWS );

		// The enumeration query deliberately skips the meta cache (it only asks
		// for IDs), so prime it in chunks here. Without this the counting loop
		// below fires one query per matched post.
		self::prime_thumbnail_cache( $post_ids );

		$would_replace = 0;
		$would_skip    = 0;
		foreach ( $post_ids as $pid ) {
			if ( (int) get_post_thumbnail_id( $pid ) === $attachment_id ) {
				++$would_skip;
			} else {
				++$would_replace;
			}
		}

		$rows = '';
		foreach ( $sample as $pid ) {
			$rows .= self::row( (int) $pid, $attachment_id );
		}

		$summary = sprintf(
			/* translators: 1: attachment ID, 2: total matched, 3: to replace, 4: to skip. */
			__( 'Media Library attachment ID: %1$d. Matched posts: %2$d — to replace: %3$d, to skip: %4$d.', 'qaiyo-featured-image-bulk-replacer' ),
			$attachment_id,
			$total,
			$would_replace,
			$would_skip
		);

		if ( '' === $rows ) {
			return '<p class="qfibr-summary">' . esc_html( $summary ) . '</p>'
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

		return '<p class="qfibr-summary">' . esc_html( $summary ) . '</p>' . $note
			. '<table class="qfibr-preview-table"><thead><tr>'
			. '<th>' . esc_html__( 'ID', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
			. '<th>' . esc_html__( 'Image', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
			. '<th>' . esc_html__( 'Title', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
			. '<th>' . esc_html__( 'Date', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
			. '<th>' . esc_html__( 'Action', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
			. '</tr></thead><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * One preview row.
	 *
	 * @param int $pid           Post ID.
	 * @param int $attachment_id Attachment that would be set.
	 * @return string
	 */
	private static function row( $pid, $attachment_id ) {
		$current_thumb_id = (int) get_post_thumbnail_id( $pid );

		if ( $current_thumb_id === $attachment_id ) {
			$action_label = __( 'skip (already correct)', 'qaiyo-featured-image-bulk-replacer' );
			$action_class = 'qfibr-skip';
		} else {
			$action_label = __( 'replace', 'qaiyo-featured-image-bulk-replacer' );
			$action_class = 'qfibr-replace';
		}

		$thumb = $current_thumb_id
			? wp_get_attachment_image( $current_thumb_id, array( 44, 44 ), true )
			: '<span class="qfibr-nothumb">—</span>';

		return sprintf(
			'<tr><td>%1$d</td><td class="qfibr-thumb-cell">%2$s</td><td>%3$s</td><td>%4$s</td><td class="%5$s">%6$s</td></tr>',
			$pid,
			$thumb, // wp_get_attachment_image() returns escaped, attribute-safe HTML.
			esc_html( get_the_title( $pid ) ),
			esc_html( get_the_date( 'Y-m-d', $pid ) ),
			esc_attr( $action_class ),
			esc_html( $action_label )
		);
	}

	/**
	 * Warm the post-meta cache so thumbnail lookups do not hit the database
	 * once per post.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return void
	 */
	private static function prime_thumbnail_cache( array $post_ids ) {
		foreach ( array_chunk( $post_ids, self::CACHE_CHUNK ) as $chunk ) {
			update_meta_cache( 'post', $chunk );
		}
	}
}
