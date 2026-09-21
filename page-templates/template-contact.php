<?php
/**
 * Template Name: Contact
 *
 * Contact form + info cards + map.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();

$settings = tg_settings();
?>
<section class="tg-section" style="padding-top:24px;">
	<div class="tg-container">
		<?php tg_breadcrumbs(); ?>
		<header class="tg-page-head">
			<h1><?php the_title(); ?></h1>
			<p class="tg-lead"><?php esc_html_e( 'Questions about a tour, a custom trip or an existing booking? We usually reply within 24 hours.', 'guidegrid-travel' ); ?></p>
		</header>

		<div class="tg-contact-layout">
			<div>
				<div class="tg-contact-info-cards">
					<div class="tg-contact-card">
						<span class="tg-fact-icon"><?php echo tg_svg( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div><strong><?php esc_html_e( 'Office', 'guidegrid-travel' ); ?></strong><span><?php echo esc_html( $settings['contact_address'] ? $settings['contact_address'] : get_bloginfo( 'name' ) ); ?></span></div>
					</div>
					<div class="tg-contact-card">
						<span class="tg-fact-icon"><?php echo tg_svg( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div><strong><?php esc_html_e( 'Phone', 'guidegrid-travel' ); ?></strong><span><?php echo esc_html( $settings['contact_phone'] ? $settings['contact_phone'] : '—' ); ?></span></div>
					</div>
					<div class="tg-contact-card">
						<span class="tg-fact-icon"><?php echo tg_svg( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div><strong><?php esc_html_e( 'Email', 'guidegrid-travel' ); ?></strong><span><?php echo esc_html( $settings['contact_email'] ? $settings['contact_email'] : get_option( 'admin_email' ) ); ?></span></div>
					</div>
					<div class="tg-contact-card">
						<span class="tg-fact-icon"><?php echo tg_svg( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div><strong><?php esc_html_e( 'Hours', 'guidegrid-travel' ); ?></strong><span><?php echo esc_html( $settings['contact_hours'] ? $settings['contact_hours'] : __( 'Mon–Sat, 9:00–18:00', 'guidegrid-travel' ) ); ?></span></div>
					</div>
				</div>
				<?php get_template_part( 'template-parts/components/contact-form' ); ?>
			</div>

			<aside>
				<div class="tg-detail-card">
					<h3><?php esc_html_e( 'Prefer to plan with a human?', 'guidegrid-travel' ); ?></h3>
					<p><?php esc_html_e( 'Send a custom trip enquiry and our team will build an itinerary and quote for you — free of charge.', 'guidegrid-travel' ); ?></p>
					<?php echo do_shortcode( '[tg_enquiry_form]' ); ?>
				</div>
			</aside>
		</div>
	</div>
</section>
<?php
get_footer();
