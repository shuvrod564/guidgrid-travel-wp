<?php
/**
 * Template Name: Booking (Checkout)
 *
 * The checkout page. The trip summary + form read selections from the
 * query string (set by the tour page "Book Now") and re-validate
 * everything server-side when the booking is created.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();

$tg_checkout_tour_id = isset( $_GET['tour_id'] ) ? absint( $_GET['tour_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$tg_checkout_tour    = $tg_checkout_tour_id ? get_post( $tg_checkout_tour_id ) : null;

if ( $tg_checkout_tour && 'tour' !== $tg_checkout_tour->post_type || 'publish' !== ( $tg_checkout_tour ? $tg_checkout_tour->post_status : '' ) ) {
	$tg_checkout_tour      = null;
	$tg_checkout_tour_id   = 0;
}

$tg_q = static function ( $key, $default = '' ) {
	$value = isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return is_scalar( $value ) ? (string) $value : $default;
};

$tg_pre    = array(
	'tour_id'  => $tg_checkout_tour_id,
	'date'     => $tg_q( 'date' ),
	'adults'   => max( 1, absint( $tg_q( 'adults', '1' ) ) ),
	'children' => absint( $tg_q( 'children', '0' ) ),
	'infants'  => absint( $tg_q( 'infants', '0' ) ),
	'addons'   => array_filter( array_map( 'absint', explode( ',', $tg_q( 'addons' ) ) ) ),
	'coupon'   => $tg_q( 'coupon' ),
);

$settings  = tg_settings();
$user      = wp_get_current_user();
$logged_in = is_user_logged_in();

$tg_prefill = array(
	'first_name' => $logged_in ? $user->first_name : '',
	'last_name'  => $logged_in ? $user->last_name : '',
	'email'      => $logged_in ? $user->user_email : '',
	'phone'      => $logged_in ? get_user_meta( $user->ID, '_tg_phone', true ) : '',
	'country'    => $logged_in ? get_user_meta( $user->ID, '_tg_country', true ) : '',
	'address'    => $logged_in ? get_user_meta( $user->ID, '_tg_address', true ) : '',
);

$tg_addons_for_tour = $tg_checkout_tour ? TG_Addons::get_for_tour( $tg_checkout_tour->ID ) : array();
?>

<section class="tg-section" style="padding-top:24px;">
	<div class="tg-container">
		<?php tg_breadcrumbs(); ?>

		<?php if ( ! $tg_checkout_tour ) : ?>
			<?php
			tg_empty_state(
				'compass',
				__( 'No tour selected', 'guidegrid-travel' ),
				__( 'Choose a tour first, then come back to complete your booking.', 'guidegrid-travel' ),
				get_post_type_archive_link( 'tour' ),
				__( 'Browse tours', 'guidegrid-travel' )
			);
			?>
		<?php else : ?>
			<div class="tg-checkout-steps">
				<span class="tg-checkout-step is-done"><?php echo tg_svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( '1. Tour & Date', 'guidegrid-travel' ); ?></span>
				<span class="tg-checkout-step is-done"><?php echo tg_svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( '2. Guests & Add-ons', 'guidegrid-travel' ); ?></span>
				<span class="tg-checkout-step"><?php esc_html_e( '3. Contact & Payment', 'guidegrid-travel' ); ?></span>
			</div>

			<div class="tg-checkout-layout" id="tg-checkout"
				data-tour="<?php echo esc_attr( (string) $tg_checkout_tour->ID ); ?>"
				data-date="<?php echo esc_attr( $tg_pre['date'] ); ?>"
				data-adults="<?php echo esc_attr( (string) $tg_pre['adults'] ); ?>"
				data-children="<?php echo esc_attr( (string) $tg_pre['children'] ); ?>"
				data-infants="<?php echo esc_attr( (string) $tg_pre['infants'] ); ?>"
				data-addons="<?php echo esc_attr( implode( ',', $tg_pre['addons'] ) ); ?>"
				data-coupon="<?php echo esc_attr( $tg_pre['coupon'] ); ?>">

				<!-- ============ Form ============ -->
				<form class="tg-checkout-form" method="post" data-tg-checkout-form novalidate>
					<h2><?php esc_html_e( 'Contact Details', 'guidegrid-travel' ); ?></h2>

					<?php if ( $logged_in ) : ?>
						<p class="tg-notice tg-notice--info"><?php esc_html_e( 'Booking as', 'guidegrid-travel' ); ?> <strong><?php echo esc_html( $user->display_name ); ?></strong> (<?php echo esc_html( $user->user_email ); ?>)</p>
					<?php else : ?>
						<p class="tg-notice tg-notice--info"><?php esc_html_e( 'Booking as a guest. Create a free account to save bookings and write reviews.', 'guidegrid-travel' ); ?></p>
					<?php endif; ?>

					<div class="tg-form-grid">
						<div class="tg-field tg-form-row">
							<label class="tg-label" for="tg-co-fname"><?php esc_html_e( 'First Name', 'guidegrid-travel' ); ?> *</label>
							<input type="text" id="tg-co-fname" name="customer[first_name]" class="tg-input" value="<?php echo esc_attr( $tg_prefill['first_name'] ); ?>" required autocomplete="given-name" />
						</div>
						<div class="tg-field tg-form-row">
							<label class="tg-label" for="tg-co-lname"><?php esc_html_e( 'Last Name', 'guidegrid-travel' ); ?></label>
							<input type="text" id="tg-co-lname" name="customer[last_name]" class="tg-input" value="<?php echo esc_attr( $tg_prefill['last_name'] ); ?>" autocomplete="family-name" />
						</div>
						<div class="tg-field tg-form-row">
							<label class="tg-label" for="tg-co-email"><?php esc_html_e( 'Email', 'guidegrid-travel' ); ?> *</label>
							<input type="email" id="tg-co-email" name="customer[email]" class="tg-input" value="<?php echo esc_attr( $tg_prefill['email'] ); ?>" required autocomplete="email" />
						</div>
						<div class="tg-field tg-form-row">
							<label class="tg-label" for="tg-co-phone"><?php esc_html_e( 'Phone', 'guidegrid-travel' ); ?></label>
							<input type="tel" id="tg-co-phone" name="customer[phone]" class="tg-input" value="<?php echo esc_attr( $tg_prefill['phone'] ); ?>" autocomplete="tel" />
						</div>
						<div class="tg-field tg-form-row">
							<label class="tg-label" for="tg-co-country"><?php esc_html_e( 'Country', 'guidegrid-travel' ); ?></label>
							<input type="text" id="tg-co-country" name="customer[country]" class="tg-input" value="<?php echo esc_attr( $tg_prefill['country'] ); ?>" autocomplete="country-name" />
						</div>
						<div class="tg-field tg-form-row">
							<label class="tg-label" for="tg-co-emergency"><?php esc_html_e( 'Emergency Contact', 'guidegrid-travel' ); ?></label>
							<input type="text" id="tg-co-emergency" name="customer[emergency_contact]" class="tg-input" />
						</div>
					</div>
					<div class="tg-field tg-form-row">
						<label class="tg-label" for="tg-co-address"><?php esc_html_e( 'Address (optional)', 'guidegrid-travel' ); ?></label>
						<textarea id="tg-co-address" name="customer[address]" class="tg-textarea" rows="2" autocomplete="street-address"><?php echo esc_textarea( $tg_prefill['address'] ); ?></textarea>
					</div>
					<div class="tg-field tg-form-row">
						<label class="tg-label" for="tg-co-request"><?php esc_html_e( 'Special Requests (dietary needs, accessibility…)', 'guidegrid-travel' ); ?></label>
						<textarea id="tg-co-request" name="customer[special_request]" class="tg-textarea" rows="3"></textarea>
					</div>

					<hr />

					<h2><?php esc_html_e( 'Payment Method', 'guidegrid-travel' ); ?></h2>
					<div class="tg-payment-methods">
						<?php foreach ( TG_Payments::manual_methods() as $tg_method_key => $tg_method_label ) : ?>
							<label class="tg-payment-option">
								<input type="radio" name="payment_method" value="<?php echo esc_attr( $tg_method_key ); ?>" <?php echo 'bank' === $tg_method_key ? 'checked' : ''; ?> />
								<span>
									<span class="tg-po-name"><?php echo esc_html( $tg_method_label ); ?></span>
									<span class="tg-po-desc">
										<?php
										if ( 'bank' === $tg_method_key ) {
											esc_html_e( 'We will share bank details after your booking is created.', 'guidegrid-travel' );
										} elseif ( 'cash' === $tg_method_key ) {
											esc_html_e( 'Pay in cash at our office or on arrival.', 'guidegrid-travel' );
										} else {
											esc_html_e( 'Reserve now, pay at our office before departure.', 'guidegrid-travel' );
										}
										?>
									</span>
								</span>
							</label>
						<?php endforeach; ?>
						<?php foreach ( TG_Payments::adapters() as $tg_adapter ) : ?>
							<label class="tg-payment-option">
								<input type="radio" name="payment_method" value="<?php echo esc_attr( $tg_adapter->id() ); ?>" />
								<span>
									<span class="tg-po-name"><?php echo esc_html( $tg_adapter->label() ); ?></span>
									<span class="tg-po-desc"><?php echo esc_html( $tg_adapter->description() ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>

					<div class="tg-field tg-form-row" style="margin-top:18px;">
						<div class="tg-check-row">
							<input type="checkbox" id="tg-co-terms" required />
							<label for="tg-co-terms">
								<?php
								$policy_id = tg_page_id_by_path( 'cancellation-refund-policy' );
								printf(
									/* translators: %s: policy page link */
									__( 'I have read and accept the %s and the terms of booking.', 'guidegrid-travel' ),
									$policy_id
										? '<a href="' . esc_url( get_permalink( $policy_id ) ) . '">' . esc_html__( 'cancellation policy', 'guidegrid-travel' ) . '</a>'
										: esc_html__( 'cancellation policy', 'guidegrid-travel' )
								);
								?>
							</label>
						</div>
					</div>

					<div class="tg-check-row" style="margin-bottom:20px;">
						<span class="tg-bw-note" style="margin:0;"><?php esc_html_e( 'By confirming, you agree that the final price is the one shown in the summary (recalculated by the server at booking time).', 'guidegrid-travel' ); ?></span>
					</div>

					<button type="submit" class="tg-btn tg-btn--primary tg-btn--block" data-tg-submit-booking>
						<?php esc_html_e( 'Confirm Booking', 'guidegrid-travel' ); ?><span class="tg-spinner" aria-hidden="true"></span>
					</button>
				</form>

				<!-- ============ Summary ============ -->
				<aside class="tg-summary-card" aria-label="<?php esc_attr_e( 'Booking summary', 'guidegrid-travel' ); ?>">
					<h3 style="margin-top:0;"><?php esc_html_e( 'Your Booking', 'guidegrid-travel' ); ?></h3>
					<div class="tg-summary-tour">
						<img src="<?php echo esc_url( tg_tour_image_url( $tg_checkout_tour->ID ) ); ?>" alt="<?php echo esc_attr( get_the_title( $tg_checkout_tour ) ); ?>" />
						<div>
							<h3><a href="<?php echo esc_url( get_permalink( $tg_checkout_tour ) ); ?>"><?php echo esc_html( get_the_title( $tg_checkout_tour ) ); ?></a></h3>
							<span class="tg-meta" data-tg-summary-meta><?php echo esc_html( tg_tour_duration_text( $tg_checkout_tour->ID ) ); ?></span>
						</div>
					</div>

					<div class="tg-summary-rows">
						<div class="tg-summary-row"><span><?php esc_html_e( 'Date', 'guidegrid-travel' ); ?></span><span data-tg-sum-date>—</span></div>
						<div class="tg-summary-row"><span><?php esc_html_e( 'Adults', 'guidegrid-travel' ); ?></span><span data-tg-sum-adults>—</span></div>
						<div class="tg-summary-row"><span><?php esc_html_e( 'Children', 'guidegrid-travel' ); ?></span><span data-tg-sum-children>—</span></div>
						<div class="tg-summary-row"><span><?php esc_html_e( 'Infants', 'guidegrid-travel' ); ?></span><span data-tg-sum-infants>—</span></div>
						<div data-tg-sum-addons-wrap hidden>
							<div class="tg-summary-row"><span><?php esc_html_e( 'Add-ons', 'guidegrid-travel' ); ?></span><span data-tg-sum-addons>—</span></div>
						</div>
					</div>

					<div class="tg-summary-totals" data-tg-summary-totals>
						<div class="tg-summary-row"><span><?php esc_html_e( 'Subtotal', 'guidegrid-travel' ); ?></span><span data-tg-sum-subtotal>—</span></div>
						<div class="tg-summary-row" data-tg-sum-discount-row hidden><span><?php esc_html_e( 'Discount', 'guidegrid-travel' ); ?></span><span data-tg-sum-discount>—</span></div>
						<div class="tg-summary-row" data-tg-sum-tax-row hidden><span><?php esc_html_e( 'Tax', 'guidegrid-travel' ); ?></span><span data-tg-sum-tax>—</span></div>
						<div class="tg-summary-row" data-tg-sum-fee-row hidden><span><?php esc_html_e( 'Service fee', 'guidegrid-travel' ); ?></span><span data-tg-sum-fee>—</span></div>
						<div class="tg-summary-row" style="font-size:1.15rem;font-weight:800;color:var(--tg-heading);border-top:1px dashed var(--tg-border);margin-top:8px;padding-top:10px;"><span><?php esc_html_e( 'Total', 'guidegrid-travel' ); ?></span><span data-tg-sum-total>—</span></div>
						<div class="tg-summary-row" data-tg-sum-deposit-row hidden><span><?php esc_html_e( 'Deposit due today', 'guidegrid-travel' ); ?></span><span data-tg-sum-deposit>—</span></div>
					</div>
					<p class="tg-bw-note" data-tg-summary-note><?php esc_html_e( 'Showing initial estimate — totals update after the server validates availability and price.', 'guidegrid-travel' ); ?></p>
				</aside>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
