<?php
/**
 * The write path: replacing and restoring a single post's featured image.
 *
 * Every replacement stores the post's original thumbnail ID once, so a restore
 * can always put things back — including "there was no image", recorded as 0.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replaces and restores featured images, one post at a time.
 */
final class Qfibr_Replacer {

	/** Meta key storing the original featured image ID for restore. */
	const META_BACKUP_KEY = '_qfibr_original_thumbnail_id';

	/**
	 * Replace one post's featured image, backing up the original once.
	 *
	 * @param int $post_id       Post to change.
	 * @param int $attachment_id Attachment to set.
	 * @return string 'replaced' | 'skipped' | 'error'
	 */
	public static function replace_one( $post_id, $attachment_id ) {
		$post_id       = (int) $post_id;
		$attachment_id = (int) $attachment_id;

		// Re-check at the moment of writing: the job may have been queued long
		// before this tick, and the target may have gone away since.
		if ( ! Qfibr_Attachment::exists( $attachment_id ) ) {
			return 'error';
		}
		if ( ! get_post( $post_id ) ) {
			return 'error';
		}

		$current_thumb_id = (int) get_post_thumbnail_id( $post_id );

		if ( $current_thumb_id === $attachment_id ) {
			return 'skipped';
		}
		if ( ! metadata_exists( 'post', $post_id, self::META_BACKUP_KEY ) ) {
			update_post_meta( $post_id, self::META_BACKUP_KEY, $current_thumb_id );
		}
		return set_post_thumbnail( $post_id, $attachment_id ) ? 'replaced' : 'error';
	}

	/**
	 * Restore one post's original featured image.
	 *
	 * @param int $post_id Post to restore.
	 * @return string 'restored' | 'skipped' | 'error'
	 */
	public static function restore_one( $post_id ) {
		$post_id     = (int) $post_id;
		$original_id = get_post_meta( $post_id, self::META_BACKUP_KEY, true );

		if ( '' === $original_id ) {
			return 'skipped';
		}

		$original_id = (int) $original_id;
		$ok          = true;

		if ( $original_id > 0 ) {
			if ( get_post( $original_id ) ) {
				$ok = (bool) set_post_thumbnail( $post_id, $original_id );
			} else {
				delete_post_thumbnail( $post_id );
			}
		} else {
			delete_post_thumbnail( $post_id );
		}

		if ( $ok ) {
			delete_post_meta( $post_id, self::META_BACKUP_KEY );
			return 'restored';
		}
		return 'error';
	}
}
