<?php
/**
 * Admin partial: result of a broken featured image scan.
 *
 * @package qaiyo-featured-image-bulk-replacer
 */

/**
 * @var array{total: int, sample: list<array{id: int, title: string, edit: string}>} $qfibr_summary Scan result.
 */
defined( 'ABSPATH' ) || exit;
?>
<?php if ( 0 === $qfibr_summary['total'] ) : ?>
	<p class="qfibr-summary" data-total="0"><?php esc_html_e( 'No broken featured images found.', 'qaiyo-featured-image-bulk-replacer' ); ?></p>
<?php else : ?>
	<p class="qfibr-summary" data-total="<?php echo (int) $qfibr_summary['total']; ?>">
		<?php
		echo esc_html( sprintf(
			/* translators: %d: number of posts with a broken featured image. */
			__( 'Posts with a broken featured image: %d.', 'qaiyo-featured-image-bulk-replacer' ),
			$qfibr_summary['total']
		) );
		?>
	</p>
	<ul class="qfibr-orphan-list">
		<?php foreach ( $qfibr_summary['sample'] as $qfibr_row ) : ?>
			<li>
				<?php if ( '' !== $qfibr_row['edit'] ) : ?>
					<a href="<?php echo esc_url( $qfibr_row['edit'] ); ?>"><?php echo esc_html( $qfibr_row['title'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $qfibr_row['title'] ); ?>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
