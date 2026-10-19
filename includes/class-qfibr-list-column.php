<?php
/**
 * "Featured image" column on the post lists, with in-place change and remove.
 *
 * Changes go through Qfibr_Replacer, so they are backed up and covered by
 * "Restore originals" exactly like a bulk run.
 *
 * The column is a small fixed pixel width, never a percentage: list tables are
 * `table-layout: fixed`, the core Posts columns already take about half the
 * width, and a percentage column squeezes the Title column on 1024–1440 px
 * screens.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the column, its sorting and its AJAX endpoints.
 */
final class Qfibr_List_Column {

	const COLUMN  = 'qfibr_thumb';
	const ORDERBY = 'qfibr_thumb';
	const NONCE   = 'qfibr_list';

	/** Rendered thumbnail size in CSS pixels. */
	const THUMB_PX = 32;

	/**
	 * Bind the hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'current_screen', array( __CLASS__, 'on_screen' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'sort' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_qfibr_list_set', array( __CLASS__, 'ajax_set' ) );
		add_action( 'wp_ajax_qfibr_list_remove', array( __CLASS__, 'ajax_remove' ) );
	}

	/**
	 * Post types the column is offered on.
	 *
	 * @param mixed $post_type Post type name.
	 * @return bool
	 */
	public static function supports( $post_type ) {
		if ( ! is_string( $post_type ) || '' === $post_type || ! post_type_supports( $post_type, 'thumbnail' ) ) {
			return false;
		}
		$object = get_post_type_object( $post_type );
		return $object instanceof WP_Post_Type && $object->show_ui;
	}

	/**
	 * Hook the column for the post type of the current list screen.
	 *
	 * @param mixed $screen Current screen.
	 * @return void
	 */
	public static function on_screen( $screen ) {
		if ( ! $screen instanceof WP_Screen || 'edit' !== $screen->base || ! self::supports( $screen->post_type ) ) {
			return;
		}

		$type = (string) $screen->post_type;
		add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'cell' ), 10, 2 );
		add_filter( "manage_edit-{$type}_sortable_columns", array( __CLASS__, 'sortable' ) );
	}

	/**
	 * Add the column right after the checkbox. Filter callback: untyped, and a
	 * non-array is passed through unchanged.
	 *
	 * @param mixed $columns Columns.
	 * @return mixed
	 */
	public static function columns( $columns ) {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}

		$header = '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>'
			. '<span class="screen-reader-text">' . esc_html__( 'Featured image', 'qaiyo-featured-image-bulk-replacer' ) . '</span>';

		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'cb' === $key ) {
				$out[ self::COLUMN ] = $header;
			}
		}
		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out = array( self::COLUMN => $header ) + $out;
		}
		return $out;
	}

	/**
	 * Make the column sortable. Filter callback.
	 *
	 * @param mixed $columns Sortable columns.
	 * @return mixed
	 */
	public static function sortable( $columns ) {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}
		$columns[ self::COLUMN ] = array( self::ORDERBY, false );
		return $columns;
	}

	/**
	 * Echo one cell.
	 *
	 * @param mixed $column  Column key.
	 * @param mixed $post_id Post ID.
	 * @return void
	 */
	public static function cell( $column, $post_id ) {
		if ( self::COLUMN !== $column ) {
			return;
		}
		echo wp_kses( self::cell_html( Qfibr_Cast::int( $post_id ) ), self::allowed_html() );
	}

	/**
	 * Markup of one cell.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function cell_html( $post_id ) {
		$thumb    = (int) get_post_thumbnail_id( $post_id );
		$title    = get_the_title( $post_id );
		$title    = '' !== $title ? $title : sprintf( '#%d', $post_id );
		$can_edit = current_user_can( 'edit_post', $post_id );

		$image = $thumb > 0
			? wp_get_attachment_image( $thumb, array( self::THUMB_PX, self::THUMB_PX ), false, array( 'alt' => '', 'loading' => 'lazy', 'class' => 'qfibr-col-img' ) )
			: '';
		$visual = '' !== $image ? $image : '<span class="dashicons dashicons-format-image qfibr-col-none" aria-hidden="true"></span>';

		$state = '<span class="screen-reader-text">' . esc_html( $thumb > 0
			? __( 'Has a featured image.', 'qaiyo-featured-image-bulk-replacer' )
			: __( 'No featured image.', 'qaiyo-featured-image-bulk-replacer' ) ) . '</span>';

		if ( ! $can_edit ) {
			return sprintf( '<div class="qfibr-col" data-post="%1$d"><span class="qfibr-col-thumb">%2$s</span>%3$s</div>', $post_id, $visual, $state );
		}

		// The thumbnail itself is the "choose" control: one 32 px target instead
		// of a thumbnail plus a separate button keeps the column narrow.
		$html = sprintf(
			'<button type="button" class="qfibr-col-btn qfibr-col-set" aria-label="%1$s">%2$s</button>',
			esc_attr( sprintf(
				/* translators: %s: post title. */
				__( 'Choose featured image for “%s”', 'qaiyo-featured-image-bulk-replacer' ),
				$title
			) ),
			$visual
		);

		if ( $thumb > 0 ) {
			$html .= sprintf(
				'<button type="button" class="qfibr-col-btn qfibr-col-remove" aria-label="%1$s"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>',
				esc_attr( sprintf(
					/* translators: %s: post title. */
					__( 'Remove featured image from “%s”', 'qaiyo-featured-image-bulk-replacer' ),
					$title
				) )
			);
		}

		return sprintf( '<div class="qfibr-col" data-post="%1$d">%2$s%3$s</div>', $post_id, $state, $html );
	}

	/**
	 * Sort by "has a featured image" when the column header is clicked.
	 *
	 * @param mixed $query Query.
	 * @return void
	 */
	public static function sort( $query ) {
		if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}
		if ( self::ORDERBY !== $query->get( 'orderby' ) ) {
			return;
		}

		$order = 'ASC' === strtoupper( Qfibr_Cast::string( $query->get( 'order' ) ) ) ? 'ASC' : 'DESC';

		$query->set( 'meta_query', array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Sorting an admin list on explicit request.
			'relation'  => 'OR',
			'qfibr_has' => array(
				'key'     => '_thumbnail_id',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => '_thumbnail_id',
				'compare' => 'NOT EXISTS',
			),
		) );
		$query->set( 'orderby', array( 'qfibr_has' => $order, 'date' => 'DESC' ) );
	}

	/**
	 * Assets on list screens of supported post types.
	 *
	 * @param mixed $hook_suffix Admin page.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! self::supports( $screen->post_type ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'qfibr-list', QFIBR_PLUGIN_URL . 'assets/css/qfibr-list.css', array(), qfibr_asset_version( 'assets/css/qfibr-list.css' ) );
		wp_enqueue_script( 'qfibr-list', QFIBR_PLUGIN_URL . 'assets/js/qfibr-list.js', array( 'jquery', 'wp-a11y' ), qfibr_asset_version( 'assets/js/qfibr-list.js' ), true );
		wp_localize_script( 'qfibr-list', 'qfibrList', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE ),
			'i18n'    => array(
				'title'   => __( 'Choose featured image', 'qaiyo-featured-image-bulk-replacer' ),
				'button'  => __( 'Use this image', 'qaiyo-featured-image-bulk-replacer' ),
				'set'     => __( 'Featured image changed.', 'qaiyo-featured-image-bulk-replacer' ),
				'removed' => __( 'Featured image removed.', 'qaiyo-featured-image-bulk-replacer' ),
				'confirm' => __( 'Remove the featured image from this post? You can undo it with “Restore originals”.', 'qaiyo-featured-image-bulk-replacer' ),
				'failed'  => __( 'Something went wrong. Please try again.', 'qaiyo-featured-image-bulk-replacer' ),
			),
		) );
	}

	/**
	 * Set a post's featured image from the list.
	 *
	 * @return void
	 */
	public static function ajax_set() {
		$post_id = self::verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by self::verify() above.
		$attachment_id = isset( $_POST['attachment_id'] ) && is_scalar( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! Qfibr_Attachment::exists( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose an image from the Media Library or paste an image URL.', 'qaiyo-featured-image-bulk-replacer' ) ), 400 );
		}

		$status = Qfibr_Replacer::replace_one( $post_id, $attachment_id );
		self::respond( $post_id, 'error' !== $status );
	}

	/**
	 * Remove a post's featured image from the list.
	 *
	 * @return void
	 */
	public static function ajax_remove() {
		$post_id = self::verify();
		$status  = Qfibr_Replacer::remove_one( $post_id );
		self::respond( $post_id, 'error' !== $status );
	}

	/**
	 * Nonce + per-post capability. Mirrors core's own set-post-thumbnail
	 * endpoint: whoever may edit the post may change its featured image.
	 *
	 * @return int Verified post ID.
	 */
	private static function verify() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) && is_scalar( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post_id ) || ! self::supports( $post->post_type ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'qaiyo-featured-image-bulk-replacer' ) ), 403 );
		}

		return $post_id;
	}

	/**
	 * Answer with the refreshed cell.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $ok      Whether the change succeeded.
	 * @return void
	 */
	private static function respond( $post_id, $ok ) {
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'qaiyo-featured-image-bulk-replacer' ) ), 500 );
		}
		wp_send_json_success( array( 'html' => wp_kses( self::cell_html( $post_id ), self::allowed_html() ) ) );
	}

	/**
	 * Markup allowed in a cell.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html() {
		return array(
			'div'    => array( 'class' => true, 'data-post' => true ),
			'span'   => array( 'class' => true, 'aria-hidden' => true ),
			'button' => array( 'type' => true, 'class' => true, 'aria-label' => true ),
			'img'    => array( 'src' => true, 'srcset' => true, 'sizes' => true, 'width' => true, 'height' => true, 'alt' => true, 'class' => true, 'loading' => true, 'decoding' => true ),
		);
	}
}
