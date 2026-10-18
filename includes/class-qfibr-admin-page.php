<?php
/**
 * Admin screen controller.
 *
 * Thin on purpose: it registers the menu, gathers the few values the template
 * needs and hands them over. All markup lives in templates/admin/.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the plugin's admin page.
 */
final class Qfibr_Admin_Page {

	const MENU_SLUG  = 'qaiyo-featured-image-bulk-replacer';
	const CAPABILITY = 'manage_options';

	/** How many categories the filter dropdown lists. */
	const CATEGORY_LIMIT = 500;

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
			'qfibr_categories'       => get_categories( array( 'hide_empty' => false, 'number' => self::CATEGORY_LIMIT ) ),
			'qfibr_placeholder_url'  => trailingslashit( home_url() ) . 'wp-content/uploads/' . gmdate( 'Y/m' ) . '/featured-image.jpg',
		) );
	}

	/**
	 * The name to greet the current user with.
	 *
	 * @return string
	 */
	public static function greeting_name() {
		$user = wp_get_current_user();
		$name = $user->first_name ? $user->first_name : $user->display_name;

		return ( '' !== $name ) ? $name : $user->user_login;
	}

	/**
	 * The <option> list of public post types that support featured images.
	 *
	 * @return void
	 */
	public static function post_type_options() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $post_types as $pt ) {
			if ( ! post_type_supports( $pt->name, 'thumbnail' ) ) {
				continue;
			}
			printf(
				'<option value="%s"%s>%s (%s)</option>',
				esc_attr( $pt->name ),
				selected( $pt->name, 'post', false ),
				esc_html( $pt->labels->singular_name ),
				esc_html( $pt->name )
			);
		}
	}

	/**
	 * Echo the summary of the last completed run.
	 *
	 * @return void
	 */
	public static function output_last_run() {
		$run = Qfibr_Job_Store::last_run();

		if ( empty( $run ) || empty( $run['time'] ) ) {
			echo '<p><em>' . esc_html__( 'No runs yet.', 'qaiyo-featured-image-bulk-replacer' ) . '</em></p>';
			return;
		}

		echo '<p class="qfibr-summary">' . esc_html( self::last_run_line( $run ) ) . '</p>';
	}

	/**
	 * Build the human-readable summary line for a run.
	 *
	 * @param array<string, mixed> $run Stored run summary.
	 * @return string
	 */
	public static function last_run_line( array $run ) {
		$when = date_i18n(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			(int) $run['time'] + ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )
		);

		if ( 'restore' === $run['type'] ) {
			return sprintf(
				/* translators: 1: date/time, 2: restored, 3: skipped, 4: errors. */
				__( 'Restore on %1$s — restored: %2$d, skipped: %3$d, errors: %4$d.', 'qaiyo-featured-image-bulk-replacer' ),
				$when,
				(int) $run['processed'],
				(int) $run['skipped'],
				(int) $run['errors']
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
			(int) $run['total'],
			(int) $run['processed'],
			(int) $run['skipped'],
			(int) $run['errors']
		);
	}
}
