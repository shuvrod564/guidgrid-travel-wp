<?php
/**
 * Single tour: gallery, facts, booking widget, content, reviews, related.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$tour_id = get_the_ID();
	tg_tour_script_data( $tour_id );
	$rating    = tg_get_tour_rating( $tour_id );
	$in_wish   = is_user_logged_in() && TG_Wishlist::has( get_current_user_id(), $tour_id );
	$faq       = (array) tg_get_meta( $tour_id, '_tg_faq', array() );
	$bring     = tg_mb_lines( $tour_id, '_tg_what_to_bring' );
	$important = (string) tg_get_meta( $tour_id, '_tg_important_info', '' );
	$policy    = (string) tg_get_meta( $tour_id, '_tg_cancellation_policy', '' );
	?>
	<div class="tg-container">
		<?php tg_breadcrumbs(); ?>
		<div class="tg-tour-title-row">
			<div>
				<h1><?php the_title(); ?></h1>
				<p class="tg-tour-subtitle"><?php echo esc_html( wp_trim_words( (string) get_the_excerpt( $tour_id ), 22 ) ); ?></p>
				<div class="tg-tour-card-meta" style="display:inline-flex;gap:16px;">
					<?php if ( $rating['count'] > 0 ) : ?>
						<span><?php echo tg_star_html( $rating['avg'], $rating['count'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php endif; ?>
					<?php $dest_name = tg_tour_destination_name( $tour_id ); ?>
					<?php if ( $dest_name ) : ?>
						<span class="tg-meta-item"><?php echo tg_svg( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $dest_name ); ?></span>
					<?php endif; ?>
					<?php $duration = tg_tour_duration_text( $tour_id ); ?>
					<?php if ( $duration ) : ?>
						<span class="tg-meta-item"><?php echo tg_svg( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $duration ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<div class="tg-tour-title-actions">
				<button type="button" class="tg-btn tg-btn--ghost tg-btn--sm <?php echo $in_wish ? 'is-active' : ''; ?>" data-tg-wishlist-single data-tour="<?php echo esc_attr( (string) $tour_id ); ?>">
					<?php echo tg_svg( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $in_wish ? esc_html__( 'Saved', 'guidegrid-travel' ) : esc_html__( 'Save', 'guidegrid-travel' ); ?>
				</button>
				<a class="tg-btn tg-btn--ghost tg-btn--sm" href="<?php echo esc_url( add_query_arg( 'wp-share', '', wp_get_referer() ? wp_get_referer() : get_permalink() ) ); ?>" onclick="navigator.clipboard.writeText(window.location.href);return false;"><?php echo tg_svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Copy link', 'guidegrid-travel' ); ?></a>
			</div>
		</div>

		<?php tg_tour_gallery( $tour_id ); ?>
		<?php tg_tour_facts( $tour_id ); ?>

		<div class="tg-tour-layout">
			<div class="tg-tour-main">
				<!-- Description -->
				<?php if ( get_the_content() ) : ?>
					<section class="tg-tour-section">
						<h2><?php esc_html_e( 'About This Tour', 'guidegrid-travel' ); ?></h2>
						<div class="tg-entry-content"><?php the_content(); ?></div>
					</section>
				<?php endif; ?>

				<!-- Highlights -->
				<?php $highlights = tg_mb_lines( $tour_id, '_tg_highlights' ); ?>
				<?php if ( $highlights ) : ?>
					<section class="tg-tour-section">
						<h2><?php esc_html_e( 'Highlights', 'guidegrid-travel' ); ?></h2>
						<ul class="tg-highlights">
							<?php foreach ( $highlights as $h ) : ?>
								<li><?php echo tg_svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $h ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<!-- Itinerary -->
				<?php $itinerary = (array) tg_get_meta( $tour_id, '_tg_itinerary', array() ); ?>
				<?php if ( $itinerary ) : ?>
					<section class="tg-tour-section">
						<h2><?php esc_html_e( 'Itinerary', 'guidegrid-travel' ); ?></h2>
						<?php tg_tour_itinerary( $tour_id ); ?>
					</section>
				<?php endif; ?>

				<!-- Included / excluded -->
				<section class="tg-tour-section">
					<h2><?php esc_html_e( 'What is Included', 'guidegrid-travel' ); ?></h2>
					<?php tg_tour_included_excluded( $tour_id ); ?>
				</section>

				<!-- Bring + important -->
				<?php if ( $bring || $important ) : ?>
					<section class="tg-tour-section">
						<h2><?php esc_html_e( 'Good to Know', 'guidegrid-travel' ); ?></h2>
						<div class="tg-two-col-lists">
							<?php if ( $bring ) : ?>
								<div class="tg-list-card">
									<h3><?php esc_html_e( 'What to Bring', 'guidegrid-travel' ); ?></h3>
									<ul>
										<?php foreach ( $bring as $item ) : ?>
											<li><?php echo esc_html( $item ); ?></li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>
							<?php if ( $important ) : ?>
								<div class="tg-list-card">
									<h3><?php esc_html_e( 'Important Information', 'guidegrid-travel' ); ?></h3>
									<p style="white-space:pre-line;"><?php echo esc_html( $important ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</section>
				<?php endif; ?>

				<!-- Cancellation -->
				<?php if ( $policy ) : ?>
					<section class="tg-tour-section">
						<h2><?php esc_html_e( 'Cancellation Policy', 'guidegrid-travel' ); ?></h2>
						<div class="tg-cancellation-box"><?php echo esc_html( $policy ); ?></div>
					</section>
				<?php endif; ?>

				<!-- Map -->
				<section class="tg-tour-section">
					<h2><?php esc_html_e( 'Location', 'guidegrid-travel' ); ?></h2>
					<?php tg_tour_map( $tour_id ); ?>
				</section>

				<!-- FAQ -->
				<?php if ( $faq ) : ?>
					<section class="tg-tour-section">
						<h2><?php esc_html_e( 'Frequently Asked Questions', 'guidegrid-travel' ); ?></h2>
						<div class="tg-itinerary">
							<?php foreach ( $faq as $item ) : ?>
								<details class="tg-itinerary-item">
									<summary>
										<h3><?php echo esc_html( $item['question'] ); ?></h3>
										<span class="tg-itinerary-chevron"><?php echo tg_svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									</summary>
									<div class="tg-itinerary-body" style="padding-inline-start:20px;">
										<p><?php echo esc_html( $item['answer'] ); ?></p>
									</div>
								</details>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<!-- Reviews -->
				<section class="tg-tour-section">
					<h2><?php esc_html_e( 'Reviews', 'guidegrid-travel' ); ?></h2>
					<?php tg_tour_reviews( $tour_id ); ?>
				</section>
			</div>

			<!-- Booking widget sidebar -->
			<aside class="tg-sidebar">
				<?php get_template_part( 'template-parts/tour/booking-widget' ); ?>
			</aside>
		</div>

		<!-- Related -->
		<section class="tg-section" style="padding-bottom:0;">
			<?php
			tg_section_heading( __( 'You Might Also Like', 'guidegrid-travel' ), '', 'center', __( 'Related Tours', 'guidegrid-travel' ) );
			tg_related_tours( $tour_id, 3 );
			?>
		</section>
	</div>

	<!-- Mobile sticky CTA -->
	<div class="tg-mobile-cta">
		<span class="tg-mc-price">
			<small><?php esc_html_e( 'From', 'guidegrid-travel' ); ?></small>
			<strong><?php echo esc_html( tg_tour_start_price( $tour_id ) ); ?></strong>
		</span>
		<a class="tg-btn tg-btn--primary" href="#tg-booking-widget"><?php esc_html_e( 'Book Now', 'guidegrid-travel' ); ?></a>
	</div>
	<?php
endwhile;

get_footer();
