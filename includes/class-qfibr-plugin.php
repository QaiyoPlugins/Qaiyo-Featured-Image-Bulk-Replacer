<?php
/**
 * Plugin facade and the public API the Pro add-on builds on.
 *
 * The moving parts live in focused classes; this stays as the stable surface
 * other code (in particular Qaiyo Featured Image Bulk Replacer Pro) calls by
 * name. Changing a signature here breaks the Free↔Pro contract.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Boots the plugin and exposes its public API.
 */
final class Qfibr_Plugin {

	const MENU_SLUG  = Qfibr_Admin_Page::MENU_SLUG;
	const AJAX_NONCE = Qfibr_Ajax::NONCE;
	const CAPABILITY = Qfibr_Ajax::CAPABILITY;

	/** How many posts the test run touches. */
	const TEST_LIMIT = Qfibr_Ajax::TEST_LIMIT;

	/** Posts processed per AJAX batch. */
	const BATCH_SIZE = Qfibr_Ajax::BATCH_SIZE;

	/** Max rows rendered in the preview table. */
	const PREVIEW_ROWS = Qfibr_Preview::PREVIEW_ROWS;

	/** Meta key storing the original featured image ID for restore. */
	const META_BACKUP_KEY = Qfibr_Replacer::META_BACKUP_KEY;

	/** Option holding a summary of the last completed run. */
	const LAST_RUN_OPTION = Qfibr_Job_Store::LAST_RUN_OPTION;

	/** Transient key prefix for an in-progress job. */
	const JOB_PREFIX = Qfibr_Job_Store::JOB_PREFIX;

	/**
	 * Singleton instance.
	 *
	 * @var Qfibr_Plugin|null
	 */
	private static $instance = null;

	/**
	 * The singleton, created on first use.
	 *
	 * @return Qfibr_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * No side effects in the constructor — hooks are bound by register().
	 */
	private function __construct() {}

	/**
	 * Bind every hook this plugin owns.
	 *
	 * @return void
	 */
	public static function register() {
		Qfibr_Admin_Page::register();
		Qfibr_Admin_Assets::register();
		Qfibr_Ajax::register();
		Qfibr_List_Column::register();
		Qfibr_Usage::register();
	}

	/**
	 * Render the admin screen.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		Qfibr_Admin_Page::render();
	}

	// =========================================================================
	// PUBLIC API — consumed by Qaiyo Featured Image Bulk Replacer Pro
	// =========================================================================

	/**
	 * True when an active Pro licence is present (Pro answers this filter).
	 *
	 * @return bool
	 */
	public static function pro_active() {
		return (bool) apply_filters( 'qfibr_pro_active', false );
	}

	/**
	 * Upgrade / product URL (Pro can override).
	 *
	 * @return string
	 */
	public static function upgrade_url() {
		$default = 'https://qaiyo-plugins.com/qaiyo-featured-image-bulk-replacer-pro/';
		$url     = Qfibr_Cast::string( apply_filters( 'qfibr_upgrade_url', $default ) );
		return '' !== $url ? $url : $default;
	}

	/**
	 * Resolve a Media Library attachment from an ID or URL.
	 *
	 * @param int|string $attachment_id Candidate attachment ID.
	 * @param string     $image_url     Candidate image URL.
	 * @return int|WP_Error
	 */
	public static function api_resolve_attachment( $attachment_id, $image_url ) {
		return Qfibr_Attachment::resolve( $attachment_id, $image_url );
	}

	/**
	 * Enumerate target post IDs for a criteria array.
	 *
	 * A replacement must be bounded by a valid "before" date; an unbounded
	 * criteria set returns an empty list rather than every published post.
	 *
	 * @param array<array-key, mixed> $criteria Keys: before_date/after_date/post_type/scope/category/tag/author/taxonomy/term.
	 * @param int|null                $limit    Optional cap.
	 * @return list<int>
	 */
	public static function api_query_ids( array $criteria, $limit = null ) {
		$c = Qfibr_Criteria::normalize( $criteria );

		// Refuse rather than widen: unbounded criteria, or a taxonomy filter
		// that does not apply to the post type, would match far more posts
		// than the caller asked for.
		if ( ! Qfibr_Criteria::is_bounded( $c ) || array() !== Qfibr_Query::inapplicable_filters( $c ) ) {
			return array();
		}

		return Qfibr_Query::target_ids( $c, $limit );
	}

	/**
	 * IDs of every post that still has a stored original.
	 *
	 * @return list<int>
	 */
	public static function api_restorable_ids() {
		return Qfibr_Query::restorable_ids();
	}

	/**
	 * Replace one post's featured image, backing up the original once.
	 *
	 * @param int $post_id       Post to change.
	 * @param int $attachment_id Attachment to set.
	 * @return string 'replaced' | 'skipped' | 'error'
	 */
	public static function replace_one_post( $post_id, $attachment_id ) {
		return Qfibr_Replacer::replace_one( $post_id, $attachment_id );
	}

	/**
	 * Restore one post's original featured image.
	 *
	 * @param int $post_id Post to restore.
	 * @return string 'restored' | 'skipped' | 'error'
	 */
	public static function restore_one_post( $post_id ) {
		return Qfibr_Replacer::restore_one( $post_id );
	}
}
