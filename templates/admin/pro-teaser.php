<?php
/**
 * Admin screen: the locked Pro feature cards shown when Pro is not active.
 *
 * @var string  $qfibr_url      Upgrade URL.
 * @var string  $qfibr_lock     Inline padlock SVG.
 * @var string  $qfibr_unlock   "Unlock in Pro" label.
 * @var array[] $qfibr_features Locked feature catalogue.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

defined( 'ABSPATH' ) || exit;
?>
		<div class="qfibr-card qfibr-pro-teaser-card">
			<h2 class="qfibr-card-title">
				<span class="qfibr-card-title-icon"><span class="dashicons dashicons-star-filled"></span></span>
				<?php esc_html_e( 'Pro features', 'qaiyo-featured-image-bulk-replacer' ); ?>
				<span class="qfibr-pro-pill">PRO</span>
			</h2>
			<div class="qfibr-pro-grid">
				<?php foreach ( $qfibr_features as $qfibr_f ) : ?>
					<a class="qfibr-pro-locked" href="<?php echo esc_url( $qfibr_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="qfibr-pro-badge">PRO</span>
						<span class="qfibr-pro-preview">
							<span class="dashicons <?php echo esc_attr( $qfibr_f['icon'] ); ?> qfibr-pro-preview-icon"></span>
							<span class="qfibr-pro-lock-overlay"><?php echo wp_kses( $qfibr_lock, Qfibr_Kses::svg() ); ?></span>
						</span>
						<h3 class="qfibr-pro-title"><?php echo esc_html( $qfibr_f['title'] ); ?></h3>
						<p class="qfibr-pro-desc"><?php echo esc_html( $qfibr_f['desc'] ); ?></p>
						<span class="qfibr-pro-unlock"><?php echo esc_html( $qfibr_unlock ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
			<div class="qfibr-pro-cta">
				<strong><?php esc_html_e( 'Qaiyo Featured Image Bulk Replacer Pro', 'qaiyo-featured-image-bulk-replacer' ); ?></strong>
				<a class="button button-primary" href="<?php echo esc_url( $qfibr_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Get Pro', 'qaiyo-featured-image-bulk-replacer' ); ?>
				</a>
			</div>
		</div>
