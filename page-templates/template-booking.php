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

$settings           = tg_settings();
$user               = wp_get_current_user();
$logged_in          = is_user_logged_in();
$tg_manual_methods  = TG_Payments::manual_methods();
$tg_online_adapters = TG_Payments::adapters();
$tg_method_keys     = array_merge( array_keys( $tg_manual_methods ), array_keys( $tg_online_adapters ) );
$tg_default_method  = $tg_method_keys ? (string) $tg_method_keys[0] : '';

$tg_prefill = array(
	'first_name' => $logged_in ? ( $user->first_name ? $user->first_name : $user->display_name ) : '',
	'last_name'  => $logged_in ? $user->last_name : '',
	'email'      => $logged_in ? $user->user_email : '',
	'phone'      => $logged_in ? get_user_meta( $user->ID, '_tg_phone', true ) : '',
	'country'    => $logged_in ? get_user_meta( $user->ID, '_tg_country', true ) : '',
	'address'    => $logged_in ? get_user_meta( $user->ID, '_tg_address', true ) : '',
);

$tg_addons_for_tour = $tg_checkout_tour ? TG_Addons::get_for_tour( $tg_checkout_tour->ID ) : array();
$tg_checkout_dates  = array();
$tg_checkout_prices = $tg_checkout_tour ? tg_tour_price_info( $tg_checkout_tour->ID ) : array( 'currency' => $settings['currency'] );

if ( $tg_checkout_tour ) {
	try {
		$tg_checkout_dates = TG_Availability::get_dates( $tg_checkout_tour->ID );
	} catch ( \Throwable $e ) { // A missing/outdated custom table should not break the whole checkout page.
		$tg_checkout_dates = array();
	}

	// Do not keep a stale/unavailable date from an old shared checkout URL.
	$tg_checkout_date_values = array_column( $tg_checkout_dates, 'date' );
	if ( $tg_pre['date'] && ! in_array( $tg_pre['date'], $tg_checkout_date_values, true ) ) {
		$tg_pre['date'] = '';
	}
}
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
				data-coupon="<?php echo esc_attr( $tg_pre['coupon'] ); ?>"
				data-has-payment="<?php echo $tg_default_method ? '1' : '0'; ?>">

				<!-- ============ Form ============ -->
				<form class="tg-checkout-form" method="post" data-tg-checkout-form novalidate>
					<section class="tg-checkout-options" aria-labelledby="tg-trip-options-title">
						<h2 id="tg-trip-options-title"><?php esc_html_e( 'Tour Details', 'guidegrid-travel' ); ?></h2>
						<div class="tg-form-grid tg-form-grid--trip">
							<div class="tg-field tg-form-row tg-trip-date-field">
								<label class="tg-label" for="tg-co-date"><?php esc_html_e( 'Travel Date', 'guidegrid-travel' ); ?> *</label>
								<select class="tg-select" id="tg-co-date" name="date" data-tg-co-date required>
									<option value=""><?php esc_html_e( 'Select a date…', 'guidegrid-travel' ); ?></option>
									<?php foreach ( $tg_checkout_dates as $tg_date ) : ?>
										<?php
										$tg_date_label = tg_format_date( $tg_date['date'] );
										if ( isset( $tg_date['remaining'] ) && (int) $tg_date['remaining'] >= 0 && (int) $tg_date['remaining'] <= 5 ) {
											$tg_date_label .= sprintf(
												/* translators: %d: remaining seats */
												__( ' — %d seats left', 'guidegrid-travel' ),
												(int) $tg_date['remaining']
											);
										}
										?>
										<option value="<?php echo esc_attr( $tg_date['date'] ); ?>" <?php selected( $tg_pre['date'], $tg_date['date'] ); ?>><?php echo esc_html( $tg_date_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( empty( $tg_checkout_dates ) ) : ?>
									<p class="tg-field-help"><?php esc_html_e( 'No dates are currently available for this tour.', 'guidegrid-travel' ); ?></p>
								<?php endif; ?>
								<span class="tg-field-error" aria-live="polite"></span>
							</div>
							<div class="tg-field tg-form-row">
								<label class="tg-label" for="tg-co-adults"><?php esc_html_e( 'Adults', 'guidegrid-travel' ); ?> *</label>
								<input class="tg-input" type="number" id="tg-co-adults" name="adults" value="<?php echo esc_attr( (string) $tg_pre['adults'] ); ?>" min="1" step="1" inputmode="numeric" data-tg-co-guests="adults" required />
							</div>
							<div class="tg-field tg-form-row">
								<label class="tg-label" for="tg-co-children"><?php esc_html_e( 'Children', 'guidegrid-travel' ); ?></label>
								<input class="tg-input" type="number" id="tg-co-children" name="children" value="<?php echo esc_attr( (string) $tg_pre['children'] ); ?>" min="0" step="1" inputmode="numeric" data-tg-co-guests="children" />
							</div>
							<div class="tg-field tg-form-row">
								<label class="tg-label" for="tg-co-infants"><?php esc_html_e( 'Infants', 'guidegrid-travel' ); ?></label>
								<input class="tg-input" type="number" id="tg-co-infants" name="infants" value="<?php echo esc_attr( (string) $tg_pre['infants'] ); ?>" min="0" step="1" inputmode="numeric" data-tg-co-guests="infants" />
							</div>
						</div>

						<?php if ( $tg_addons_for_tour ) : ?>
							<fieldset class="tg-checkout-addons">
								<legend class="tg-label"><?php esc_html_e( 'Add-ons', 'guidegrid-travel' ); ?></legend>
								<div class="tg-bw-addons">
									<?php foreach ( $tg_addons_for_tour as $tg_addon ) : ?>
										<label class="tg-bw-addon">
											<input type="checkbox" name="addons[]" value="<?php echo esc_attr( (string) $tg_addon['id'] ); ?>" data-tg-co-addon data-addon-name="<?php echo esc_attr( $tg_addon['name'] ); ?>" <?php checked( in_array( (int) $tg_addon['id'], $tg_pre['addons'], true ) ); ?> />
											<span class="tg-addon-name"><?php echo esc_html( $tg_addon['name'] ); ?><small><?php echo esc_html( TG_Addons::unit_label( $tg_addon['unit'] ) ); ?></small></span>
											<span class="tg-addon-price"><?php echo esc_html( tg_format_price( (float) $tg_addon['price'], $tg_checkout_prices['currency'] ) ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</fieldset>
						<?php endif; ?>

						<div class="tg-field tg-form-row tg-checkout-coupon">
							<label class="tg-label" for="tg-co-coupon"><?php esc_html_e( 'Coupon code', 'guidegrid-travel' ); ?></label>
							<div class="tg-coupon-row">
								<input type="text" class="tg-input" id="tg-co-coupon" name="coupon" value="<?php echo esc_attr( $tg_pre['coupon'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. WELCOME10', 'guidegrid-travel' ); ?>" data-tg-co-coupon />
								<button type="button" class="tg-btn tg-btn--secondary tg-btn--sm" data-tg-co-apply-coupon><?php esc_html_e( 'Apply', 'guidegrid-travel' ); ?></button>
							</div>
							<p class="tg-coupon-msg" data-tg-co-coupon-msg aria-live="polite"></p>
						</div>
					</section>

					<h2><?php esc_html_e( 'Contact Details', 'guidegrid-travel' ); ?></h2>

					<p class="tg-notice tg-notice--info"><?php esc_html_e( 'Booking as', 'guidegrid-travel' ); ?> <strong><?php echo esc_html( $user->display_name ); ?></strong> (<?php echo esc_html( $user->user_email ); ?>)</p>

					<div class="tg-form-grid">
						<div class="tg-field tg-form-row">
							<label class="tg-label" for="tg-co-fname"><?php esc_html_e( 'First Name', 'guidegrid-travel' ); ?> *</label>
							<input type="text" id="tg-co-fname" name="customer[first_name]" class="tg-input" value="<?php echo esc_attr( $tg_prefill['first_name'] ); ?>" required autocomplete="given-name" />
							<span class="tg-field-error" aria-live="polite"></span>
						</div>
						<div class="tg-field tg-form-row">
							<label class="tg-label" for="tg-co-lname"><?php esc_html_e( 'Last Name', 'guidegrid-travel' ); ?></label>
							<input type="text" id="tg-co-lname" name="customer[last_name]" class="tg-input" value="<?php echo esc_attr( $tg_prefill['last_name'] ); ?>" autocomplete="family-name" />
						</div>
						<div class="tg-field tg-form-row">
							<label class="tg-label" for="tg-co-email"><?php esc_html_e( 'Email', 'guidegrid-travel' ); ?> *</label>
							<input type="email" id="tg-co-email" name="customer[email]" class="tg-input" value="<?php echo esc_attr( $tg_prefill['email'] ); ?>" required readonly autocomplete="email" />
							<span class="tg-field-error" aria-live="polite"></span>
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
						<?php foreach ( $tg_manual_methods as $tg_method_key => $tg_method_label ) : ?>
							<label class="tg-payment-option">
								<input type="radio" name="payment_method" value="<?php echo esc_attr( $tg_method_key ); ?>" <?php checked( $tg_default_method, $tg_method_key ); ?> />
								<span>
									<span class="tg-po-name"><?php echo esc_html( $tg_method_label ); ?></span>
									<span class="tg-po-desc"><?php echo esc_html( TG_Payments::manual_instructions( $tg_method_key ) ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
						<?php foreach ( $tg_online_adapters as $tg_adapter ) : ?>
							<label class="tg-payment-option">
								<input type="radio" name="payment_method" value="<?php echo esc_attr( $tg_adapter->id() ); ?>" <?php checked( $tg_default_method, $tg_adapter->id() ); ?> />
								<span>
									<span class="tg-po-name"><?php echo esc_html( $tg_adapter->label() ); ?></span>
									<span class="tg-po-desc"><?php echo esc_html( $tg_adapter->description() ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
						<?php if ( '' === $tg_default_method ) : ?>
							<div class="tg-notice tg-notice--error"><?php esc_html_e( 'No payment method is currently available. Please contact us before booking.', 'guidegrid-travel' ); ?></div>
						<?php endif; ?>
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
