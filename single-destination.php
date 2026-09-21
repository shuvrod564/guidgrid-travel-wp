<?php
/**
 * Single destination.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$dest_id  = get_the_ID();
	$country  = tg_get_meta( $dest_id, '_tg_country', '' );
	$best     = tg_get_meta( $dest_id, '_tg_best_time', '' );
	$temp     = tg_get_meta( $dest_id, '_tg_avg_temp', '' );
	$currency = tg_get_meta( $dest_id, '_tg_currency', '' );
	$language = tg_get_meta( $dest_id, '_tg_language', '' );
	$timezone = tg_get_meta( $dest_id, '_tg_timezone', '' );
	$travel   = tg_get_meta( $dest_id, '_tg_travel_info', '' );
	$safety   = tg_get_meta( $dest_id, '_tg_safety_info', '' );

	$tours = new WP_Query(
		array(
			'post_type'      => 'tour',
			'posts_per_page' => 6,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'   => '_tg_destination_id',
					'value' => $dest_id,
				),
			),
		)
	);
	?>
	<section class="tg-section" style="padding-top:24px;">
		<div class="tg-container">
			<?php tg_breadcrumbs(); ?>
			<header class="tg-page-head">
				<h1><?php the_title(); ?></h1>
				<?php if ( $country ) : ?>
					<p class="tg-lead"><?php echo tg_svg( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $country ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<p style="margin:0 0 28px;border-radius:var(--tg-radius);overflow:hidden;"><img src="<?php echo esc_url( get_the_post_thumbnail_url( null, 'tg-hero' ) ); ?>" alt="<?php the_title_attribute(); ?>" /></p>
			<?php else : ?>
				<p style="margin:0 0 28px;"><img src="<?php echo esc_url( tg_get_meta( $dest_id, '_tg_demo_image', tg_demo_img( 'tg-dest-' . $dest_id, 1600, 600 ) ) ); ?>" alt="<?php the_title_attribute(); ?>" /></p>
			<?php endif; ?>

			<?php if ( $best || $temp || $currency || $language ) : ?>
				<div class="tg-dest-facts">
					<?php if ( $best ) : ?>
						<div class="tg-fact"><span class="tg-fact-icon"><?php echo tg_svg( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><div><strong><?php echo esc_html( $best ); ?></strong><span><?php esc_html_e( 'Best time to visit', 'guidegrid-travel' ); ?></span></div></div>
					<?php endif; ?>
					<?php if ( $temp ) : ?>
						<div class="tg-fact"><span class="tg-fact-icon"><?php echo tg_svg( 'temperature' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><div><strong><?php echo esc_html( $temp ); ?></strong><span><?php esc_html_e( 'Average temperature', 'guidegrid-travel' ); ?></span></div></div>
					<?php endif; ?>
					<?php if ( $currency ) : ?>
						<div class="tg-fact"><span class="tg-fact-icon"><?php echo tg_svg( 'currency' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><div><strong><?php echo esc_html( $currency ); ?></strong><span><?php esc_html_e( 'Local currency', 'guidegrid-travel' ); ?></span></div></div>
					<?php endif; ?>
					<?php if ( $language ) : ?>
						<div class="tg-fact"><span class="tg-fact-icon"><?php echo tg_svg( 'language' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><div><strong><?php echo esc_html( $language ); ?></strong><span><?php esc_html_e( 'Language', 'guidegrid-travel' ); ?></span></div></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="tg-entry-content" style="max-width:820px;">
				<?php the_content(); ?>
			</div>

			<?php if ( $travel ) : ?>
				<section class="tg-tour-section">
					<h2><?php esc_html_e( 'Travel Information', 'guidegrid-travel' ); ?></h2>
					<div class="tg-entry-content"><?php echo wp_kses_post( wpautop( esc_html( $travel ) ) ); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( $safety ) : ?>
				<section class="tg-tour-section">
					<h2><?php esc_html_e( 'Safety & Health', 'guidegrid-travel' ); ?></h2>
					<div class="tg-entry-content"><?php echo wp_kses_post( wpautop( esc_html( $safety ) ) ); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( $timezone ) : ?>
				<p class="tg-map-note"><?php echo tg_svg( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( sprintf( /* translators: %s: timezone */ __( 'Time zone: %s', 'guidegrid-travel' ), $timezone ) ); ?></p>
			<?php endif; ?>

			<?php if ( $tours->have_posts() ) : ?>
				<section class="tg-section" style="padding-top:40px;">
					<?php
					tg_section_heading(
						sprintf( /* translators: %s: destination name */ __( 'Tours in %s', 'guidegrid-travel' ), get_the_title() ),
						'',
						'left'
					);
					?>
					<div class="tg-card-grid">
						<?php
						while ( $tours->have_posts() ) :
							$tours->the_post();
							tg_tour_card( get_the_ID() );
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				</section>
			<?php else : ?>
				<section class="tg-tour-section">
					<?php
					tg_empty_state(
						'compass',
						__( 'No tours here yet', 'guidegrid-travel' ),
						__( 'Tours for this destination will appear once they are published.', 'guidegrid-travel' ),
						get_post_type_archive_link( 'tour' ),
						__( 'Browse all tours', 'guidegrid-travel' )
					);
					?>
				</section>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
