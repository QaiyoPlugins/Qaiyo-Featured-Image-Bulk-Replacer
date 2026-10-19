<?php
/**
 * Admin screen controller.
 *
 * Thin on purpose: it registers the menu, gathers the values the template needs
 * and hands them over. All markup lives in templates/admin/.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the plugin's admin page.
 *
 * @phpstan-import-type Qfibr_Last_Run from Qfibr_Job_Store
 * @phpstan-type Qfibr_Option array{value: string, label: string}
 * @phpstan-type Qfibr_Option_Group array{label: string, options: list<Qfibr_Option>}
 */
final class Qfibr_Admin_Page {

	const MENU_SLUG  = 'qaiyo-featured-image-bulk-replacer';
	const CAPABILITY = 'manage_options';

	/** How many terms a filter dropdown lists. */
	const TERM_LIMIT = 500;

	/** How many authors the author filter lists. */
	const AUTHOR_LIMIT = 200;

	/**
	 * Bind the menu hook.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Add the top-level menu entry.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Featured Image Bulk Replacer', 'qaiyo-featured-image-bulk-replacer' ),
			__( 'Featured Images', 'qaiyo-featured-image-bulk-replacer' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-format-image',
			Qfibr_Brand_Menu::plugin_position()
		);
		Qfibr_Brand_Menu::register_plugin_slug( self::MENU_SLUG );
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'qaiyo-featured-image-bulk-replacer' ) );
		}

		Qfibr_View::output( 'page', array(
			'qfibr_greeting_name'    => self::greeting_name(),
			'qfibr_restorable_count' => Qfibr_Query::restorable_count(),
			'qfibr_post_types'       => self::post_type_options(),
			'qfibr_categories'       => self::term_options( 'category' ),
			'qfibr_tags'             => self::term_options( 'post_tag' ),
			'qfibr_tax_groups'       => self::taxonomy_groups(),
			'qfibr_authors'          => self::author_options(),
			'qfibr_placeholder_url'  => trailingslashit( home_url() ) . 'wp-content/uploads/' . gmdate( 'Y/m' ) . '/featured-image.jpg',
			'qfibr_last_run'         => self::last_run_line( Qfibr_Job_Store::last_run() ),
			'qfibr_pro_active'       => Qfibr_Plugin::pro_active(),
		) );
	}

	/**
	 * The name to greet the current user with.
	 *
	 * @return string
	 */
	public static function greeting_name() {
		$user = wp_get_current_user();
		$name = '' !== $user->first_name ? $user->first_name : $user->display_name;

		return ( '' !== $name ) ? $name : $user->user_login;
	}

	/**
	 * Public post types that support featured images.
	 *
	 * @return list<Qfibr_Option>
	 */
	public static function post_type_options() {
		$options = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( ! $pt instanceof WP_Post_Type || ! post_type_supports( $pt->name, 'thumbnail' ) ) {
				continue;
			}
			$options[] = array(
				'value' => $pt->name,
				'label' => sprintf( '%s (%s)', Qfibr_Cast::string( $pt->labels->singular_name ), $pt->name ),
			);
		}
		return $options;
	}

	/**
	 * Terms of one taxonomy for a dropdown.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return list<Qfibr_Option>
	 */
	public static function term_options( $taxonomy ) {
		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => self::TERM_LIMIT,
		) );

		$options = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof WP_Term ) {
				$options[] = array(
					'value' => (string) $term->term_id,
					'label' => sprintf( '%s (%d)', $term->name, $term->count ),
				);
			}
		}
		return $options;
	}

	/**
	 * Other public taxonomies (not category/tag) with their terms, grouped.
	 *
	 * @return list<Qfibr_Option_Group>
	 */
	public static function taxonomy_groups() {
		$groups = array();
		foreach ( get_taxonomies( array( 'public' => true, 'show_ui' => true ), 'objects' ) as $tax ) {
			if ( ! $tax instanceof WP_Taxonomy || in_array( $tax->name, array( 'category', 'post_tag', 'post_format' ), true ) ) {
				continue;
			}

			$options = array();
			foreach ( self::term_options( $tax->name ) as $option ) {
				$options[] = array(
					'value' => $tax->name . ':' . $option['value'],
					'label' => $option['label'],
				);
			}
			if ( array() !== $options ) {
				$groups[] = array(
					'label'   => Qfibr_Cast::string( $tax->labels->name ),
					'options' => $options,
				);
			}
		}
		return $groups;
	}

	/**
	 * Users who can write posts.
	 *
	 * @return list<Qfibr_Option>
	 */
	public static function author_options() {
		$users = get_users( array(
			'capability' => array( 'edit_posts' ),
			'fields'     => array( 'ID', 'display_name' ),
			'number'     => self::AUTHOR_LIMIT,
			'orderby'    => 'display_name',
		) );

		$options = array();
		foreach ( $users as $user ) {
			if ( is_object( $user ) && isset( $user->ID, $user->display_name ) ) {
				$options[] = array(
					'value' => (string) Qfibr_Cast::int( $user->ID ),
					'label' => Qfibr_Cast::string( $user->display_name ),
				);
			}
		}
		return $options;
	}

	/**
	 * Human-readable summary line for the last run.
	 *
	 * @param Qfibr_Last_Run|null $run Stored run summary.
	 * @return string Empty when there was no run yet.
	 */
	public static function last_run_line( $run ) {
		if ( null === $run ) {
			return '';
		}

		$when = date_i18n(
			Qfibr_Cast::string( get_option( 'date_format' ) ) . ' ' . Qfibr_Cast::string( get_option( 'time_format' ) ),
			$run['time'] + (int) ( (float) Qfibr_Cast::string( get_option( 'gmt_offset' ) ) * HOUR_IN_SECONDS )
		);

		if ( 'restore' === $run['type'] ) {
			return sprintf(
				/* translators: 1: date/time, 2: restored, 3: skipped, 4: errors. */
				__( 'Restore on %1$s — restored: %2$d, skipped: %3$d, errors: %4$d.', 'qaiyo-featured-image-bulk-replacer' ),
				$when,
				$run['processed'],
				$run['skipped'],
				$run['errors']
			);
		}

		if ( 'cleanup' === $run['type'] ) {
			return sprintf(
				/* translators: 1: date/time, 2: cleaned, 3: skipped, 4: errors. */
				__( 'Broken image cleanup on %1$s — cleaned: %2$d, skipped: %3$d, errors: %4$d.', 'qaiyo-featured-image-bulk-replacer' ),
				$when,
				$run['processed'],
				$run['skipped'],
				$run['errors']
			);
		}

		$mode_label = ( 'test' === $run['mode'] )
			? __( 'test', 'qaiyo-featured-image-bulk-replacer' )
			: __( 'live', 'qaiyo-featured-image-bulk-replacer' );

		return sprintf(
			/* translators: 1: mode label, 2: date/time, 3: matched, 4: replaced, 5: skipped, 6: errors. */
			__( 'Replacement (%1$s) on %2$s — matched: %3$d, replaced: %4$d, skipped: %5$d, errors: %6$d.', 'qaiyo-featured-image-bulk-replacer' ),
			$mode_label,
			$when,
			$run['total'],
			$run['processed'],
			$run['skipped'],
			$run['errors']
		);
	}

	/**
	 * Markup for an orphan scan result.
	 *
	 * @param array{total: int, sample: list<array{id: int, title: string, edit: string}>} $summary Scan result.
	 * @return string
	 */
	public static function orphan_result_html( array $summary ) {
		return Qfibr_View::render( 'orphan-result', array( 'qfibr_summary' => $summary ) );
	}
}
