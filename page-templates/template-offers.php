<?php
/**
 * Template Name: Special Offers
 *
 * Lists tours with an active price reduction.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();

$offers = new WP_Query(
	array(
		'post_type'      => 'tour',
		'posts_per_page' => 12,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'key'     => '_tg_previous_price',
				'value'   => 0,
				'compare' => '>',
			),
		),
	)
);
?>
<section class="tg-section" style="padding-top:24px;">
	<div class="tg-container">
		<?php tg_breadcrumbs(); ?>
		<header class="tg-page-head">
			<h1><?php esc_html_e( 'Special Offers', 'guidegrid-travel' ); ?></h1>
			<p class="tg-lead"><?php esc_html_e( 'Limited-time discounts on selected tours. Prices include all listed inclusions.', 'guidegrid-travel' ); ?></p>
		</header>

		<?php if ( $offers->have_posts() ) : ?>
			<div class="tg-offer-grid">
				<?php
				while ( $offers->have_posts() ) :
					$offers->the_post();
					$oid   = get_the_ID();
					$price = tg_tour_price_info( $oid );
					if ( $price['previous'] <= $price['base'] ) {
						continue;
					}
					$pct   = (int) round( ( 1 - $price['base'] / $price['previous'] ) * 100 );
					$valid = tg_get_next_available_date( $oid );
					?>
					<a class="tg-offer" href="<?php echo esc_url( get_permalink( $oid ) ); ?>">
						<span class="tg-offer-media">
							<img src="<?php echo esc_url( tg_tour_image_url( $oid ) ); ?>" alt="<?php echo esc_attr( get_the_title( $oid ) ); ?>" loading="lazy" />
							<span class="tg-offer-pct">−<?php echo esc_html( (string) $pct ); ?>%</span>
						</span>
						<span class="tg-offer-body">
							<h3><?php echo esc_html( get_the_title( $oid ) ); ?></h3>
							<span class="tg-offer-valid">
								<?php
								printf(
									/* translators: %s: date */
									esc_html__( 'Next departure: %s', 'guidegrid-travel' ),
									esc_html( $valid ? tg_format_date( $valid ) : __( 'On request', 'guidegrid-travel' ) )
								);
								?>
							</span>
							<span class="tg-offer-price">
								<span class="now"><?php echo esc_html( tg_format_price( $price['base'], $price['currency'] ) ); ?></span>
								<span class="was"><?php echo esc_html( tg_format_price( $price['previous'], $price['currency'] ) ); ?></span>
							</span>
						</span>
					</a>
				<?php endwhile;
				wp_reset_postdata();
				?>
			</div>
		<?php else : ?>
			<?php
			tg_empty_state(
				'gift',
				__( 'No active offers right now', 'guidegrid-travel' ),
				__( 'Check back soon — or browse all tours, many include seasonal pricing.', 'guidegrid-travel' ),
				get_post_type_archive_link( 'tour' ),
				__( 'Browse tours', 'guidegrid-travel' )
			);
			?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
