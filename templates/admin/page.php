<?php
/**
 * Admin screen: the whole Featured Image Bulk Replacer page.
 *
 * @var string   $qfibr_greeting_name    Display name used in the greeting.
 * @var int      $qfibr_restorable_count How many posts can be restored.
 * @var object[] $qfibr_categories       Categories for the filter dropdown.
 * @var string   $qfibr_placeholder_url  Example image URL for the input placeholder.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;
?>
		<div class="wrap qfibr-wrap">
			<p class="qfibr-greeting">
				<?php
				/* translators: %s: current user's first name. */
				printf( esc_html__( 'Hi, %s!', 'qaiyo-featured-image-bulk-replacer' ), esc_html( $qfibr_greeting_name ) );
				?>
				<span class="qfibr-wave" aria-hidden="true">&#128075;</span>
			</p>
			<h1>
				<span class="qfibr-brand">Qaiyo</span>
				<?php esc_html_e( 'Featured Image Bulk Replacer', 'qaiyo-featured-image-bulk-replacer' ); ?>
				<span class="qfibr-version">v<?php echo esc_html( QFIBR_VERSION ); ?></span>
			</h1>
			<p class="qfibr-description">
				<?php esc_html_e( 'Replace the featured image of published posts with an image that already exists in your Media Library. Filter by date, scope and category, preview the result, then run it in safe batches — and restore the originals at any time.', 'qaiyo-featured-image-bulk-replacer' ); ?>
			</p>

			<div class="qfibr-card">
				<h2 class="qfibr-card-title">
					<span class="qfibr-card-title-icon"><span class="dashicons dashicons-format-image"></span></span>
					<?php esc_html_e( 'Replacement settings', 'qaiyo-featured-image-bulk-replacer' ); ?>
				</h2>

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
										<input type="url" class="large-text code" id="qfibr_image_url" value="" placeholder="<?php echo esc_attr( $qfibr_placeholder_url ); ?>" />
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
									<?php Qfibr_Admin_Page::post_type_options(); ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qfibr_category"><?php esc_html_e( 'Category', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
							<td>
								<select id="qfibr_category">
									<option value="0"><?php esc_html_e( 'All categories', 'qaiyo-featured-image-bulk-replacer' ); ?></option>
									<?php foreach ( $qfibr_categories as $qfibr_cat ) : ?>
										<option value="<?php echo (int) $qfibr_cat->term_id; ?>"><?php echo esc_html( $qfibr_cat->name . ' (' . $qfibr_cat->count . ')' ); ?></option>
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
							echo esc_html( sprintf( __( 'Test run (max %d posts)', 'qaiyo-featured-image-bulk-replacer' ), Qfibr_Ajax::TEST_LIMIT ) );
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
				<h2 class="qfibr-card-title">
					<span class="qfibr-card-title-icon"><span class="dashicons dashicons-undo"></span></span>
					<?php esc_html_e( 'Restore originals', 'qaiyo-featured-image-bulk-replacer' ); ?>
				</h2>
				<p class="qfibr-card-desc">
					<?php esc_html_e( 'Restores the original featured image of every post the plugin previously changed.', 'qaiyo-featured-image-bulk-replacer' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Restorable posts:', 'qaiyo-featured-image-bulk-replacer' ); ?></strong>
					<span class="qfibr-badge qfibr-badge-count" id="qfibr-restorable-count"><?php echo (int) $qfibr_restorable_count; ?></span>
				</p>

				<?php if ( $qfibr_restorable_count > 0 ) : ?>
					<button type="button" class="button button-secondary qfibr-run" data-job="restore">
						<span class="dashicons dashicons-undo"></span>
						<?php esc_html_e( 'Restore original featured images', 'qaiyo-featured-image-bulk-replacer' ); ?>
					</button>
				<?php else : ?>
					<p><em><?php esc_html_e( 'Nothing to restore right now.', 'qaiyo-featured-image-bulk-replacer' ); ?></em></p>
				<?php endif; ?>
			</div>

			<div class="qfibr-card">
				<h2 class="qfibr-card-title">
					<span class="qfibr-card-title-icon"><span class="dashicons dashicons-clock"></span></span>
					<?php esc_html_e( 'Last run', 'qaiyo-featured-image-bulk-replacer' ); ?>
				</h2>
				<?php Qfibr_Admin_Page::output_last_run(); ?>
			</div>

			<?php
			if ( Qfibr_Plugin::pro_active() ) {
				/**
				 * Pro renders its own admin cards here (background processing,
				 * scheduling, auto featured image, run history).
				 */
				do_action( 'qfibr_pro_admin_cards' );
			} else {
				Qfibr_Pro_Teaser::output();
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
				<a href="https://qaiyo-plugins.com" target="_blank" rel="noopener noreferrer">Qaiyo</a>
			</div>
		</div>
