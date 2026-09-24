<?php
/**
 * Tour Settings admin page (single tg_settings option).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_render_settings_page' ) ) {
	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	function tg_render_settings_page() {
		if ( ! current_user_can( 'manage_tg' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'guidegrid-travel' ) );
		}

		$updated = false;
		if ( isset( $_POST['tg_settings_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['tg_settings_nonce'] ), 'tg_save_settings' ) && isset( $_POST['tg_save_settings'] ) ) {
			tg_save_settings();
			$updated = true;
		}

		$s = tg_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tour Settings', 'guidegrid-travel' ); ?></h1>
			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'guidegrid-travel' ); ?></p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'tg_save_settings', 'tg_settings_nonce' ); ?>
				<h2><?php esc_html_e( 'General', 'guidegrid-travel' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="tg-currency"><?php esc_html_e( 'Default Currency', 'guidegrid-travel' ); ?></label></th>
						<td><input type="text" id="tg-currency" name="tg_settings[currency]" class="regular-text" value="<?php echo esc_attr( $s['currency'] ); ?>" maxlength="3" /></td>
					</tr>
					<tr>
						<th><label for="tg-currency-symbol"><?php esc_html_e( 'Currency Symbol (optional)', 'guidegrid-travel' ); ?></label></th>
						<td><input type="text" id="tg-currency-symbol" name="tg_settings[currency_symbol]" class="regular-text" value="<?php echo esc_attr( $s['currency_symbol'] ); ?>" placeholder="$" /></td>
					</tr>
					<tr>
						<th><label for="tg-date-format"><?php esc_html_e( 'Date Format', 'guidegrid-travel' ); ?></label></th>
						<td><input type="text" id="tg-date-format" name="tg_settings[date_format]" class="regular-text" value="<?php echo esc_attr( $s['date_format'] ); ?>" placeholder="F j, Y" />
						<p class="description"><?php esc_html_e( 'PHP date format.', 'guidegrid-travel' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="tg-admin-email"><?php esc_html_e( 'Notifications Email', 'guidegrid-travel' ); ?></label></th>
						<td><input type="email" id="tg-admin-email" name="tg_settings[admin_email]" class="regular-text" value="<?php echo esc_attr( $s['admin_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Pricing', 'guidegrid-travel' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="tg-tax"><?php esc_html_e( 'Default Tax %', 'guidegrid-travel' ); ?></label></th>
						<td><input type="number" step="0.01" min="0" id="tg-tax" name="tg_settings[tax_percent]" value="<?php echo esc_attr( (string) $s['tax_percent'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="tg-fee"><?php esc_html_e( 'Default Service Fee (flat)', 'guidegrid-travel' ); ?></label></th>
						<td><input type="number" step="0.01" min="0" id="tg-fee" name="tg_settings[service_fee]" value="<?php echo esc_attr( (string) $s['service_fee'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="tg-deposit"><?php esc_html_e( 'Default Deposit %', 'guidegrid-travel' ); ?></label></th>
						<td><input type="number" step="0.01" min="0" max="100" id="tg-deposit" name="tg_settings[deposit_percent]" value="<?php echo esc_attr( (string) $s['deposit_percent'] ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Tax Add-ons by default', 'guidegrid-travel' ); ?></th>
						<td><label><input type="checkbox" name="tg_settings[tax_on_addons]" value="1" <?php checked( ! empty( $s['tax_on_addons'] ) ); ?> /> <?php esc_html_e( 'Include add-on amounts in the taxable base unless marked non-taxable', 'guidegrid-travel' ); ?></label></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Availability & Booking', 'guidegrid-travel' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="tg-days-ahead"><?php esc_html_e( 'Sell dates (days ahead)', 'guidegrid-travel' ); ?></label></th>
						<td><input type="number" min="1" id="tg-days-ahead" name="tg_settings[available_days_ahead]" value="<?php echo esc_attr( (string) $s['available_days_ahead'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="tg-notice"><?php esc_html_e( 'Minimum booking notice (days)', 'guidegrid-travel' ); ?></label></th>
						<td><input type="number" min="0" id="tg-notice" name="tg_settings[min_booking_notice_days]" value="<?php echo esc_attr( (string) $s['min_booking_notice_days'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="tg-hold"><?php esc_html_e( 'Payment hold (minutes)', 'guidegrid-travel' ); ?></label></th>
						<td><input type="number" min="1" id="tg-hold" name="tg_settings[hold_minutes]" value="<?php echo esc_attr( (string) $s['hold_minutes'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Unpaid bookings release their seats automatically after this time.', 'guidegrid-travel' ); ?></p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Customer account', 'guidegrid-travel' ); ?></th>
						<td><input type="hidden" name="tg_settings[require_customer_account]" value="1" /><strong><?php esc_html_e( 'Required', 'guidegrid-travel' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Customers must log in or create an account. This is enforced on both the checkout page and booking API.', 'guidegrid-travel' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="tg-reminders"><?php esc_html_e( 'Reminder offsets (days before, comma separated)', 'guidegrid-travel' ); ?></label></th>
						<td><input type="text" id="tg-reminders" name="tg_settings[reminder_days_csv]" value="<?php echo esc_attr( implode( ',', $s['reminder_days'] ) ); ?>" class="regular-text" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Payments', 'guidegrid-travel' ); ?></h2>
				<p><?php esc_html_e( 'Online methods appear at checkout only after they are enabled and all required credentials are saved.', 'guidegrid-travel' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					foreach ( array( 'bank', 'cash', 'paylater' ) as $method_key ) :
						$label        = isset( $s['manual_payment_methods'][ $method_key ] ) ? $s['manual_payment_methods'][ $method_key ] : $method_key;
						$enabled      = ! empty( $s['manual_payment_enabled'][ $method_key ] );
						$instructions = isset( $s['manual_payment_instructions'][ $method_key ] ) ? $s['manual_payment_instructions'][ $method_key ] : '';
						?>
						<tr>
							<th><label for="tg-method-<?php echo esc_attr( $method_key ); ?>"><?php echo esc_html( ucfirst( str_replace( 'paylater', 'Pay later', $method_key ) ) ); ?></label></th>
							<td>
								<label><input type="checkbox" name="tg_settings[manual_enabled_<?php echo esc_attr( $method_key ); ?>]" value="1" <?php checked( $enabled ); ?> /> <?php esc_html_e( 'Enable at checkout', 'guidegrid-travel' ); ?></label><br />
								<input type="text" id="tg-method-<?php echo esc_attr( $method_key ); ?>" name="tg_settings[manual_method_<?php echo esc_attr( $method_key ); ?>]" value="<?php echo esc_attr( $label ); ?>" class="regular-text" /><br />
								<textarea name="tg_settings[manual_instructions_<?php echo esc_attr( $method_key ); ?>]" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Customer instructions shown after booking', 'guidegrid-travel' ); ?>"><?php echo esc_textarea( $instructions ); ?></textarea>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				
				<div style="display:none;">
				<h3><?php esc_html_e( 'Stripe Checkout', 'guidegrid-travel' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Stripe', 'guidegrid-travel' ); ?></th>
						<td><label><input type="checkbox" name="tg_settings[stripe_enabled]" value="1" <?php checked( ! empty( $s['stripe_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable hosted Stripe Checkout', 'guidegrid-travel' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="tg-stripe-key"><?php esc_html_e( 'Stripe Secret Key', 'guidegrid-travel' ); ?></label></th>
						<td><input type="password" id="tg-stripe-key" name="tg_settings[stripe_secret_key]" value="<?php echo esc_attr( $s['stripe_secret_key'] ); ?>" class="regular-text" autocomplete="new-password" placeholder="sk_test_…" /></td>
					</tr>
					<tr>
						<th><label for="tg-stripe-webhook"><?php esc_html_e( 'Stripe Webhook Signing Secret', 'guidegrid-travel' ); ?></label></th>
						<td><input type="password" id="tg-stripe-webhook" name="tg_settings[stripe_webhook_secret]" value="<?php echo esc_attr( $s['stripe_webhook_secret'] ); ?>" class="regular-text" autocomplete="new-password" placeholder="whsec_…" />
						<p class="description"><?php echo esc_html( sprintf( /* translators: %s: webhook URL */ __( 'Create a Stripe webhook for: %s', 'guidegrid-travel' ), rest_url( 'tg/v1/payments/stripe/webhook' ) ) ); ?></p></td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'PayPal Checkout', 'guidegrid-travel' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'PayPal', 'guidegrid-travel' ); ?></th>
						<td><label><input type="checkbox" name="tg_settings[paypal_enabled]" value="1" <?php checked( ! empty( $s['paypal_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable PayPal Orders checkout', 'guidegrid-travel' ); ?></label><br />
						<label><input type="checkbox" name="tg_settings[paypal_sandbox]" value="1" <?php checked( ! empty( $s['paypal_sandbox'] ) ); ?> /> <?php esc_html_e( 'Use PayPal Sandbox (recommended until tested)', 'guidegrid-travel' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="tg-paypal-client"><?php esc_html_e( 'PayPal Client ID', 'guidegrid-travel' ); ?></label></th>
						<td><input type="text" id="tg-paypal-client" name="tg_settings[paypal_client_id]" value="<?php echo esc_attr( $s['paypal_client_id'] ); ?>" class="regular-text" autocomplete="off" /></td>
					</tr>
					<tr>
						<th><label for="tg-paypal-secret"><?php esc_html_e( 'PayPal Client Secret', 'guidegrid-travel' ); ?></label></th>
						<td><input type="password" id="tg-paypal-secret" name="tg_settings[paypal_client_secret]" value="<?php echo esc_attr( $s['paypal_client_secret'] ); ?>" class="regular-text" autocomplete="new-password" /></td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Payment Simulator', 'guidegrid-travel' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Test gateway', 'guidegrid-travel' ); ?></th>
						<td>
							<label><input type="checkbox" name="tg_settings[test_gateway_enabled]" value="1" <?php checked( ! empty( $s['test_gateway_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable simulated approve/decline payments', 'guidegrid-travel' ); ?></label>
							<p class="description"><?php esc_html_e( 'No money is charged. The simulator is available only on local, development, or staging environments and recognized local hostnames.', 'guidegrid-travel' ); ?></p>
						</td>
					</tr>
				</table>
				</div>

				<details style="margin:16px 0;">
					<summary><?php esc_html_e( 'Legacy custom-adapter webhook', 'guidegrid-travel' ); ?></summary>
					<table class="form-table" role="presentation">
						<tr>
							<th><label for="tg-webhook-secret"><?php esc_html_e( 'Webhook Secret (HMAC-SHA256)', 'guidegrid-travel' ); ?></label></th>
							<td><input type="password" id="tg-webhook-secret" name="tg_settings[webhook_secret]" value="<?php echo esc_attr( $s['webhook_secret'] ); ?>" class="regular-text" autocomplete="new-password" /></td>
						</tr>
					</table>
				</details>

				<h2><?php esc_html_e( 'Emails & Contact', 'guidegrid-travel' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="tg-from-name"><?php esc_html_e( 'Sender Name', 'guidegrid-travel' ); ?></label></th>
						<td><input type="text" id="tg-from-name" name="tg_settings[email_from_name]" value="<?php echo esc_attr( $s['email_from_name'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="tg-from-email"><?php esc_html_e( 'Sender Email', 'guidegrid-travel' ); ?></label></th>
						<td><input type="email" id="tg-from-email" name="tg_settings[email_from_email]" value="<?php echo esc_attr( $s['email_from_email'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="tg-enquiry-email"><?php esc_html_e( 'Enquiry Notifications Email', 'guidegrid-travel' ); ?></label></th>
						<td><input type="email" id="tg-enquiry-email" name="tg_settings[enquiry_notify_email]" value="<?php echo esc_attr( $s['enquiry_notify_email'] ); ?>" class="regular-text" /></td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'guidegrid-travel' ) ); ?>
			</form>
		</div>
		<?php
	}
}

if ( ! function_exists( 'tg_save_settings' ) ) {
	/**
	 * Sanitize + save the settings option.
	 *
	 * @return void
	 */
	function tg_save_settings() {
		$current  = tg_settings();
		$post     = isset( $_POST['tg_settings'] ) && is_array( $_POST['tg_settings'] ) ? (array) wp_unslash( $_POST['tg_settings'] ) : array();
		$updated  = $current;

		$text_keys = array(
			'currency'         => 3,
			'currency_symbol'  => 10,
			'date_format'      => 60,
			'admin_email'      => null,
			'enquiry_notify_email' => null,
			'email_from_name'        => 120,
			'webhook_secret'         => 255,
			'stripe_secret_key'      => 255,
			'stripe_webhook_secret'  => 255,
			'paypal_client_id'       => 255,
			'paypal_client_secret'   => 255,
		);

		foreach ( $text_keys as $key => $maxlen ) {
			if ( isset( $post[ $key ] ) ) {
				$value = sanitize_text_field( $post[ $key ] );
				if ( null === $maxlen && str_contains( $key, 'email' ) && '' !== $value && ! is_email( $value ) ) {
					continue;
				}
				$updated[ $key ] = ( null !== $maxlen ) ? mb_substr( $value, 0, $maxlen ) : $value;
			}
		}

		$num_keys = array(
			'tax_percent', 'service_fee', 'deposit_percent',
			'available_days_ahead', 'min_booking_notice_days', 'hold_minutes',
		);
		foreach ( $num_keys as $key ) {
			if ( isset( $post[ $key ] ) ) {
				$updated[ $key ] = max( 0, (float) $post[ $key ] );
			}
		}

		$bool_keys = array( 'tax_on_addons', 'require_customer_account', 'stripe_enabled', 'paypal_enabled', 'paypal_sandbox', 'test_gateway_enabled' );
		foreach ( $bool_keys as $key ) {
			$updated[ $key ] = isset( $post[ $key ] ) ? 1 : 0;
		}

		if ( isset( $post['reminder_days_csv'] ) ) {
			$days = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( $post['reminder_days_csv'] ) ) ) );
			$days = array_slice( array_unique( $days ), 0, 10 );
			$updated['reminder_days'] = $days ? $days : array( 7, 3, 1 );
		}

		$methods      = array();
		$enabled      = array();
		$instructions = array();
		foreach ( array( 'bank', 'cash', 'paylater' ) as $key ) {
			$label_field       = 'manual_method_' . $key;
			$enabled_field     = 'manual_enabled_' . $key;
			$instruction_field = 'manual_instructions_' . $key;
			$methods[ $key ]      = isset( $post[ $label_field ] ) ? sanitize_text_field( $post[ $label_field ] ) : $current['manual_payment_methods'][ $key ];
			$enabled[ $key ]      = isset( $post[ $enabled_field ] ) ? 1 : 0;
			$instructions[ $key ] = isset( $post[ $instruction_field ] ) ? sanitize_textarea_field( $post[ $instruction_field ] ) : $current['manual_payment_instructions'][ $key ];
		}
		$updated['manual_payment_methods']      = $methods;
		$updated['manual_payment_enabled']      = $enabled;
		$updated['manual_payment_instructions'] = $instructions;
		$updated['allow_guest_checkout']        = empty( $updated['require_customer_account'] ) ? 1 : 0;

		update_option( 'tg_settings', $updated );
	}
}
