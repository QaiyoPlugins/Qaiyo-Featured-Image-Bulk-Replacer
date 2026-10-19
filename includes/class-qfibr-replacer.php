<?php
/**
 * The write path: replacing, removing and restoring a single post's featured
 * image.
 *
 * Every change stores the post's original thumbnail ID once, so a restore can
 * always put things back — including "there was no image", recorded as 0.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replaces, removes and restores featured images, one post at a time.
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
		// before this batch, and the target may have gone away since.
		if ( ! Qfibr_Attachment::exists( $attachment_id ) ) {
			return 'error';
		}
		if ( ! get_post( $post_id ) ) {
			return 'error';
		}

		$current = (int) get_post_thumbnail_id( $post_id );

		if ( $current === $attachment_id ) {
			return 'skipped';
		}

		self::backup( $post_id, $current );

		if ( ! set_post_thumbnail( $post_id, $attachment_id ) ) {
			return 'error';
		}

		self::changed( $post_id, $attachment_id, $current );
		return 'replaced';
	}

	/**
	 * Remove one post's featured image, backing up the original once.
	 *
	 * @param int $post_id Post to change.
	 * @return string 'removed' | 'skipped' | 'error'
	 */
	public static function remove_one( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! get_post( $post_id ) ) {
			return 'error';
		}

		$current = Qfibr_Cast::int( get_post_meta( $post_id, '_thumbnail_id', true ) );
		if ( 0 === $current ) {
			return 'skipped';
		}

		self::backup( $post_id, $current );

		if ( ! delete_post_thumbnail( $post_id ) ) {
			return 'error';
		}

		self::changed( $post_id, 0, $current );
		return 'removed';
	}

	/**
	 * Restore one post's original featured image.
	 *
	 * @param int $post_id Post to restore.
	 * @return string 'restored' | 'skipped' | 'error'
	 */
	public static function restore_one( $post_id ) {
		$post_id = (int) $post_id;
		$stored  = get_post_meta( $post_id, self::META_BACKUP_KEY, true );

		if ( '' === $stored || ! is_scalar( $stored ) ) {
			return 'skipped';
		}

		$original_id = Qfibr_Cast::int( $stored );
		$current     = Qfibr_Cast::int( get_post_meta( $post_id, '_thumbnail_id', true ) );
		$ok          = true;

		if ( $original_id > 0 && get_post( $original_id ) ) {
			$ok = (bool) set_post_thumbnail( $post_id, $original_id );
		} else {
			delete_post_thumbnail( $post_id );
			$original_id = 0;
		}

		if ( ! $ok ) {
			return 'error';
		}

		delete_post_meta( $post_id, self::META_BACKUP_KEY );
		self::changed( $post_id, $original_id, $current );
		return 'restored';
	}

	/**
	 * Store the original once; a later change must never overwrite it, or the
	 * true original is lost.
	 *
	 * @param int $post_id  Post ID.
	 * @param int $original Current thumbnail ID (0 = none).
	 * @return void
	 */
	private static function backup( $post_id, $original ) {
		if ( ! metadata_exists( 'post', $post_id, self::META_BACKUP_KEY ) ) {
			update_post_meta( $post_id, self::META_BACKUP_KEY, $original );
		}
	}

	/**
	 * Announce a successful change.
	 *
	 * @param int $post_id Post ID.
	 * @param int $new_id  New attachment ID (0 = none).
	 * @param int $old_id  Previous attachment ID (0 = none).
	 * @return void
	 */
	private static function changed( $post_id, $new_id, $old_id ) {
		/**
		 * Fires after this plugin changed a post's featured image (replace,
		 * remove or restore). Add-ons use it to clear caches, e.g. WooCommerce
		 * product caches.
		 *
		 * @param int $post_id Post ID.
		 * @param int $new_id  New attachment ID, 0 when removed.
		 * @param int $old_id  Previous attachment ID, 0 when there was none.
		 */
		do_action( 'qfibr_featured_image_changed', $post_id, $new_id, $old_id );
	}
}
