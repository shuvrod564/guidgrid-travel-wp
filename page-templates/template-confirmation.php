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

$tg_tour          = $tg_bk ? get_post( (int) $tg_bk->tour_id ) : null;
$tg_c             = $tg_bk ? TG_Bookings::customer( $tg_bk ) : array();
$tg_addons        = $tg_bk ? TG_Bookings::addons( $tg_bk ) : array();
$tg_guests        = $tg_bk ? (int) $tg_bk->adult_count + (int) $tg_bk->child_count + (int) $tg_bk->infant_count : 0;
$tg_customer_name = trim( (string) ( $tg_c['first_name'] ?? '' ) . ' ' . (string) ( $tg_c['last_name'] ?? '' ) );
$tg_customer_name = $tg_customer_name ? $tg_customer_name : (string) ( $tg_c['email'] ?? '' );
$settings         = tg_settings();
$tg_payment       = $tg_bk ? TG_Payments::latest_for_booking( (int) $tg_bk->id ) : null;
$tg_method        = $tg_payment ? (string) $tg_payment->payment_method : '';
$tg_method_label  = $tg_method ? TG_Payments::method_label( $tg_method ) : '';
$tg_payment_return = isset( $_GET['payment'] ) ? sanitize_key( wp_unslash( $_GET['payment'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

					<?php if ( 'success' === $tg_payment_return && 'paid' === $tg_bk->payment_status ) : ?>
						<div class="tg-notice tg-notice--success" style="margin-top:20px;"><strong><?php esc_html_e( 'Payment confirmed', 'guidegrid-travel' ); ?></strong> <?php echo 'test_gateway' === $tg_method ? esc_html__( 'The simulated payment was approved. No money was charged.', 'guidegrid-travel' ) : esc_html__( 'Your payment was verified and your reservation is confirmed.', 'guidegrid-travel' ); ?></div>
					<?php elseif ( 'simulate' === $tg_payment_return ) : ?>
						<div class="tg-notice tg-notice--info" style="margin-top:20px;"><strong><?php esc_html_e( 'Test payment only', 'guidegrid-travel' ); ?></strong> <?php esc_html_e( 'Choose an outcome below. This simulator never contacts a payment provider and never charges money.', 'guidegrid-travel' ); ?></div>
					<?php elseif ( 'declined' === $tg_payment_return ) : ?>
						<div class="tg-notice tg-notice--error" style="margin-top:20px;"><strong><?php esc_html_e( 'Simulated decline recorded.', 'guidegrid-travel' ); ?></strong> <?php esc_html_e( 'No charge was attempted. You can now test an approval while the reservation hold remains active.', 'guidegrid-travel' ); ?></div>
					<?php elseif ( 'processing' === $tg_payment_return && 'paid' !== $tg_bk->payment_status ) : ?>
						<div class="tg-notice tg-notice--info" style="margin-top:20px;"><strong><?php esc_html_e( 'Payment is being verified', 'guidegrid-travel' ); ?></strong> <?php esc_html_e( 'Stripe is confirming the payment securely. Refresh this page shortly if the status still shows pending.', 'guidegrid-travel' ); ?></div>
					<?php elseif ( in_array( $tg_payment_return, array( 'failed', 'cancelled', 'invalid', 'unavailable' ), true ) ) : ?>
						<div class="tg-notice tg-notice--error" style="margin-top:20px;"><strong><?php esc_html_e( 'Online payment was not completed.', 'guidegrid-travel' ); ?></strong> <?php esc_html_e( 'Your booking has not been marked paid. Please retry or contact our team and quote the booking number above.', 'guidegrid-travel' ); ?></div>
					<?php endif; ?>

					<?php if ( 'awaiting_payment' === $tg_bk->booking_status || 'pending' === $tg_bk->booking_status ) : ?>
						<div class="tg-notice tg-notice--warning" style="margin-top:20px;">
							<strong><?php esc_html_e( 'Payment pending', 'guidegrid-travel' ); ?></strong>
							<?php if ( $tg_method_label ) : ?><p style="margin:6px 0 0;"><strong><?php esc_html_e( 'Selected method:', 'guidegrid-travel' ); ?></strong> <?php echo esc_html( $tg_method_label ); ?></p><?php endif; ?>
							<?php if ( 'bank' === $tg_method ) : ?>
								<p style="margin:6px 0 0;"><?php echo esc_html( TG_Payments::manual_instructions( 'bank' ) ); ?></p>
							<?php else : ?>
								<p style="margin:6px 0 0;">
									<?php
									printf(
										/* translators: %d: hold minutes */
										esc_html__( 'Your seats are held for %d minutes while payment is completed.', 'guidegrid-travel' ),
										TG_Payments::hold_minutes_for_method( $tg_method )
									);
									?>
								</p>
							<?php endif; ?>
							<?php if ( 'test_gateway' === $tg_method && isset( TG_Payments::adapters()[ $tg_method ] ) && is_user_logged_in() ) : ?>
								<form method="post" class="tg-no-print" style="margin-top:12px;">
									<?php wp_nonce_field( 'tg_test_payment_' . $tg_bk->booking_number, 'tg_payment_nonce' ); ?>
									<input type="hidden" name="tg_payment_action" value="test_decision" />
									<input type="hidden" name="booking_number" value="<?php echo esc_attr( $tg_bk->booking_number ); ?>" />
									<p><strong><?php esc_html_e( 'Simulator outcome:', 'guidegrid-travel' ); ?></strong> <?php esc_html_e( 'Neither choice charges money.', 'guidegrid-travel' ); ?></p>
									<button type="submit" name="test_decision" value="approve" class="tg-btn tg-btn--primary tg-btn--sm"><?php esc_html_e( 'Simulate approval', 'guidegrid-travel' ); ?></button>
									<button type="submit" name="test_decision" value="decline" class="tg-btn tg-btn--secondary tg-btn--sm"><?php esc_html_e( 'Simulate decline', 'guidegrid-travel' ); ?></button>
								</form>
							<?php elseif ( isset( TG_Payments::adapters()[ $tg_method ] ) && is_user_logged_in() ) : ?>
								<form method="post" class="tg-no-print" style="margin-top:12px;">
									<?php wp_nonce_field( 'tg_restart_payment_' . $tg_bk->booking_number, 'tg_payment_nonce' ); ?>
									<input type="hidden" name="tg_payment_action" value="restart" />
									<input type="hidden" name="booking_number" value="<?php echo esc_attr( $tg_bk->booking_number ); ?>" />
									<button type="submit" class="tg-btn tg-btn--primary tg-btn--sm"><?php esc_html_e( 'Continue secure payment', 'guidegrid-travel' ); ?></button>
								</form>
							<?php endif; ?>
						</div>
					<?php elseif ( 'confirmed' === $tg_bk->booking_status && 'unpaid' === $tg_bk->payment_status && TG_Payments::is_deferred_manual( $tg_method ) ) : ?>
						<div class="tg-notice tg-notice--info" style="margin-top:20px;">
							<strong><?php esc_html_e( 'Payment arranged', 'guidegrid-travel' ); ?></strong>
							<?php if ( $tg_method_label ) : ?><p style="margin:6px 0 0;"><strong><?php esc_html_e( 'Selected method:', 'guidegrid-travel' ); ?></strong> <?php echo esc_html( $tg_method_label ); ?></p><?php endif; ?>
							<p style="margin:6px 0 0;"><?php echo esc_html( TG_Payments::manual_instructions( $tg_method ) ); ?></p>
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
							<strong><?php echo esc_html( $tg_customer_name ); ?></strong>
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
