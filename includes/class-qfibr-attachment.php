<?php
/**
 * Media Library attachment resolution.
 *
 * Nothing is ever downloaded: a run can only point at an image that is already
 * in this site's Media Library, resolved from an attachment ID or a local URL.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves an attachment ID from user input.
 */
final class Qfibr_Attachment {

	/**
	 * Resolve an existing attachment from an ID or URL.
	 *
	 * @param int|string $attachment_id Candidate attachment ID.
	 * @param string     $image_url     Candidate image URL.
	 * @return int|WP_Error Attachment ID, or an error explaining what to fix.
	 */
	public static function resolve( $attachment_id, $image_url ) {
		$attachment_id = (int) $attachment_id;
		if ( self::exists( $attachment_id ) ) {
			return $attachment_id;
		}

		if ( '' === $image_url ) {
			return new WP_Error( 'qfibr_no_image', __( 'Please choose an image from the Media Library or paste an image URL.', 'qaiyo-featured-image-bulk-replacer' ) );
		}

		$found = attachment_url_to_postid( $image_url );
		if ( self::exists( $found ) ) {
			return $found;
		}

		// Fallback: a sized URL (…-150x150.jpg) → try the base file name.
		$stripped = preg_replace( '/-\d+x\d+(\.[a-zA-Z]+)$/', '$1', $image_url );
		if ( $stripped !== $image_url ) {
			$found = attachment_url_to_postid( (string) $stripped );
			if ( self::exists( $found ) ) {
				return $found;
			}
		}

		return new WP_Error( 'qfibr_not_found', __( 'No Media Library image was found for that URL. Make sure the image is already uploaded.', 'qaiyo-featured-image-bulk-replacer' ) );
	}

	/**
	 * Whether an ID still points at a real attachment right now.
	 *
	 * Checked again at write time, not only when the job was queued: a
	 * background run can start hours after the image was picked, and the
	 * attachment may have been deleted in the meantime.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function exists( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		return $attachment_id > 0
			&& 'attachment' === get_post_type( $attachment_id )
			&& wp_attachment_is_image( $attachment_id );
	}

	/**
	 * Keep only the IDs that are real image attachments right now.
	 *
	 * @param list<int> $ids Candidate IDs.
	 * @return list<int>
	 */
	public static function existing( array $ids ) {
		return array_values( array_filter( $ids, array( __CLASS__, 'exists' ) ) );
	}

	/**
	 * Apply a one-off alt/title text to the chosen attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $alt_text      Replacement alt/title text.
	 * @return void
	 */
	public static function apply_alt_text( $attachment_id, $alt_text ) {
		if ( '' === $alt_text || ! self::exists( $attachment_id ) ) {
			return;
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		wp_update_post( array(
			'ID'         => (int) $attachment_id,
			'post_title' => $alt_text,
		) );
	}
}
