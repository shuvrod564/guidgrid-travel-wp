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
	$rating    = tg_get_tour_rating( $tour_id );
	$in_wish   = is_user_logged_in() && TG_Wishlist::has( get_current_user_id(), $tour_id );
	$faq       = (array) tg_get_meta( $tour_id, '_tg_faq', array() );
	$bring     = tg_mb_lines( $tour_id, '_tg_what_to_bring' );
	$important = (string) tg_get_meta( $tour_id, '_tg_important_info', '' );
	$policy    = (string) tg_get_meta( $tour_id, '_tg_cancellation_policy', '' );
	?>
	<div class="tg-container" style="padding:3rem 1rem;">
		<?php tg_breadcrumbs(); ?>
		<div class="tg-tour-title-row">
			<div style="flex-grow:1;">
				<h1 style="margin-bottom:8px;"><?php the_title(); ?></h1>
				<p class="tg-tour-subtitle"><?php echo esc_html( wp_trim_words( (string) get_the_excerpt( $tour_id ), 22 ) ); ?></p>
				<div class="tg-tour-card-meta" style="display:inline-flex;gap:4px 16px;flex-direction: row;flex-wrap:wrap;">
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
					<?php // echo tg_svg( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $in_wish ? tg_svg( 'heart' ) : tg_lucide( 'heart' ); ?>
					<?php echo $in_wish ? esc_html__( 'Saved', 'guidegrid-travel' ) : esc_html__( 'Save', 'guidegrid-travel' ); ?>
				</button>
				<a class="tg-btn tg-btn--ghost tg-btn--sm" href="<?php echo esc_url( add_query_arg( 'wp-share', '', wp_get_referer() ? wp_get_referer() : get_permalink() ) ); ?>" onclick="navigator.clipboard.writeText(window.location.href);return false;"><?php echo tg_lucide( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Copy', 'guidegrid-travel' ); ?></a>
			</div>
		</div>

		<?php tg_tour_gallery( $tour_id ); ?>
		<?php tg_tour_facts( $tour_id ); ?>

		<div class="tg-tour-layout">
			<div class="tg-tour-main">
				<!-- Description -->
				<?php if ( get_the_content() ) : ?>
					<section class="tg-tour-section card section-gap">
						<h2><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--tg-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-info preview-icon"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg><?php esc_html_e( 'About This Tour', 'guidegrid-travel' ); ?></h2>
						<div class="tg-entry-content"><?php the_content(); ?></div>
					</section>
				<?php endif; ?>

				<!-- Highlights -->
				<?php $highlights = tg_mb_lines( $tour_id, '_tg_highlights' ); ?>
				<?php if ( $highlights ) : ?>
					<section class="tg-tour-section card section-gap">
						<h2><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--tg-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles preview-icon"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/><path d="M20 2v4"/><path d="M22 4h-4"/><circle cx="4" cy="20" r="2"/></svg><?php esc_html_e( 'Highlights', 'guidegrid-travel' ); ?></h2>
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
					<section class="tg-tour-section section-gap">
						<div class="tg-title-icon-row"> 
							<div class="tg-tour-section-icon">
								<?php echo tg_lucide( 'question-mark' ); ?>
							</div>
							<h2 class="section-title">
								<?php esc_html_e( 'Itinerary', 'guidegrid-travel' ); ?>
								<small style="font-size:.75rem;display:block;font-weight:500;"><?php esc_html_e( 'Discover tours perform activities', 'guidegrid-travel' ); ?></small>
							</h2>
						</div> 
						<?php tg_tour_itinerary( $tour_id ); ?>
					</section>
				<?php endif; ?>

				<!-- Included / excluded -->
				<section class="tg-tour-section card section-gap">
					<h2 style="margin-bottom:20px;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--tg-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-star-plus preview-icon"><path d="M11.013 18.582 6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.12 2.12 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.12 2.12 0 0 0 1.597-1.16l2.309-4.679a.53.53 0 0 1 .95 0l2.31 4.679a2.12 2.12 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904L20 11.5"/><path d="M15 18h6"/><path d="M18 15v6"/></svg><?php esc_html_e( 'What is Included', 'guidegrid-travel' ); ?></h2>
					<?php tg_tour_included_excluded( $tour_id ); ?>
				</section>

				<!-- Bring + important -->
				<?php if ( $bring || $important ) : ?>
					<section class="tg-tour-section card section-gap">
						<h2 style="margin-bottom:20px;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--tg-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-brain preview-icon"><path d="M12 18V5"/><path d="M15 13a4.17 4.17 0 0 1-3-4 4.17 4.17 0 0 1-3 4"/><path d="M17.598 6.5A3 3 0 1 0 12 5a3 3 0 1 0-5.598 1.5"/><path d="M17.997 5.125a4 4 0 0 1 2.526 5.77"/><path d="M18 18a4 4 0 0 0 2-7.464"/><path d="M19.967 17.483A4 4 0 1 1 12 18a4 4 0 1 1-7.967-.517"/><path d="M6 18a4 4 0 0 1-2-7.464"/><path d="M6.003 5.125a4 4 0 0 0-2.526 5.77"/></svg><?php esc_html_e( 'Good to Know', 'guidegrid-travel' ); ?></h2>
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
					<section class="tg-tour-section tg-cancellation-box section-gap ">
						<h2><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-x preview-icon"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg><?php esc_html_e( 'Cancellation Policy', 'guidegrid-travel' ); ?></h2>
						<div class="tg-cancellation-box-info"><?php echo esc_html( $policy ); ?></div>
					</section>
				<?php endif; ?>

				<!-- Map -->
				<section class="tg-tour-section card section-gap">
					<h2><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--tg-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pinned preview-icon"><path d="M18 8c0 3.613-3.869 7.429-5.393 8.795a1 1 0 01-1.214 0C9.87 15.429 6 11.613 6 8a6 6 0 0112 0"/><path d="M4.474 15h-.197a1 1 0 00-.969.753l-1.097 4.35a1.5 1.5 0 001.444 1.898L20.344 22a1.5 1.5 0 001.446-1.897l-1.098-4.35a1 1 0 00-.969-.753h-.197"/><circle cx="12" cy="8" r="2"/></svg><?php esc_html_e( 'Location', 'guidegrid-travel' ); ?></h2>
					<?php tg_tour_map( $tour_id ); ?>
				</section>

				<!-- FAQ -->
				<?php if ( $faq ) : ?>
					<section class="tg-tour-section section-gap"> 
						<div class="tg-title-icon-row"> 
							<div class="tg-tour-section-icon">
								<?php echo tg_lucide( 'question-mark' ); ?>
							</div>
							<h2 class="section-title">
								<?php esc_html_e( 'Frequently Asked Questions', 'guidegrid-travel' ); ?>
								<small style="font-size:.75rem;display:block;font-weight:500;"><?php esc_html_e( 'Everything you need to know about your stay', 'guidegrid-travel' ); ?></small>
							</h2>
						</div>
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

			</div>

			<!-- Booking widget sidebar -->
			<aside class="tg-sidebar">
				<?php get_template_part( 'template-parts/tour/booking-widget' ); ?>
			</aside>
		</div>

		<!-- Reviews -->
		<section class="tg-tour-section">
			<div class="tg-title-icon-row"> 
				<div class="tg-tour-section-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-star-check preview-icon"><path d="m19.06 12.501 2.78-2.707a.53.53 0 0 0-.294-.905l-5.166-.755a2.1 2.1 0 0 1-1.595-1.16l-2.31-4.68a.53.53 0 0 0-.95.001L9.216 6.974a2.1 2.1 0 0 1-1.597 1.16l-5.165.755a.53.53 0 0 0-.294.906l3.736 3.637a2.1 2.1 0 0 1 .611 1.879l-.88 5.139a.53.53 0 0 0 .769.56l4.617-2.428.027-.014"/><path d="m15 18 2 2 4-4"/></svg>
				</div>
				<h2 class="section-title">
					<?php esc_html_e( 'Reviews', 'guidegrid-travel' ); ?>
					<small style="font-size:.75rem;display:block;font-weight:500;"><?php esc_html_e( 'Here you can find reviews from our guests.', 'guidegrid-travel' ); ?></small>
				</h2>
			</div>

			<?php tg_tour_reviews( $tour_id ); ?>
		</section>
		 

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
