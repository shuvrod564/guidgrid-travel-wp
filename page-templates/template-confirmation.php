<?php
/**
 * Template Name: Booking Confirmation
 *
 * Shows a booking by its (unguessable) number: details, price,
 * payment instructions and print/download.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();

$tg_num = isset( $_GET['booking'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['booking'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$tg_bk  = $tg_num ? TG_Bookings::by_number( $tg_num ) : null;

if ( ! $tg_bk ) {
	// Legacy/short IDs from internal links.
	$tg_bk = (int) $tg_num ? TG_Bookings::get( (int) $tg_num ) : null;
}

$tg_tour      = $tg_bk ? get_post( (int) $tg_bk->tour_id ) : null;
$tg_c         = $tg_bk ? TG_Bookings::customer( $tg_bk ) : array();
$tg_addons    = $tg_bk ? TG_Bookings::addons( $tg_bk ) : array();
$tg_guests    = $tg_bk ? (int) $tg_bk->adult_count + (int) $tg_bk->child_count + (int) $tg_bk->infant_count : 0;
$settings     = tg_settings();
?>

<section class="tg-section" style="padding-top:24px;">
	<div class="tg-container">
		<?php tg_breadcrumbs(); ?>

		<?php if ( ! $tg_bk ) : ?>
			<div class="tg-confirmation">
				<?php
				tg_empty_state(
					'ticket',
					__( 'Booking not found', 'guidegrid-travel' ),
					__( 'Check the booking number in your confirmation email, or use the booking lookup page.', 'guidegrid-travel' ),
					tg_booking_lookup_url(),
					__( 'Look up a booking', 'guidegrid-travel' )
				);
				?>
			</div>
		<?php else : ?>
			<div class="tg-confirmation">
				<div class="tg-confirmation-box tg-print-box">
					<span class="tg-confirm-icon"><?php echo tg_svg( 'check-circle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div class="tg-text-center">
						<h1 style="font-size:1.8rem;"><?php esc_html_e( 'Thank you — your booking is received', 'guidegrid-travel' ); ?></h1>
						<p><?php esc_html_e( 'A confirmation email with all the details is on its way.', 'guidegrid-travel' ); ?></p>
						<span class="tg-confirm-number"><?php echo esc_html( $tg_bk->booking_number ); ?></span>
						<p style="margin-bottom:0;">
							<?php echo tg_status_badge( $tg_bk->booking_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo tg_status_badge( $tg_bk->payment_status, 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</p>
					</div>

					<?php if ( 'awaiting_payment' === $tg_bk->booking_status || 'pending' === $tg_bk->booking_status ) : ?>
						<div class="tg-notice tg-notice--warning" style="margin-top:20px;">
							<strong><?php esc_html_e( 'Payment pending', 'guidegrid-travel' ); ?></strong>
							<p style="margin:6px 0 0;">
								<?php
								printf(
									/* translators: %d: hold minutes */
									esc_html__( 'Your seats are held for %d minutes. Choose a payment method to confirm the booking:', 'guidegrid-travel' ),
									(int) $settings['hold_minutes']
								);
								?>
							</p>
							<ul style="margin:8px 0 0;">
								<?php foreach ( TG_Payments::manual_methods() as $tg_mk => $tg_ml ) : ?>
									<li><?php echo esc_html( $tg_ml ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<div class="tg-confirm-grid">
						<div class="tg-confirm-item">
							<small><?php esc_html_e( 'Tour', 'guidegrid-travel' ); ?></small>
							<strong><?php echo $tg_tour ? esc_html( get_the_title( $tg_tour ) ) : esc_html( '#' . (int) $tg_bk->tour_id ); ?></strong>
						</div>
						<div class="tg-confirm-item">
							<small><?php esc_html_e( 'Travel date', 'guidegrid-travel' ); ?></small>
							<strong><?php echo esc_html( tg_format_date( $tg_bk->booking_date ) ); ?><?php echo $tg_bk->tour_end_date ? ' → ' . esc_html( tg_format_date( $tg_bk->tour_end_date ) ) : ''; ?></strong>
						</div>
						<div class="tg-confirm-item">
							<small><?php esc_html_e( 'Guests', 'guidegrid-travel' ); ?></small>
							<strong><?php echo esc_html( (string) $tg_guests ); ?></strong>
						</div>
						<div class="tg-confirm-item">
							<small><?php esc_html_e( 'Booked by', 'guidegrid-travel' ); ?></small>
							<strong><?php echo esc_html( ( $tg_c['first_name'] . ' ' . $tg_c['last_name'] ) ? ( $tg_c['first_name'] . ' ' . $tg_c['last_name'] ) : ( $tg_c['email'] ?? '' ); ?></strong>
						</div>
					</div>

					<table class="tg-table">
						<thead>
							<tr><th><?php esc_html_e( 'Item', 'guidegrid-travel' ); ?></th><th><?php esc_html_e( 'Amount', 'guidegrid-travel' ); ?></th></tr>
						</thead>
						<tbody>
							<tr><td><?php echo esc_html( sprintf( /* translators: %s: price */ __( '%d × Adult (%s)', 'guidegrid-travel' ), (int) $tg_bk->adult_count, tg_format_price( (float) $tg_bk->adult_unit_price, $tg_bk->currency ) ) ); ?></td><td><?php echo esc_html( tg_format_price( round( (float) $tg_bk->adult_unit_price * (int) $tg_bk->adult_count, 2 ), $tg_bk->currency ) ); ?></td></tr>
							<?php if ( (int) $tg_bk->child_count ) : ?>
								<tr><td><?php echo esc_html( sprintf( /* translators: %s: price */ __( '%d × Child (%s)', 'guidegrid-travel' ), (int) $tg_bk->child_count, tg_format_price( (float) $tg_bk->child_unit_price, $tg_bk->currency ) ) ); ?></td><td><?php echo esc_html( tg_format_price( round( (float) $tg_bk->child_unit_price * (int) $tg_bk->child_count, 2 ), $tg_bk->currency ) ); ?></td></tr>
							<?php endif; ?>
							<?php if ( (int) $tg_bk->infant_count ) : ?>
								<tr><td><?php echo esc_html( sprintf( /* translators: %s: price */ __( '%d × Infant (%s)', 'guidegrid-travel' ), (int) $tg_bk->infant_count, tg_format_price( (float) $tg_bk->infant_unit_price, $tg_bk->currency ) ) ); ?></td><td><?php echo esc_html( tg_format_price( round( (float) $tg_bk->infant_unit_price * (int) $tg_bk->infant_count, 2 ), $tg_bk->currency ) ); ?></td></tr>
							<?php endif; ?>
							<?php foreach ( $tg_addons as $tg_line ) : ?>
								<tr><td><?php echo esc_html( $tg_line['name'] ); ?> × <?php echo esc_html( (string) $tg_line['qty'] ); ?></td><td><?php echo esc_html( tg_format_price( (float) $tg_line['amount'], $tg_bk->currency ) ); ?></td></tr>
							<?php endforeach; ?>
							<?php if ( (float) $tg_bk->discount > 0 ) : ?>
								<tr><td><?php esc_html_e( 'Discount', 'guidegrid-travel' ); ?><?php echo $tg_bk->coupon_code ? ' (' . esc_html( $tg_bk->coupon_code ) . ')' : ''; ?></td><td>−<?php echo esc_html( tg_format_price( (float) $tg_bk->discount, $tg_bk->currency ) ); ?></td></tr>
							<?php endif; ?>
							<?php if ( (float) $tg_bk->tax > 0 ) : ?>
								<tr><td><?php esc_html_e( 'Tax', 'guidegrid-travel' ); ?></td><td><?php echo esc_html( tg_format_price( (float) $tg_bk->tax, $tg_bk->currency ) ); ?></td></tr>
							<?php endif; ?>
							<?php if ( (float) $tg_bk->service_fee > 0 ) : ?>
								<tr><td><?php esc_html_e( 'Service fee', 'guidegrid-travel' ); ?></td><td><?php echo esc_html( tg_format_price( (float) $tg_bk->service_fee, $tg_bk->currency ) ); ?></td></tr>
							<?php endif; ?>
							<tr style="font-weight:800;"><td><?php esc_html_e( 'Total', 'guidegrid-travel' ); ?></td><td><?php echo esc_html( tg_format_price( (float) $tg_bk->total, $tg_bk->currency ) ); ?></td></tr>
						</tbody>
					</table>

					<?php if ( $tg_tour ) : ?>
						<?php $tg_policy = (string) tg_get_meta( $tg_tour->ID, '_tg_cancellation_policy', '' ); ?>
						<?php if ( $tg_policy ) : ?>
							<div class="tg-cancellation-box" style="margin-top:16px;">
								<strong><?php esc_html_e( 'Cancellation policy:', 'guidegrid-travel' ); ?></strong>
								<?php echo esc_html( $tg_policy ); ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>

					<div class="tg-confirm-actions tg-no-print">
						<button type="button" class="tg-btn tg-btn--secondary" onclick="window.print()"><?php echo tg_svg( 'print' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Print / Save PDF', 'guidegrid-travel' ); ?></button>
						<a class="tg-btn tg-btn--ghost" href="<?php echo esc_url( tg_booking_lookup_url() ); ?>"><?php esc_html_e( 'Find booking', 'guidegrid-travel' ); ?></a>
						<a class="tg-btn tg-btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'tour' ) ? get_post_type_archive_link( 'tour' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Continue exploring', 'guidegrid-travel' ); ?></a>
						<?php if ( is_user_logged_in() ) : ?>
							<a class="tg-btn tg-btn--ghost" href="<?php echo esc_url( tg_account_url( 'bookings' ) ); ?>"><?php esc_html_e( 'My bookings', 'guidegrid-travel' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
