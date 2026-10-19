<?php
/**
 * Admin screen: the whole Featured Image Bulk Replacer page.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

/**
 * @var string                                                      $qfibr_greeting_name    Display name used in the greeting.
 * @var int                                                         $qfibr_restorable_count How many posts can be restored.
 * @var list<array{value: string, label: string}>                   $qfibr_post_types       Post types.
 * @var list<array{value: string, label: string}>                   $qfibr_categories       Categories.
 * @var list<array{value: string, label: string}>                   $qfibr_tags             Tags.
 * @var list<array{label: string, options: list<array{value: string, label: string}>}> $qfibr_tax_groups Other taxonomies.
 * @var list<array{value: string, label: string}>                   $qfibr_authors          Authors.
 * @var string                                                      $qfibr_placeholder_url  Example image URL for the input placeholder.
 * @var string                                                      $qfibr_last_run         Last run summary, or empty.
 * @var bool                                                        $qfibr_pro_active       Whether the Pro add-on is active.
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
		<?php esc_html_e( 'Replace the featured image of published posts with an image that already exists in your Media Library. Filter by date, scope, category, tag, author or taxonomy, preview the result, then run it in safe batches — and restore the originals at any time.', 'qaiyo-featured-image-bulk-replacer' ); ?>
	</p>

	<div class="qfibr-card">
		<h2 class="qfibr-card-title">
			<span class="qfibr-card-title-icon" aria-hidden="true"><span class="dashicons dashicons-format-image"></span></span>
			<?php esc_html_e( 'Replacement settings', 'qaiyo-featured-image-bulk-replacer' ); ?>
		</h2>

		<div id="qfibr-form-error" class="qfibr-error" role="alert" tabindex="-1" hidden></div>

		<form id="qfibr-form" novalidate>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'New featured image', 'qaiyo-featured-image-bulk-replacer' ); ?></th>
					<td>
						<fieldset class="qfibr-assign">
							<legend class="screen-reader-text"><?php esc_html_e( 'How images are assigned', 'qaiyo-featured-image-bulk-replacer' ); ?></legend>
							<label><input type="radio" name="qfibr_assign_mode" value="single" checked /> <?php esc_html_e( 'The same image for every post', 'qaiyo-featured-image-bulk-replacer' ); ?></label>
							<label><input type="radio" name="qfibr_assign_mode" value="random" aria-describedby="qfibr-random-help" /> <?php esc_html_e( 'A random image from a selection', 'qaiyo-featured-image-bulk-replacer' ); ?></label>
							<p id="qfibr-random-help" class="description"><?php esc_html_e( 'Pick at least two images. Each post gets one of them; a retried batch gives a post the same image again.', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
						</fieldset>

						<div class="qfibr-media">
							<ul class="qfibr-media-preview" id="qfibr-media-preview" aria-label="<?php esc_attr_e( 'Selected images', 'qaiyo-featured-image-bulk-replacer' ); ?>" hidden></ul>
							<div class="qfibr-media-controls">
								<button type="button" class="button" id="qfibr-media-btn" aria-describedby="qfibr-image-help">
									<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
									<span class="qfibr-media-btn-label"><?php esc_html_e( 'Choose from Media Library', 'qaiyo-featured-image-bulk-replacer' ); ?></span>
								</button>
								<input type="hidden" id="qfibr_attachment_id" value="0" />
								<input type="hidden" id="qfibr_attachment_ids" value="" />
								<div class="qfibr-url-field">
									<label for="qfibr_image_url"><?php esc_html_e( 'Or paste the URL of an uploaded image', 'qaiyo-featured-image-bulk-replacer' ); ?></label>
									<input type="url" class="large-text code" id="qfibr_image_url" value="" placeholder="<?php echo esc_attr( $qfibr_placeholder_url ); ?>" aria-describedby="qfibr-image-help" />
								</div>
								<p id="qfibr-image-help" class="description"><?php esc_html_e( 'Pick an image from the Media Library, or paste the URL of an image that is already uploaded. Nothing is downloaded.', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
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
							<?php foreach ( $qfibr_post_types as $qfibr_option ) : ?>
								<option value="<?php echo esc_attr( $qfibr_option['value'] ); ?>" <?php selected( $qfibr_option['value'], 'post' ); ?>><?php echo esc_html( $qfibr_option['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="qfibr_category"><?php esc_html_e( 'Category', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
					<td>
						<select id="qfibr_category" aria-describedby="qfibr-tax-help">
							<option value="0"><?php esc_html_e( 'All categories', 'qaiyo-featured-image-bulk-replacer' ); ?></option>
							<?php foreach ( $qfibr_categories as $qfibr_option ) : ?>
								<option value="<?php echo esc_attr( $qfibr_option['value'] ); ?>"><?php echo esc_html( $qfibr_option['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="qfibr_tag"><?php esc_html_e( 'Tag', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
					<td>
						<select id="qfibr_tag" aria-describedby="qfibr-tax-help">
							<option value="0"><?php esc_html_e( 'All tags', 'qaiyo-featured-image-bulk-replacer' ); ?></option>
							<?php foreach ( $qfibr_tags as $qfibr_option ) : ?>
								<option value="<?php echo esc_attr( $qfibr_option['value'] ); ?>"><?php echo esc_html( $qfibr_option['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<?php if ( array() !== $qfibr_tax_groups ) : ?>
					<tr>
						<th scope="row"><label for="qfibr_tax_term"><?php esc_html_e( 'Other taxonomy', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
						<td>
							<select id="qfibr_tax_term" aria-describedby="qfibr-tax-help">
								<option value=""><?php esc_html_e( 'No filter', 'qaiyo-featured-image-bulk-replacer' ); ?></option>
								<?php foreach ( $qfibr_tax_groups as $qfibr_group ) : ?>
									<optgroup label="<?php echo esc_attr( $qfibr_group['label'] ); ?>">
										<?php foreach ( $qfibr_group['options'] as $qfibr_option ) : ?>
											<option value="<?php echo esc_attr( $qfibr_option['value'] ); ?>"><?php echo esc_html( $qfibr_option['label'] ); ?></option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><label for="qfibr_author"><?php esc_html_e( 'Author', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
					<td>
						<select id="qfibr_author">
							<option value="0"><?php esc_html_e( 'All authors', 'qaiyo-featured-image-bulk-replacer' ); ?></option>
							<?php foreach ( $qfibr_authors as $qfibr_option ) : ?>
								<option value="<?php echo esc_attr( $qfibr_option['value'] ); ?>"><?php echo esc_html( $qfibr_option['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p id="qfibr-tax-help" class="description"><?php esc_html_e( 'Category, tag and taxonomy filters must apply to the chosen post type, otherwise the run is refused rather than widened.', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="qfibr_after_date"><?php esc_html_e( 'After date (optional)', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
					<td>
						<input type="date" id="qfibr_after_date" value="" aria-describedby="qfibr-after-help" />
						<p id="qfibr-after-help" class="description"><?php esc_html_e( 'Only posts published on or after this date are affected. Leave empty for no lower bound.', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="qfibr_before_date">
							<?php esc_html_e( 'Before date', 'qaiyo-featured-image-bulk-replacer' ); ?>
							<span class="qfibr-required"><?php esc_html_e( '(required)', 'qaiyo-featured-image-bulk-replacer' ); ?></span>
						</label>
					</th>
					<td>
						<input type="date" id="qfibr_before_date" value="" required aria-required="true" aria-describedby="qfibr-before-help" />
						<p id="qfibr-before-help" class="description"><?php esc_html_e( 'Only posts published strictly before this date are affected (the date itself is excluded).', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
					</td>
				</tr>
				<tr class="qfibr-single-only">
					<th scope="row"><label for="qfibr_alt_text"><?php esc_html_e( 'Alt / title text (optional)', 'qaiyo-featured-image-bulk-replacer' ); ?></label></th>
					<td>
						<input type="text" class="large-text" id="qfibr_alt_text" value="" aria-describedby="qfibr-alt-help" />
						<p id="qfibr-alt-help" class="description"><?php esc_html_e( 'If filled, the alt and title of the chosen Media Library image are set to this text once. Leave empty to keep them unchanged.', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
					</td>
				</tr>
				<?php
				/**
				 * Lets add-ons render extra criteria rows. Inputs carrying a
				 * `data-qfibr-extra="key"` attribute are sent along as extra[key].
				 */
				do_action( 'qfibr_criteria_fields' );
				?>
			</table>

			<div class="qfibr-actions">
				<button type="button" class="button button-secondary" id="qfibr-preview-btn">
					<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
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
			<div class="qfibr-progress-bar" role="progressbar" aria-labelledby="qfibr-progress-label" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="qfibr-progress-fill"></span></div>
			<p class="qfibr-progress-label" id="qfibr-progress-label" aria-live="polite"></p>
		</div>

		<div id="qfibr-preview-results" class="qfibr-preview-results" aria-live="polite" hidden></div>
	</div>

	<div class="qfibr-card">
		<h2 class="qfibr-card-title">
			<span class="qfibr-card-title-icon" aria-hidden="true"><span class="dashicons dashicons-undo"></span></span>
			<?php esc_html_e( 'Restore originals', 'qaiyo-featured-image-bulk-replacer' ); ?>
		</h2>
		<p class="qfibr-card-desc">
			<?php esc_html_e( 'Restores the original featured image of every post the plugin previously changed — bulk runs, list-column changes and broken-image cleanups alike.', 'qaiyo-featured-image-bulk-replacer' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Restorable posts:', 'qaiyo-featured-image-bulk-replacer' ); ?></strong>
			<span class="qfibr-badge qfibr-badge-count" id="qfibr-restorable-count"><?php echo (int) $qfibr_restorable_count; ?></span>
		</p>

		<?php if ( $qfibr_restorable_count > 0 ) : ?>
			<button type="button" class="button button-secondary qfibr-run" data-job="restore">
				<span class="dashicons dashicons-undo" aria-hidden="true"></span>
				<?php esc_html_e( 'Restore original featured images', 'qaiyo-featured-image-bulk-replacer' ); ?>
			</button>
		<?php else : ?>
			<p><em><?php esc_html_e( 'Nothing to restore right now.', 'qaiyo-featured-image-bulk-replacer' ); ?></em></p>
		<?php endif; ?>
	</div>

	<div class="qfibr-card">
		<h2 class="qfibr-card-title">
			<span class="qfibr-card-title-icon" aria-hidden="true"><span class="dashicons dashicons-warning"></span></span>
			<?php esc_html_e( 'Broken featured images', 'qaiyo-featured-image-bulk-replacer' ); ?>
		</h2>
		<p class="qfibr-card-desc">
			<?php esc_html_e( 'Finds posts whose featured image points at an attachment that no longer exists, and removes that dangling reference. Images whose file is stored elsewhere (offloaded media) are never touched.', 'qaiyo-featured-image-bulk-replacer' ); ?>
		</p>
		<div class="qfibr-actions">
			<button type="button" class="button button-secondary" id="qfibr-orphan-scan">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<?php esc_html_e( 'Scan for broken featured images', 'qaiyo-featured-image-bulk-replacer' ); ?>
			</button>
			<button type="button" class="button qfibr-run" id="qfibr-orphan-clean" data-job="cleanup" hidden>
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<?php esc_html_e( 'Remove broken references', 'qaiyo-featured-image-bulk-replacer' ); ?>
			</button>
		</div>
		<div id="qfibr-orphan-results" aria-live="polite"></div>
	</div>

	<div class="qfibr-card">
		<h2 class="qfibr-card-title">
			<span class="qfibr-card-title-icon" aria-hidden="true"><span class="dashicons dashicons-clock"></span></span>
			<?php esc_html_e( 'Last run', 'qaiyo-featured-image-bulk-replacer' ); ?>
		</h2>
		<?php if ( '' === $qfibr_last_run ) : ?>
			<p><em><?php esc_html_e( 'No runs yet.', 'qaiyo-featured-image-bulk-replacer' ); ?></em></p>
		<?php else : ?>
			<p class="qfibr-summary"><?php echo esc_html( $qfibr_last_run ); ?></p>
		<?php endif; ?>
	</div>

	<?php
	if ( $qfibr_pro_active ) {
		/**
		 * Pro renders its own admin cards here.
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
		<a href="https://qaiyo-plugins.com" target="_blank" rel="noopener noreferrer">Qaiyo<span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'qaiyo-featured-image-bulk-replacer' ); ?></span></a>
	</div>
</div>
