<?php
/**
 * "Featured image of" — which posts use an attachment as their featured image.
 *
 * Shown as a Media Library list column and as a read-only field in the
 * attachment details (grid modal and edit screen), so it is safe to tell
 * whether deleting or replacing an image will affect published posts.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Looks up and renders featured-image usage.
 */
final class Qfibr_Usage {

	const COLUMN = 'qfibr_used_by';

	/** Posts listed per attachment before "and N more". */
	const SHOWN = 3;

	/** Upper bound on rows read for one list page. */
	const QUERY_LIMIT = 1000;

	/**
	 * Primed usage for the attachments of the current list page.
	 *
	 * @var array<int, list<int>>|null
	 */
	private static $map = null;

	/**
	 * Bind the hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'manage_media_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_media_custom_column', array( __CLASS__, 'cell' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'field' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Column styles on the Media Library list.
	 *
	 * @param mixed $hook_suffix Admin page.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'upload.php' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'qfibr-list', QFIBR_PLUGIN_URL . 'assets/css/qfibr-list.css', array(), qfibr_asset_version( 'assets/css/qfibr-list.css' ) );
	}

	/**
	 * Post IDs using each of the given attachments as featured image.
	 *
	 * One query for the whole set, not one per attachment.
	 *
	 * @param list<int> $attachment_ids Attachment IDs.
	 * @return array<int, list<int>> attachment ID => post IDs.
	 */
	public static function lookup( array $attachment_ids ) {
		$map = array();
		foreach ( $attachment_ids as $id ) {
			$map[ $id ] = array();
		}
		if ( array() === $attachment_ids ) {
			return $map;
		}

		$query = new WP_Query( array(
			'post_type'              => 'any',
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'         => self::QUERY_LIMIT,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One bounded query per admin list page instead of one per row.
				array(
					'key'     => '_thumbnail_id',
					'value'   => array_map( 'strval', $attachment_ids ),
					'compare' => 'IN',
				),
			),
		) );

		foreach ( Qfibr_Query::ids( $query->posts ) as $post_id ) {
			$thumb = Qfibr_Cast::int( get_post_meta( $post_id, '_thumbnail_id', true ) );
			if ( isset( $map[ $thumb ] ) ) {
				$map[ $thumb ][] = $post_id;
			}
		}

		return $map;
	}

	/**
	 * Usage of one attachment, priming the whole list page on first call.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return list<int>
	 */
	public static function for_attachment( $attachment_id ) {
		if ( null === self::$map || ! isset( self::$map[ $attachment_id ] ) ) {
			$ids = array( $attachment_id );

			global $wp_query;
			if ( $wp_query instanceof WP_Query ) {
				$ids = array_values( array_unique( array_merge( $ids, Qfibr_Query::ids( $wp_query->posts ) ) ) );
			}

			self::$map = ( self::$map ?? array() ) + self::lookup( $ids );
		}

		return self::$map[ $attachment_id ] ?? array();
	}

	/**
	 * Markup listing the posts.
	 *
	 * @param list<int> $post_ids Post IDs.
	 * @return string
	 */
	public static function html( array $post_ids ) {
		if ( array() === $post_ids ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">'
				. esc_html__( 'Not used as a featured image', 'qaiyo-featured-image-bulk-replacer' ) . '</span>';
		}

		$items = '';
		foreach ( array_slice( $post_ids, 0, self::SHOWN ) as $post_id ) {
			$title = get_the_title( $post_id );
			$title = '' !== $title ? $title : sprintf( '#%d', $post_id );
			$link  = get_edit_post_link( $post_id );

			$items .= '<li>' . ( $link
				? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>'
				: esc_html( $title ) ) . '</li>';
		}

		$more = count( $post_ids ) - self::SHOWN;
		if ( $more > 0 ) {
			$items .= '<li>' . esc_html( sprintf(
				/* translators: %d: number of further posts. */
				__( 'and %d more', 'qaiyo-featured-image-bulk-replacer' ),
				$more
			) ) . '</li>';
		}

		return '<ul class="qfibr-usage">' . $items . '</ul>';
	}

	/**
	 * Add the Media Library column. Filter callback.
	 *
	 * @param mixed $columns Columns.
	 * @return mixed
	 */
	public static function columns( $columns ) {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}
		$columns[ self::COLUMN ] = esc_html__( 'Featured image of', 'qaiyo-featured-image-bulk-replacer' );
		return $columns;
	}

	/**
	 * Echo one cell.
	 *
	 * @param mixed $column        Column key.
	 * @param mixed $attachment_id Attachment ID.
	 * @return void
	 */
	public static function cell( $column, $attachment_id ) {
		if ( self::COLUMN !== $column ) {
			return;
		}
		echo wp_kses_post( self::html( self::for_attachment( Qfibr_Cast::int( $attachment_id ) ) ) );
	}

	/**
	 * Read-only field in the attachment details. Filter callback: untyped, and
	 * unexpected input is passed through unchanged.
	 *
	 * @param mixed $fields Fields.
	 * @param mixed $post   Attachment.
	 * @return mixed
	 */
	public static function field( $fields, $post ) {
		if ( ! is_array( $fields ) || ! $post instanceof WP_Post || ! wp_attachment_is_image( $post->ID ) ) {
			return $fields;
		}

		$fields['qfibr_used_by'] = array(
			'label' => __( 'Featured image of', 'qaiyo-featured-image-bulk-replacer' ),
			'input' => 'html',
			'html'  => wp_kses_post( self::html( self::lookup( array( $post->ID ) )[ $post->ID ] ?? array() ) ),
		);
		return $fields;
	}
}
