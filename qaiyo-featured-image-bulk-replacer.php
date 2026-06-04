<?php
/**
 * Plugin Name: Qaiyo Featured Image Bulk Replacer
 * Plugin URI: https://qaiyo-plugins.com/qaiyo-featured-image-bulk-replacer/
 * Description: Bulk-replace the featured image of published posts with an image that already exists in the Media Library — filtered by date, scope and category. Batch processed (no timeouts), with preview, test run and one-click restore.
 * Version: 1.2.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Qaiyo by PixelDesigns
 * Author URI: https://qaiyo-plugins.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: qaiyo-featured-image-bulk-replacer
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QFIBR_VERSION', '1.2.0' );
define( 'QFIBR_PLUGIN_FILE', __FILE__ );
define( 'QFIBR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QFIBR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once QFIBR_PLUGIN_DIR . 'includes/class-qfibr-brand-menu.php';

if ( class_exists( 'Qfibr_Brand_Menu' ) ) {
	Qfibr_Brand_Menu::init();
	Qfibr_Brand_Menu::register_plugin_slug( 'qaiyo-featured-image-bulk-replacer' );

	// Cross-plugin fallback: if another Qaiyo plugin's brand-menu class loaded first,
	// register ourselves there too so the separator injection sees our menu.
	foreach ( array( 'Qaiyo_Access_Manager_Brand_Menu', 'Qt_Brand_Menu', 'Qls_Brand_Menu' ) as $qfibr_brand_class ) {
		if ( class_exists( $qfibr_brand_class ) && method_exists( $qfibr_brand_class, 'register_plugin_slug' ) ) {
			call_user_func( array( $qfibr_brand_class, 'register_plugin_slug' ), 'qaiyo-featured-image-bulk-replacer' );
		}
	}
}

/**
 * Load translations following the Qaiyo i18n standard (no load_plugin_textdomain()).
 */
function qfibr_load_translations() {
	$locale = determine_locale();

	$locale_map = array(
		'de_CH'          => 'de_DE',
		'de_AT'          => 'de_DE',
		'de_DE_formal'   => 'de_DE',
		'de_CH_informal' => 'de_DE',
		'fr_BE'          => 'fr_FR',
		'fr_CA'          => 'fr_FR',
		'fr_CH'          => 'fr_FR',
		'es_MX'          => 'es_ES',
		'es_AR'          => 'es_ES',
		'es_CO'          => 'es_ES',
		'es_VE'          => 'es_ES',
		'es_CL'          => 'es_ES',
		'es_PE'          => 'es_ES',
	);

	if ( isset( $locale_map[ $locale ] ) ) {
		$locale = $locale_map[ $locale ];
	}

	$supported = array( 'en_US', 'hu_HU', 'de_DE', 'fr_FR', 'es_ES' );
	if ( ! in_array( $locale, $supported, true ) || 'en_US' === $locale ) {
		return;
	}

	$mofile = QFIBR_PLUGIN_DIR . 'languages/qaiyo-featured-image-bulk-replacer-' . $locale . '.mo';
	if ( file_exists( $mofile ) ) {
		load_textdomain( 'qaiyo-featured-image-bulk-replacer', $mofile );
	}
}
add_action( 'init', 'qfibr_load_translations', 1 );

/**
 * Main plugin controller.
 */
final class Qfibr_Plugin {

	const MENU_SLUG    = 'qaiyo-featured-image-bulk-replacer';
	const AJAX_NONCE   = 'qfibr_ajax';
	const CAPABILITY   = 'manage_options';

	/** How many posts the test run touches. */
	const TEST_LIMIT = 3;

	/** Posts processed per AJAX batch — keeps each request light, avoids timeouts. */
	const BATCH_SIZE = 25;

	/** Max rows rendered in the preview table (the count reflects the full match). */
	const PREVIEW_ROWS = 100;

	/** Meta key storing the original featured image ID for restore. */
	const META_BACKUP_KEY = '_qfibr_original_thumbnail_id';

	/** Option holding a summary of the last completed run. */
	const LAST_RUN_OPTION = 'qfibr_last_run';

	/** Transient key prefix for an in-progress job's queued IDs + counters. */
	const JOB_PREFIX = 'qfibr_job_';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_qfibr_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_qfibr_prepare', array( $this, 'ajax_prepare' ) );
		add_action( 'wp_ajax_qfibr_batch', array( $this, 'ajax_batch' ) );
	}

	// =========================================================================
	// MENU & ASSETS
	// =========================================================================

	public function register_menu() {
		add_menu_page(
			__( 'Featured Image Bulk Replacer', 'qaiyo-featured-image-bulk-replacer' ),
			__( 'Featured Images', 'qaiyo-featured-image-bulk-replacer' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_admin_page' ),
			'dashicons-format-image',
			class_exists( 'Qfibr_Brand_Menu' ) ? Qfibr_Brand_Menu::plugin_position() : 25
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();

		$css_path = QFIBR_PLUGIN_DIR . 'assets/css/admin.css';
		$js_path  = QFIBR_PLUGIN_DIR . 'assets/js/admin.js';
		$css_ver  = file_exists( $css_path ) ? QFIBR_VERSION . '.' . filemtime( $css_path ) : QFIBR_VERSION;
		$js_ver   = file_exists( $js_path ) ? QFIBR_VERSION . '.' . filemtime( $js_path ) : QFIBR_VERSION;

		wp_enqueue_style( 'qfibr-admin', QFIBR_PLUGIN_URL . 'assets/css/admin.css', array(), $css_ver );
		wp_enqueue_script( 'qfibr-admin', QFIBR_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), $js_ver, true );

		wp_localize_script( 'qfibr-admin', 'qfibr_data', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::AJAX_NONCE ),
			'i18n'     => array(
				'media_title'     => __( 'Select the new featured image', 'qaiyo-featured-image-bulk-replacer' ),
				'media_button'    => __( 'Use this image', 'qaiyo-featured-image-bulk-replacer' ),
				'previewing'      => __( 'Loading preview…', 'qaiyo-featured-image-bulk-replacer' ),
				'preview_error'   => __( 'Could not load the preview.', 'qaiyo-featured-image-bulk-replacer' ),
				'need_image_date' => __( 'Please choose an image and a date first.', 'qaiyo-featured-image-bulk-replacer' ),
				'preparing'       => __( 'Preparing…', 'qaiyo-featured-image-bulk-replacer' ),
				'processing'      => __( 'Processing…', 'qaiyo-featured-image-bulk-replacer' ),
				'nothing'         => __( 'Nothing to process.', 'qaiyo-featured-image-bulk-replacer' ),
				'failed'          => __( 'Something went wrong. Please try again.', 'qaiyo-featured-image-bulk-replacer' ),
				'confirm_test'    => __( 'Run the TEST replacement on the first few matching posts?', 'qaiyo-featured-image-bulk-replacer' ),
				'confirm_live'    => __( 'Run the LIVE bulk featured-image replacement on ALL matching posts?', 'qaiyo-featured-image-bulk-replacer' ),
				'confirm_restore' => __( 'Restore the original featured image of every affected post?', 'qaiyo-featured-image-bulk-replacer' ),
			),
		) );
	}

	// =========================================================================
	// ADMIN PAGE
	// =========================================================================

	public function render_admin_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'qaiyo-featured-image-bulk-replacer' ) );
		}

		$restorable_count = $this->count_restorable_posts();
		$categories       = get_categories( array( 'hide_empty' => false, 'number' => 500 ) );
		$placeholder_url  = trailingslashit( home_url() ) . 'wp-content/uploads/' . gmdate( 'Y/m' ) . '/featured-image.jpg';
		?>
		<div class="wrap qfibr-wrap">
			<h1>
				<span class="qfibr-brand">Qaiyo</span>
				<?php esc_html_e( 'Featured Image Bulk Replacer', 'qaiyo-featured-image-bulk-replacer' ); ?>
				<span class="qfibr-version">v<?php echo esc_html( QFIBR_VERSION ); ?></span>
			</h1>
			<p class="qfibr-description">
				<?php esc_html_e( 'Replace the featured image of published posts with an image that already exists in your Media Library. Filter by date, scope and category, preview the result, then run it in safe batches — and restore the originals at any time.', 'qaiyo-featured-image-bulk-replacer' ); ?>
			</p>

			<div class="qfibr-card">
				<h2 class="qfibr-card-title"><?php esc_html_e( 'Replacement settings', 'qaiyo-featured-image-bulk-replacer' ); ?></h2>

				<form id="qfibr-form" onsubmit="return false;">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'New featured image', 'qaiyo-featured-image-bulk-replacer' ); ?></th>
							<td>
								<div class="qfibr-media">
									<div class="qfibr-media-preview" id="qfibr-media-preview" aria-hidden="true"></div>
									<div class="qfibr-media-controls">
										<button type="button" class="button" id="qfibr-media-btn">
											<span class="dashicons dashicons-format-image"></span>
											<?php esc_html_e( 'Choose from Media Library', 'qaiyo-featured-image-bulk-replacer' ); ?>
										</button>
										<input type="hidden" id="qfibr_attachment_id" value="0" />
										<input type="url" class="large-text code" id="qfibr_image_url" value="" placeholder="<?php echo esc_attr( $placeholder_url ); ?>" />
										<p class="description"><?php esc_html_e( 'Pick an image from the Media Library, or paste the URL of an image that is already uploaded. Nothing is downloaded.', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qfibr_scope"><?php esc_html_e( 'Target scope', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
							<td>
								<select id="qfibr_scope">
									<option value="all"><?php esc_html_e( 'All matching posts', 'qaiyo-featured-image-bulk-replacer' ); ?></option>
									<option value="missing"><?php esc_html_e( 'Only posts without a featured image', 'qaiyo-featured-image-bulk-replacer' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qfibr_post_type"><?php esc_html_e( 'Post type', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
							<td>
								<select id="qfibr_post_type">
									<?php $this->render_post_type_options(); ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qfibr_category"><?php esc_html_e( 'Category', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
							<td>
								<select id="qfibr_category">
									<option value="0"><?php esc_html_e( 'All categories', 'qaiyo-featured-image-bulk-replacer' ); ?></option>
									<?php foreach ( $categories as $cat ) : ?>
										<option value="<?php echo (int) $cat->term_id; ?>"><?php echo esc_html( $cat->name . ' (' . $cat->count . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Category filter applies to standard posts.', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qfibr_after_date"><?php esc_html_e( 'After date (optional)', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
							<td>
								<input type="date" id="qfibr_after_date" value="" />
								<p class="description"><?php esc_html_e( 'Only posts published on or after this date are affected. Leave empty for no lower bound.', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qfibr_before_date"><?php esc_html_e( 'Before date', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
							<td>
								<input type="date" id="qfibr_before_date" value="" required />
								<p class="description"><?php esc_html_e( 'Only posts published strictly before this date are affected (the date itself is excluded).', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qfibr_alt_text"><?php esc_html_e( 'Alt / title text (optional)', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
							<td>
								<input type="text" class="large-text" id="qfibr_alt_text" value="" />
								<p class="description"><?php esc_html_e( 'If filled, the alt and title of the chosen Media Library image are set to this text once. Leave empty to keep them unchanged.', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
							</td>
						</tr>
					</table>

					<div class="qfibr-actions">
						<button type="button" class="button button-secondary" id="qfibr-preview-btn">
							<span class="dashicons dashicons-visibility"></span>
							<?php esc_html_e( 'Preview', 'qaiyo-featured-image-bulk-replacer' ); ?>
						</button>
						<button type="button" class="button button-primary qfibr-run" data-job="replace" data-mode="test">
							<?php
							/* translators: %d: number of posts processed by the test run. */
							echo esc_html( sprintf( __( 'Test run (max %d posts)', 'qaiyo-featured-image-bulk-replacer' ), self::TEST_LIMIT ) );
							?>
						</button>
						<button type="button" class="button button-primary button-large qfibr-run qfibr-live-btn" data-job="replace" data-mode="live">
							<?php esc_html_e( 'Live run (all matching posts)', 'qaiyo-featured-image-bulk-replacer' ); ?>
						</button>
					</div>
					<p class="qfibr-hint"><?php esc_html_e( 'Tip: back up your database before a live run. Posts are processed in small batches so large sites do not time out. The original featured image of every changed post is stored so you can restore it below.', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
				</form>

				<div id="qfibr-progress" class="qfibr-progress" hidden>
					<div class="qfibr-progress-bar"><span id="qfibr-progress-fill"></span></div>
					<p class="qfibr-progress-label" id="qfibr-progress-label"></p>
				</div>

				<div id="qfibr-preview-results" class="qfibr-preview-results" hidden></div>
			</div>

			<div class="qfibr-card">
				<h2 class="qfibr-card-title"><?php esc_html_e( 'Restore originals', 'qaiyo-featured-image-bulk-replacer' ); ?></h2>
				<p class="qfibr-card-desc">
					<?php esc_html_e( 'Restores the original featured image of every post the plugin previously changed.', 'qaiyo-featured-image-bulk-replacer' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Restorable posts:', 'qaiyo-featured-image-bulk-replacer' ); ?></strong>
					<span class="qfibr-badge qfibr-badge-count" id="qfibr-restorable-count"><?php echo (int) $restorable_count; ?></span>
				</p>

				<?php if ( $restorable_count > 0 ) : ?>
					<button type="button" class="button button-secondary qfibr-run" data-job="restore">
						<span class="dashicons dashicons-undo"></span>
						<?php esc_html_e( 'Restore original featured images', 'qaiyo-featured-image-bulk-replacer' ); ?>
					</button>
				<?php else : ?>
					<p><em><?php esc_html_e( 'Nothing to restore right now.', 'qaiyo-featured-image-bulk-replacer' ); ?></em></p>
				<?php endif; ?>
			</div>

			<div class="qfibr-card">
				<h2 class="qfibr-card-title"><?php esc_html_e( 'Last run', 'qaiyo-featured-image-bulk-replacer' ); ?></h2>
				<?php $this->render_last_run(); ?>
			</div>

			<?php
			if ( self::pro_active() ) {
				/**
				 * Pro renders its own admin cards here (background processing,
				 * scheduling, auto featured image, run history).
				 */
				do_action( 'qfibr_pro_admin_cards' );
			} else {
				$this->render_pro_teasers();
			}
			?>

			<div class="qfibr-footer">
				<?php
				printf(
					/* translators: %s: plugin version. */
					esc_html__( 'Qaiyo Featured Image Bulk Replacer v%s — by', 'qaiyo-featured-image-bulk-replacer' ),
					esc_html( QFIBR_VERSION )
				);
				?>
				<a href="https://qaiyo-plugins.com" target="_blank" rel="noopener noreferrer">Qaiyo by PixelDesigns</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the summary of the last completed job.
	 */
	private function render_last_run() {
		$run = get_option( self::LAST_RUN_OPTION, array() );

		if ( empty( $run ) || empty( $run['time'] ) ) {
			echo '<p><em>' . esc_html__( 'No runs yet.', 'qaiyo-featured-image-bulk-replacer' ) . '</em></p>';
			return;
		}

		$when = date_i18n(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			(int) $run['time'] + ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )
		);

		if ( 'restore' === $run['type'] ) {
			$line = sprintf(
				/* translators: 1: date/time, 2: restored, 3: skipped, 4: errors. */
				__( 'Restore on %1$s — restored: %2$d, skipped: %3$d, errors: %4$d.', 'qaiyo-featured-image-bulk-replacer' ),
				$when,
				(int) $run['processed'],
				(int) $run['skipped'],
				(int) $run['errors']
			);
		} else {
			$mode_label = ( 'test' === $run['mode'] )
				? __( 'test', 'qaiyo-featured-image-bulk-replacer' )
				: __( 'live', 'qaiyo-featured-image-bulk-replacer' );
			$line = sprintf(
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

		echo '<p class="qfibr-summary">' . esc_html( $line ) . '</p>';
	}

	/**
	 * Freemium teaser: locked Pro feature cards shown when Pro is not active.
	 * The Pro plugin replaces these with the real UI via the qfibr_pro_admin_cards hook.
	 */
	private function render_pro_teasers() {
		$url    = self::upgrade_url();
		$lock   = '<svg class="qfibr-lock-svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="#fff" d="M6 10V8a6 6 0 1 1 12 0v2h1a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1h1zm2 0h8V8a4 4 0 1 0-8 0v2z"/></svg>';
		$unlock = esc_html__( 'Unlock in Pro →', 'qaiyo-featured-image-bulk-replacer' );

		$features = array(
			array(
				'title' => __( 'Background processing & scheduling', 'qaiyo-featured-image-bulk-replacer' ),
				'desc'  => __( 'Offload large runs to a server-friendly background queue with adaptive batch size, throttling and an off-peak window — or schedule a run for later. No need to keep the browser open.', 'qaiyo-featured-image-bulk-replacer' ),
				'icon'  => 'dashicons-clock',
			),
			array(
				'title' => __( 'Automatic featured image', 'qaiyo-featured-image-bulk-replacer' ),
				'desc'  => __( 'Automatically set a fallback featured image when a post is published without one — by post type and category, optionally using the first image found in the content.', 'qaiyo-featured-image-bulk-replacer' ),
				'icon'  => 'dashicons-update',
			),
			array(
				'title' => __( 'Run history & per-run restore', 'qaiyo-featured-image-bulk-replacer' ),
				'desc'  => __( 'Keep a history of every run and roll back any single run independently, plus an email report when a background job finishes.', 'qaiyo-featured-image-bulk-replacer' ),
				'icon'  => 'dashicons-backup',
			),
		);
		?>
		<div class="qfibr-card qfibr-pro-teaser-card">
			<h2 class="qfibr-card-title">
				<?php esc_html_e( 'Pro features', 'qaiyo-featured-image-bulk-replacer' ); ?>
				<span class="qfibr-pro-pill">PRO</span>
			</h2>
			<div class="qfibr-pro-grid">
				<?php foreach ( $features as $f ) : ?>
					<a class="qfibr-pro-locked" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="qfibr-pro-badge">PRO</span>
						<span class="qfibr-pro-preview">
							<span class="dashicons <?php echo esc_attr( $f['icon'] ); ?> qfibr-pro-preview-icon"></span>
							<span class="qfibr-pro-lock-overlay"><?php echo $lock; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static, self-contained inline SVG. ?></span>
						</span>
						<h3 class="qfibr-pro-title"><?php echo esc_html( $f['title'] ); ?></h3>
						<p class="qfibr-pro-desc"><?php echo esc_html( $f['desc'] ); ?></p>
						<span class="qfibr-pro-unlock"><?php echo esc_html( $unlock ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
			<div class="qfibr-pro-cta">
				<strong><?php esc_html_e( 'Qaiyo Featured Image Bulk Replacer Pro', 'qaiyo-featured-image-bulk-replacer' ); ?></strong>
				<a class="button button-primary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Get Pro', 'qaiyo-featured-image-bulk-replacer' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * <option> list of public post types that support featured images.
	 */
	private function render_post_type_options() {
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

	// =========================================================================
	// REQUEST PARSING (shared by the AJAX endpoints)
	// =========================================================================

	/** Verify nonce + capability for every AJAX endpoint. */
	private function verify_ajax() {
		check_ajax_referer( self::AJAX_NONCE, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'qaiyo-featured-image-bulk-replacer' ) ), 403 );
		}
	}

	/**
	 * Collect and sanitize the replacement criteria from $_POST.
	 *
	 * Only ever called from AJAX endpoints that have already run verify_ajax()
	 * (check_ajax_referer). The nonce-verification sniff cannot see that across
	 * the helper boundary, hence the scoped ignore below.
	 */
	private function read_criteria() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_ajax() in the calling AJAX handler.
		$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'all';

		$criteria = array(
			'attachment_id' => isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0,
			'image_url'     => isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '',
			'before_date'   => isset( $_POST['before_date'] ) ? sanitize_text_field( wp_unslash( $_POST['before_date'] ) ) : '',
			'after_date'    => isset( $_POST['after_date'] ) ? sanitize_text_field( wp_unslash( $_POST['after_date'] ) ) : '',
			'post_type'     => isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post',
			'scope'         => ( 'missing' === $scope ) ? 'missing' : 'all',
			'category'      => isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0,
			'alt_text'      => isset( $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $criteria;
	}

	// =========================================================================
	// QUERIES
	// =========================================================================

	/**
	 * Target post IDs matching the criteria.
	 *
	 * @param array    $c     Criteria from read_criteria().
	 * @param int|null $limit Null = no limit.
	 * @return int[]
	 */
	private function get_target_post_ids( $c, $limit = null ) {
		$args = array(
			'post_type'              => $c['post_type'],
			'post_status'            => 'publish',
			'posts_per_page'         => $limit ? (int) $limit : -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin-only bulk maintenance tool must enumerate every matching post; processing itself is batched.
			'fields'                 => 'ids',
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$date_query = array( 'relation' => 'AND' );
		if ( self::is_valid_date( $c['before_date'] ) ) {
			$date_query[] = array(
				'before'    => $c['before_date'] . ' 00:00:00',
				'inclusive' => false,
				'column'    => 'post_date',
			);
		}
		if ( self::is_valid_date( $c['after_date'] ) ) {
			$date_query[] = array(
				'after'     => $c['after_date'] . ' 00:00:00',
				'inclusive' => true,
				'column'    => 'post_date',
			);
		}
		if ( count( $date_query ) > 1 ) {
			$args['date_query'] = $date_query;
		}

		if ( 'missing' === $c['scope'] ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only maintenance tool, explicit user action.
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				),
			);
		}

		if ( $c['category'] > 0 && is_object_in_taxonomy( $c['post_type'], 'category' ) ) {
			$args['category__in'] = array( $c['category'] );
		}

		$query = new WP_Query( $args );
		return array_map( 'intval', $query->posts );
	}

	/**
	 * Count posts that still carry a stored original (i.e. are restorable).
	 */
	private function count_restorable_posts() {
		return count( $this->get_restorable_post_ids() );
	}

	private function get_restorable_post_ids() {
		$query = new WP_Query( array(
			'post_type'              => 'any',
			'post_status'            => 'any',
			'posts_per_page'         => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Admin-only restore enumeration; processing is batched.
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only maintenance tool, explicit user action.
				array(
					'key'     => self::META_BACKUP_KEY,
					'compare' => 'EXISTS',
				),
			),
		) );
		return array_map( 'intval', $query->posts );
	}

	/**
	 * Resolve an existing Media Library attachment from an ID or URL. Never downloads.
	 *
	 * @return int|WP_Error
	 */
	private function resolve_attachment( $attachment_id, $image_url ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
			return $attachment_id;
		}

		if ( '' === $image_url ) {
			return new WP_Error( 'qfibr_no_image', __( 'Please choose an image from the Media Library or paste an image URL.', 'qaiyo-featured-image-bulk-replacer' ) );
		}

		$found = attachment_url_to_postid( $image_url );
		if ( $found ) {
			return (int) $found;
		}

		// Fallback: a sized URL (…-150x150.jpg) → try the base file name.
		$stripped = preg_replace( '/-\d+x\d+(\.[a-zA-Z]+)$/', '$1', $image_url );
		if ( $stripped !== $image_url ) {
			$found = attachment_url_to_postid( $stripped );
			if ( $found ) {
				return (int) $found;
			}
		}

		return new WP_Error( 'qfibr_not_found', __( 'No Media Library image was found for that URL. Make sure the image is already uploaded.', 'qaiyo-featured-image-bulk-replacer' ) );
	}

	// =========================================================================
	// AJAX: PREVIEW (read-only)
	// =========================================================================

	public function ajax_preview() {
		$this->verify_ajax();
		$c = $this->read_criteria();

		if ( ! self::is_valid_date( $c['before_date'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid “before” date.', 'qaiyo-featured-image-bulk-replacer' ) ) );
		}

		$attachment_id = $this->resolve_attachment( $c['attachment_id'], $c['image_url'] );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		$post_ids = $this->get_target_post_ids( $c );
		$total    = count( $post_ids );
		$sample   = array_slice( $post_ids, 0, self::PREVIEW_ROWS );

		$would_replace = 0;
		$would_skip    = 0;
		$rows          = '';

		foreach ( $sample as $pid ) {
			$current_thumb_id = (int) get_post_thumbnail_id( $pid );
			$is_same          = ( $current_thumb_id === (int) $attachment_id );

			if ( $is_same ) {
				$action_label = __( 'skip (already correct)', 'qaiyo-featured-image-bulk-replacer' );
				$action_class = 'qfibr-skip';
			} else {
				$action_label = __( 'replace', 'qaiyo-featured-image-bulk-replacer' );
				$action_class = 'qfibr-replace';
			}

			$thumb = $current_thumb_id
				? wp_get_attachment_image( $current_thumb_id, array( 44, 44 ), true )
				: '<span class="qfibr-nothumb">—</span>';

			$rows .= sprintf(
				'<tr><td>%1$d</td><td class="qfibr-thumb-cell">%2$s</td><td>%3$s</td><td>%4$s</td><td class="%5$s">%6$s</td></tr>',
				(int) $pid,
				$thumb, // wp_get_attachment_image() returns escaped, attribute-safe HTML.
				esc_html( get_the_title( $pid ) ),
				esc_html( get_the_date( 'Y-m-d', $pid ) ),
				esc_attr( $action_class ),
				esc_html( $action_label )
			);
		}

		// Whole-match replace/skip counts (cheap: just compares thumbnail IDs).
		foreach ( $post_ids as $pid ) {
			if ( (int) get_post_thumbnail_id( $pid ) === (int) $attachment_id ) {
				++$would_skip;
			} else {
				++$would_replace;
			}
		}

		$summary = sprintf(
			/* translators: 1: attachment ID, 2: total matched, 3: to replace, 4: to skip. */
			__( 'Media Library attachment ID: %1$d. Matched posts: %2$d — to replace: %3$d, to skip: %4$d.', 'qaiyo-featured-image-bulk-replacer' ),
			(int) $attachment_id,
			$total,
			$would_replace,
			$would_skip
		);

		if ( '' === $rows ) {
			$html = '<p class="qfibr-summary">' . esc_html( $summary ) . '</p>'
				. '<p>' . esc_html__( 'No posts match these criteria.', 'qaiyo-featured-image-bulk-replacer' ) . '</p>';
		} else {
			$note = '';
			if ( $total > self::PREVIEW_ROWS ) {
				$note = '<p class="qfibr-hint">' . esc_html( sprintf(
					/* translators: 1: rows shown, 2: total matched. */
					__( 'Showing the first %1$d of %2$d matching posts.', 'qaiyo-featured-image-bulk-replacer' ),
					self::PREVIEW_ROWS,
					$total
				) ) . '</p>';
			}
			$html = '<p class="qfibr-summary">' . esc_html( $summary ) . '</p>' . $note
				. '<table class="qfibr-preview-table"><thead><tr>'
				. '<th>' . esc_html__( 'ID', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
				. '<th>' . esc_html__( 'Image', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
				. '<th>' . esc_html__( 'Title', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
				. '<th>' . esc_html__( 'Date', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
				. '<th>' . esc_html__( 'Action', 'qaiyo-featured-image-bulk-replacer' ) . '</th>'
				. '</tr></thead><tbody>' . $rows . '</tbody></table>';
		}

		wp_send_json_success( array( 'html' => $html ) );
	}

	// =========================================================================
	// AJAX: PREPARE (build the job queue, return a token)
	// =========================================================================

	public function ajax_prepare() {
		$this->verify_ajax();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_ajax() above.
		$job_raw  = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( $_POST['job'] ) ) : '';
		$job_type = ( 'restore' === $job_raw ) ? 'restore' : 'replace';

		if ( 'restore' === $job_type ) {
			$ids = $this->get_restorable_post_ids();
			$job = array(
				'type'      => 'restore',
				'ids'       => $ids,
				'total'     => count( $ids ),
				'done'      => 0,
				'processed' => 0,
				'skipped'   => 0,
				'errors'    => 0,
			);
		} else {
			$c = $this->read_criteria();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_ajax() above.
			$mode_raw = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
			$mode     = ( 'test' === $mode_raw ) ? 'test' : 'live';

			if ( ! self::is_valid_date( $c['before_date'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a valid “before” date.', 'qaiyo-featured-image-bulk-replacer' ) ) );
			}

			$attachment_id = $this->resolve_attachment( $c['attachment_id'], $c['image_url'] );
			if ( is_wp_error( $attachment_id ) ) {
				wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
			}

			// Optionally set a fixed alt/title on the chosen attachment, once.
			if ( '' !== $c['alt_text'] ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $c['alt_text'] );
				wp_update_post( array(
					'ID'         => $attachment_id,
					'post_title' => $c['alt_text'],
				) );
			}

			$limit = ( 'test' === $mode ) ? self::TEST_LIMIT : null;
			$ids   = $this->get_target_post_ids( $c, $limit );

			$job = array(
				'type'          => 'replace',
				'mode'          => $mode,
				'attachment_id' => (int) $attachment_id,
				'ids'           => $ids,
				'total'         => count( $ids ),
				'done'          => 0,
				'processed'     => 0,
				'skipped'       => 0,
				'errors'        => 0,
			);
		}

		if ( 0 === $job['total'] ) {
			wp_send_json_success( array( 'token' => '', 'total' => 0 ) );
		}

		$token = wp_generate_password( 20, false );
		set_transient( self::JOB_PREFIX . $token, $job, HOUR_IN_SECONDS );

		wp_send_json_success( array( 'token' => $token, 'total' => $job['total'] ) );
	}

	// =========================================================================
	// AJAX: BATCH (process the next slice of the queued job)
	// =========================================================================

	public function ajax_batch() {
		$this->verify_ajax();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by verify_ajax() above.
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$job   = $token ? get_transient( self::JOB_PREFIX . $token ) : false;

		if ( ! is_array( $job ) ) {
			wp_send_json_error( array( 'message' => __( 'The job expired. Please start again.', 'qaiyo-featured-image-bulk-replacer' ) ) );
		}

		$slice = array_slice( $job['ids'], $job['done'], self::BATCH_SIZE );

		foreach ( $slice as $pid ) {
			if ( 'restore' === $job['type'] ) {
				$this->process_restore_one( (int) $pid, $job );
			} else {
				$this->process_replace_one( (int) $pid, $job );
			}
			++$job['done'];
		}

		$finished = ( $job['done'] >= $job['total'] );

		if ( $finished ) {
			$this->store_last_run( $job );
			delete_transient( self::JOB_PREFIX . $token );
			/**
			 * Fires when a foreground run/restore completes. Pro uses this to log history.
			 *
			 * @param array $job Completed job (type, mode, ids, counters).
			 */
			do_action( 'qfibr_run_complete', $job );
		} else {
			set_transient( self::JOB_PREFIX . $token, $job, HOUR_IN_SECONDS );
		}

		wp_send_json_success( array(
			'done'      => (int) $job['done'],
			'total'     => (int) $job['total'],
			'processed' => (int) $job['processed'],
			'skipped'   => (int) $job['skipped'],
			'errors'    => (int) $job['errors'],
			'finished'  => $finished,
		) );
	}

	/** Replace one post's featured image (mutates $job counters by reference). */
	private function process_replace_one( $pid, &$job ) {
		$status = self::replace_one_post( $pid, (int) $job['attachment_id'] );
		$this->tally( $job, $status, 'replaced' );
	}

	/** Restore one post's original featured image (mutates $job counters by reference). */
	private function process_restore_one( $pid, &$job ) {
		$status = self::restore_one_post( $pid );
		$this->tally( $job, $status, 'restored' );
	}

	/** Map a per-post status string onto the job counters. */
	private function tally( &$job, $status, $ok_key ) {
		if ( $ok_key === $status ) {
			++$job['processed'];
		} elseif ( 'skipped' === $status ) {
			++$job['skipped'];
		} else {
			++$job['errors'];
		}
	}

	// =========================================================================
	// PUBLIC API — reused by Qaiyo Featured Image Bulk Replacer Pro
	// =========================================================================

	/** True when an active Pro license is present (Pro answers this filter). */
	public static function pro_active() {
		return (bool) apply_filters( 'qfibr_pro_active', false );
	}

	/** Upgrade / product URL (Pro can override). */
	public static function upgrade_url() {
		return apply_filters( 'qfibr_upgrade_url', 'https://qaiyo-plugins.com/qaiyo-featured-image-bulk-replacer-pro/' );
	}

	/**
	 * Resolve a Media Library attachment from an ID or URL. @return int|WP_Error
	 */
	public static function api_resolve_attachment( $attachment_id, $image_url ) {
		return self::get_instance()->resolve_attachment( $attachment_id, $image_url );
	}

	/**
	 * Enumerate target post IDs for a (raw) criteria array. Mirrors the free UI logic.
	 *
	 * @param array    $criteria Keys: image/before/after/post_type/scope/category (raw or normalized).
	 * @param int|null $limit
	 * @return int[]
	 */
	public static function api_query_ids( array $criteria, $limit = null ) {
		$c = wp_parse_args( $criteria, array(
			'before_date' => '',
			'after_date'  => '',
			'post_type'   => 'post',
			'scope'       => 'all',
			'category'    => 0,
		) );
		$c['post_type'] = sanitize_key( $c['post_type'] );
		$c['scope']     = ( 'missing' === $c['scope'] ) ? 'missing' : 'all';
		$c['category']  = absint( $c['category'] );
		return self::get_instance()->get_target_post_ids( $c, $limit );
	}

	/** IDs of every post that still has a stored original. @return int[] */
	public static function api_restorable_ids() {
		return self::get_instance()->get_restorable_post_ids();
	}

	/**
	 * Replace one post's featured image, backing up the original once.
	 *
	 * @return string 'replaced' | 'skipped' | 'error'
	 */
	public static function replace_one_post( $post_id, $attachment_id ) {
		$post_id          = (int) $post_id;
		$attachment_id    = (int) $attachment_id;
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
	 * @return string 'restored' | 'skipped' | 'error'
	 */
	public static function restore_one_post( $post_id ) {
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

	private function store_last_run( $job ) {
		update_option( self::LAST_RUN_OPTION, array(
			'time'      => time(),
			'type'      => $job['type'],
			'mode'      => isset( $job['mode'] ) ? $job['mode'] : '',
			'total'     => (int) $job['total'],
			'processed' => (int) $job['processed'],
			'skipped'   => (int) $job['skipped'],
			'errors'    => (int) $job['errors'],
		), false );
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	private static function is_valid_date( $date ) {
		if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		$parts = explode( '-', $date );
		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
	}
}

Qfibr_Plugin::get_instance();
