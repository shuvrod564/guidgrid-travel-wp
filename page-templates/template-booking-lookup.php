<?php
/**
 * Template Name: Booking Lookup
 *
 * Find a booking with number + email (rate-limited; the two-factor
 * requirement keeps bookings private).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="tg-section" style="padding-top:24px;">
	<div class="tg-container">
		<div class="tg-lookup-box">
			<?php tg_breadcrumbs(); ?>
			<div class="tg-confirmation-box">
				<span class="tg-confirm-icon"><?php echo tg_svg( 'ticket' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div class="tg-text-center">
					<h1 style="font-size:1.7rem;"><?php esc_html_e( 'Find My Booking', 'guidegrid-travel' ); ?></h1>
					<p><?php esc_html_e( 'Enter the booking number from your confirmation email and the email address used when booking.', 'guidegrid-travel' ); ?></p>
				</div>

				<form method="post" data-tg-lookup-form>
					<div class="tg-form-row">
						<label class="tg-label" for="tg-lk-number"><?php esc_html_e( 'Booking number', 'guidegrid-travel' ); ?> *</label>
						<input type="text" id="tg-lk-number" name="number" class="tg-input" placeholder="TG-20260921-XXXXX" required />
						<span class="tg-field-error" role="alert"></span>
					</div>
					<div class="tg-form-row">
						<label class="tg-label" for="tg-lk-email"><?php esc_html_e( 'Email address', 'guidegrid-travel' ); ?> *</label>
						<input type="email" id="tg-lk-email" name="email" class="tg-input" required />
						<span class="tg-field-error" role="alert"></span>
					</div>
					<button type="submit" class="tg-btn tg-btn--primary tg-btn--block"><?php esc_html_e( 'Find Booking', 'guidegrid-travel' ); ?><span class="tg-spinner" aria-hidden="true"></span></button>
				</form>

				<div class="tg-lookup-result" data-tg-lookup-result aria-live="polite"></div>
			</div>
		</div>
	</div>
</section>
<?php
get_footer();
