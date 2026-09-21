<?php
/**
 * Booking widget (tour page sidebar).
 *
 * All prices shown are placeholders until the first quote response —
 * authoritative prices always come from the server.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

$bw_id     = get_the_ID();
$bw_prices = tg_tour_price_info( $bw_id );
$bw_cy     = $bw_prices['currency'];
$bw_addons = TG_Addons::get_for_tour( $bw_id );
$bw_dates  = isset( $tgTourData ) ? $tgTourData : array();
?>
<div class="tg-booking-widget" id="tg-booking-widget" data-tg-widget data-tour="<?php echo esc_attr( (string) $bw_id ); ?>">
	<div class="tg-bw-price">
		<span>
			<span class="from"><?php esc_html_e( 'From', 'guidegrid-travel' ); ?></span>
			<span class="amount"><?php echo esc_html( tg_format_price( $bw_prices['base'], $bw_cy ) ); ?></span>
		</span>
		<?php if ( $bw_prices['previous'] > 0 ) : ?>
			<span class="old"><?php echo esc_html( tg_format_price( $bw_prices['previous'], $bw_cy ) ); ?></span>
			<span class="save">
				<?php
				printf(
					/* translators: %d: percent saved */
					esc_html__( 'Save %d%%', 'guidegrid-travel' ),
					(int) round( ( 1 - $bw_prices['base'] / $bw_prices['previous'] ) * 100 )
				);
				?>
			</span>
		<?php endif; ?>
	</div>

	<div class="tg-bw-group">
		<label class="tg-label" for="tg-bw-date"><?php esc_html_e( 'Select Date', 'guidegrid-travel' ); ?> *</label>
		<select class="tg-select" id="tg-bw-date" name="date" data-tg-field="date" required>
			<option value=""><?php esc_html_e( 'Select a date…', 'guidegrid-travel' ); ?></option>
		</select>
		<p class="tg-bw-dates-empty" data-tg-dates-empty hidden></p>
	</div>

	<div class="tg-bw-group">
		<span class="tg-label"><?php esc_html_e( 'Guests', 'guidegrid-travel' ); ?></span>
		<?php foreach ( tg_guest_labels() as $key => $guest ) : ?>
			<div class="tg-bw-guest-row">
				<span class="tg-guest-name">
					<?php echo esc_html( $guest['label'] ); ?>
					<small class="tg-guest-price"><?php echo esc_html( $guest['desc'] ); ?></small>
				</span>
				<span class="tg-stepper" data-tg-stepper data-field="<?php echo esc_attr( $key ); ?>">
					<button type="button" data-step="-1" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: guest type */ __( 'Decrease %s', 'guidegrid-travel' ), $guest['label'] ) ); ?>">−</button>
					<span class="tg-stepper-value" aria-live="polite"><?php echo 'adults' === $key ? '1' : '0'; ?></span>
					<button type="button" data-step="1" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: guest type */ __( 'Increase %s', 'guidegrid-travel' ), $guest['label'] ) ); ?>">+</button>
				</span>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $bw_addons ) : ?>
		<div class="tg-bw-group">
			<span class="tg-label"><?php esc_html_e( 'Add-ons', 'guidegrid-travel' ); ?></span>
			<div class="tg-bw-addons">
				<?php foreach ( $bw_addons as $addon ) : ?>
					<label class="tg-bw-addon">
						<input type="checkbox" name="addons[]" value="<?php echo esc_attr( (string) $addon['id'] ); ?>" data-tg-addon data-price="<?php echo esc_attr( (string) $addon['price'] ); ?>" data-unit="<?php echo esc_attr( $addon['unit'] ); ?>" />
						<span class="tg-addon-name"><?php echo esc_html( $addon['name'] ); ?><small><?php echo esc_html( TG_Addons::unit_label( $addon['unit'] ) ); ?></small></span>
						<span class="tg-addon-price"><?php echo esc_html( tg_format_price( $addon['price'], $bw_cy ) ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="tg-bw-group">
		<label class="tg-label" for="tg-bw-coupon"><?php esc_html_e( 'Coupon code', 'guidegrid-travel' ); ?></label>
		<div class="tg-coupon-row">
			<input type="text" class="tg-input" id="tg-bw-coupon" data-tg-field="coupon" placeholder="<?php esc_attr_e( 'e.g. WELCOME10', 'guidegrid-travel' ); ?>" />
			<button type="button" class="tg-btn tg-btn--secondary tg-btn--sm" data-tg-apply-coupon><?php esc_html_e( 'Apply', 'guidegrid-travel' ); ?></button>
		</div>
		<p class="tg-coupon-msg" data-tg-coupon-msg aria-live="polite"></p>
	</div>

	<div class="tg-bw-totals" aria-live="polite">
		<div class="tg-bw-total-row" data-tg-line="subtotal"><span><?php esc_html_e( 'Subtotal', 'guidegrid-travel' ); ?></span><span>—</span></div>
		<div class="tg-bw-total-row discount" data-tg-line="discount" hidden><span><?php esc_html_e( 'Discount', 'guidegrid-travel' ); ?></span><span>—</span></div>
		<div class="tg-bw-total-row" data-tg-line="tax" hidden><span><?php esc_html_e( 'Tax', 'guidegrid-travel' ); ?></span><span>—</span></div>
		<div class="tg-bw-total-row" data-tg-line="fee" hidden><span><?php esc_html_e( 'Service fee', 'guidegrid-travel' ); ?></span><span>—</span></div>
		<div class="tg-bw-total-row grand"><span><?php esc_html_e( 'Total', 'guidegrid-travel' ); ?></span><span data-tg-total><?php echo esc_html( tg_format_price( $bw_prices['base'], $bw_cy ) ); ?></span></div>
	</div>

	<button type="button" class="tg-btn tg-btn--primary tg-btn--block tg-mt-4" data-tg-book-now>
		<?php esc_html_e( 'Book Now', 'guidegrid-travel' ); ?><span class="tg-spinner" aria-hidden="true"></span>
	</button>
	<p class="tg-bw-note"><?php esc_html_e( 'No payment required yet — your seats are held until payment.', 'guidegrid-travel' ); ?></p>

	<input type="hidden" name="tour_id" value="<?php echo esc_attr( (string) $bw_id ); ?>" />
	<input type="hidden" data-tg-field="adults" value="1" />
	<input type="hidden" data-tg-field="children" value="0" />
	<input type="hidden" data-tg-field="infants" value="0" />
</div>
