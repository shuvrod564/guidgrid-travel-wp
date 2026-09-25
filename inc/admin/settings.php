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

		$notice_key = 'tg_settings_notice_' . get_current_user_id();
		$notice     = get_transient( $notice_key );
		if ( false !== $notice ) {
			delete_transient( $notice_key );
		}

		$s = tg_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tour Settings', 'guidegrid-travel' ); ?></h1>
			<?php if ( is_array( $notice ) && ! empty( $notice['message'] ) ) : ?>
				<div class="notice <?php echo 'error' === ( $notice['type'] ?? '' ) ? 'notice-error' : ( 'warning' === ( $notice['type'] ?? '' ) ? 'notice-warning' : 'notice-success' ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=tg-settings' ) ); ?>">
				<?php wp_nonce_field( 'tg_save_settings', 'tg_settings_nonce' ); ?>
				<input type="hidden" name="tg_settings_action" value="save" />
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

				<h3><?php esc_html_e( 'Stripe Checkout', 'guidegrid-travel' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Stripe', 'guidegrid-travel' ); ?></th>
						<td>
							<label><input type="checkbox" name="tg_settings[stripe_enabled]" value="1" <?php checked( ! empty( $s['stripe_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable hosted Stripe Checkout', 'guidegrid-travel' ); ?></label>
							<p class="description"><strong><?php esc_html_e( 'Status:', 'guidegrid-travel' ); ?></strong> <?php echo ! empty( $s['stripe_enabled'] ) && ! empty( $s['stripe_secret_key'] ) && ! empty( $s['stripe_webhook_secret'] ) ? esc_html( str_starts_with( (string) $s['stripe_secret_key'], 'sk_live_' ) ? __( 'Enabled (live mode)', 'guidegrid-travel' ) : __( 'Enabled (test mode)', 'guidegrid-travel' ) ) : esc_html__( 'Disabled or incomplete', 'guidegrid-travel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="tg-stripe-key"><?php esc_html_e( 'Stripe Secret Key', 'guidegrid-travel' ); ?></label></th>
						<td>
							<input type="password" id="tg-stripe-key" name="tg_settings[stripe_secret_key]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( ! empty( $s['stripe_secret_key'] ) ? __( 'Saved — leave blank to keep', 'guidegrid-travel' ) : 'sk_test_…' ); ?>" />
							<?php if ( ! empty( $s['stripe_secret_key'] ) ) : ?><label><input type="checkbox" name="tg_settings[clear_stripe_secret_key]" value="1" /> <?php esc_html_e( 'Clear saved key', 'guidegrid-travel' ); ?></label><?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="tg-stripe-webhook"><?php esc_html_e( 'Stripe Webhook Signing Secret', 'guidegrid-travel' ); ?></label></th>
						<td>
							<input type="password" id="tg-stripe-webhook" name="tg_settings[stripe_webhook_secret]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( ! empty( $s['stripe_webhook_secret'] ) ? __( 'Saved — leave blank to keep', 'guidegrid-travel' ) : 'whsec_…' ); ?>" />
							<?php if ( ! empty( $s['stripe_webhook_secret'] ) ) : ?><label><input type="checkbox" name="tg_settings[clear_stripe_webhook_secret]" value="1" /> <?php esc_html_e( 'Clear saved secret', 'guidegrid-travel' ); ?></label><?php endif; ?>
							<p class="description"><?php echo esc_html( sprintf( /* translators: %s: webhook URL */ __( 'Webhook URL: %s', 'guidegrid-travel' ), rest_url( 'tg/v1/payments/stripe/webhook' ) ) ); ?></p>
							<p class="description"><?php esc_html_e( 'Subscribe to checkout.session.completed and checkout.session.async_payment_succeeded. Only a verified paid event marks a booking paid.', 'guidegrid-travel' ); ?></p>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'PayPal Checkout', 'guidegrid-travel' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'PayPal', 'guidegrid-travel' ); ?></th>
						<td><label><input type="checkbox" name="tg_settings[paypal_enabled]" value="1" <?php checked( ! empty( $s['paypal_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable PayPal Orders checkout', 'guidegrid-travel' ); ?></label><br />
						<label><input type="checkbox" name="tg_settings[paypal_sandbox]" value="1" <?php checked( ! empty( $s['paypal_sandbox'] ) ); ?> /> <?php esc_html_e( 'Use PayPal Sandbox (recommended until tested)', 'guidegrid-travel' ); ?></label>
						<p class="description"><strong><?php esc_html_e( 'Status:', 'guidegrid-travel' ); ?></strong> <?php echo ! empty( $s['paypal_enabled'] ) && ! empty( $s['paypal_client_id'] ) && ! empty( $s['paypal_client_secret'] ) ? esc_html( ! empty( $s['paypal_sandbox'] ) ? __( 'Enabled (sandbox)', 'guidegrid-travel' ) : __( 'Enabled (live)', 'guidegrid-travel' ) ) : esc_html__( 'Disabled or incomplete', 'guidegrid-travel' ); ?> <?php echo ! empty( $s['paypal_webhook_id'] ) ? esc_html__( 'Webhook verification configured.', 'guidegrid-travel' ) : esc_html__( 'Webhook verification not configured.', 'guidegrid-travel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="tg-paypal-client"><?php esc_html_e( 'PayPal Client ID', 'guidegrid-travel' ); ?></label></th>
						<td><input type="text" id="tg-paypal-client" name="tg_settings[paypal_client_id]" value="<?php echo esc_attr( $s['paypal_client_id'] ); ?>" class="regular-text" autocomplete="off" /></td>
					</tr>
					<tr>
						<th><label for="tg-paypal-secret"><?php esc_html_e( 'PayPal Client Secret', 'guidegrid-travel' ); ?></label></th>
						<td>
							<input type="password" id="tg-paypal-secret" name="tg_settings[paypal_client_secret]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( ! empty( $s['paypal_client_secret'] ) ? __( 'Saved — leave blank to keep', 'guidegrid-travel' ) : __( 'Enter client secret', 'guidegrid-travel' ) ); ?>" />
							<?php if ( ! empty( $s['paypal_client_secret'] ) ) : ?><label><input type="checkbox" name="tg_settings[clear_paypal_client_secret]" value="1" /> <?php esc_html_e( 'Clear saved secret', 'guidegrid-travel' ); ?></label><?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="tg-paypal-webhook-id"><?php esc_html_e( 'PayPal Webhook ID', 'guidegrid-travel' ); ?></label></th>
						<td>
							<input type="text" id="tg-paypal-webhook-id" name="tg_settings[paypal_webhook_id]" value="<?php echo esc_attr( $s['paypal_webhook_id'] ); ?>" class="regular-text" autocomplete="off" />
							<p class="description"><?php echo esc_html( sprintf( /* translators: %s: webhook URL */ __( 'Webhook URL: %s', 'guidegrid-travel' ), rest_url( 'tg/v1/payments/paypal/webhook' ) ) ); ?></p>
							<p class="description"><?php esc_html_e( 'Subscribe to CHECKOUT.ORDER.APPROVED and PAYMENT.CAPTURE.COMPLETED. The Webhook ID is required to verify PayPal signatures.', 'guidegrid-travel' ); ?></p>
						</td>
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

				<details style="margin:16px 0;">
					<summary><?php esc_html_e( 'Optional custom-adapter webhook (HMAC-SHA256)', 'guidegrid-travel' ); ?></summary>
					<table class="form-table" role="presentation">
						<tr>
							<th><label for="tg-webhook-secret"><?php esc_html_e( 'Webhook Secret', 'guidegrid-travel' ); ?></label></th>
							<td>
								<input type="password" id="tg-webhook-secret" name="tg_settings[webhook_secret]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( ! empty( $s['webhook_secret'] ) ? __( 'Saved — leave blank to keep', 'guidegrid-travel' ) : __( 'Use at least 32 random characters', 'guidegrid-travel' ) ); ?>" />
								<button type="button" class="button" id="tg-generate-webhook-secret"><?php esc_html_e( 'Generate', 'guidegrid-travel' ); ?></button>
								<?php if ( ! empty( $s['webhook_secret'] ) ) : ?><label><input type="checkbox" name="tg_settings[clear_webhook_secret]" value="1" /> <?php esc_html_e( 'Clear saved secret', 'guidegrid-travel' ); ?></label><?php endif; ?>
								<p class="description"><?php echo esc_html( sprintf( /* translators: %s: webhook URL */ __( 'Endpoint: %s', 'guidegrid-travel' ), rest_url( 'tg/v1/payments/webhook' ) ) ); ?></p>
								<p class="description"><?php esc_html_e( 'Send X-TG-Signature as the lowercase hexadecimal HMAC-SHA256 of the untouched raw JSON body. The payload gateway must also have a registered adapter that independently verifies the transaction; this secret does not replace Stripe or PayPal verification.', 'guidegrid-travel' ); ?></p>
							</td>
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

				<?php submit_button( __( 'Save Settings', 'guidegrid-travel' ), 'primary', 'tg_save_settings' ); ?>
			</form>
			<script>
			(function () {
				var button = document.getElementById('tg-generate-webhook-secret');
				var field = document.getElementById('tg-webhook-secret');
				if (!button || !field) return;
				button.addEventListener('click', function () {
					if (!window.crypto || !window.crypto.getRandomValues) return;
					var bytes = new Uint8Array(32);
					window.crypto.getRandomValues(bytes);
					var binary = '';
					bytes.forEach(function (value) { binary += String.fromCharCode(value); });
					field.value = window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
					field.type = 'text';
					field.focus();
				});
			}());
			</script>
		</div>
		<?php
	}
}

if ( ! function_exists( 'tg_save_settings' ) ) {
	/**
	 * Sanitize and save the settings option.
	 *
	 * Secret fields are intentionally blank in the form. A blank submission
	 * keeps the existing value; an adjacent clear checkbox removes it.
	 *
	 * @return array|WP_Error Save result with optional warnings.
	 */
	function tg_save_settings() {
		$current = tg_settings();
		$post    = isset( $_POST['tg_settings'] ) && is_array( $_POST['tg_settings'] ) ? (array) wp_unslash( $_POST['tg_settings'] ) : array();
		if ( empty( $post ) ) {
			return new WP_Error( 'tg_settings_empty', __( 'No settings data was received.', 'guidegrid-travel' ) );
		}

		$updated  = $current;
		$warnings = array();
		$limit    = static function ( string $value, int $length ): string {
			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
		};

		if ( isset( $post['currency'] ) ) {
			$currency = strtoupper( sanitize_text_field( $post['currency'] ) );
			if ( preg_match( '/^[A-Z]{3}$/', $currency ) ) {
				$updated['currency'] = $currency;
			} else {
				$warnings[] = __( 'Currency was not changed because it must be a three-letter ISO code.', 'guidegrid-travel' );
			}
		}

		$text_keys = array(
			'currency_symbol'  => 10,
			'date_format'      => 60,
			'email_from_name'  => 120,
		);
		foreach ( $text_keys as $key => $maxlen ) {
			if ( isset( $post[ $key ] ) ) {
				$updated[ $key ] = $limit( sanitize_text_field( $post[ $key ] ), $maxlen );
			}
		}

		foreach ( array( 'admin_email', 'enquiry_notify_email', 'email_from_email' ) as $key ) {
			if ( ! isset( $post[ $key ] ) ) {
				continue;
			}
			$value = sanitize_email( $post[ $key ] );
			if ( '' === trim( (string) $post[ $key ] ) || is_email( $value ) ) {
				$updated[ $key ] = $value;
			} else {
				$warnings[] = sprintf(
					/* translators: %s: settings field name */
					__( '%s was not changed because the email address is invalid.', 'guidegrid-travel' ),
					str_replace( '_', ' ', $key )
				);
			}
		}

		$number_rules = array(
			'tax_percent'             => array( 0, 100, false ),
			'service_fee'             => array( 0, 99999999, false ),
			'deposit_percent'         => array( 0, 100, false ),
			'available_days_ahead'    => array( 1, 3650, true ),
			'min_booking_notice_days' => array( 0, 3650, true ),
			'hold_minutes'            => array( 1, 10080, true ),
		);
		foreach ( $number_rules as $key => $rule ) {
			if ( ! isset( $post[ $key ] ) || ! is_numeric( $post[ $key ] ) ) {
				continue;
			}
			$value           = max( $rule[0], min( $rule[1], (float) $post[ $key ] ) );
			$updated[ $key ] = $rule[2] ? (int) $value : $value;
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

		if ( isset( $post['paypal_client_id'] ) ) {
			$client_id = $limit( sanitize_text_field( $post['paypal_client_id'] ), 255 );
			if ( '' === $client_id || preg_match( '/^\S{8,255}$/', $client_id ) ) {
				$updated['paypal_client_id'] = $client_id;
			} else {
				$warnings[] = __( 'PayPal Client ID was not changed because its format is invalid.', 'guidegrid-travel' );
			}
		}
		if ( isset( $post['paypal_webhook_id'] ) ) {
			$webhook_id = strtoupper( sanitize_text_field( $post['paypal_webhook_id'] ) );
			if ( '' === $webhook_id || preg_match( '/^[A-Z0-9]{1,50}$/', $webhook_id ) ) {
				$updated['paypal_webhook_id'] = $webhook_id;
			} else {
				$warnings[] = __( 'PayPal Webhook ID was not changed because its format is invalid.', 'guidegrid-travel' );
			}
		}

		$secret_fields = array(
			'stripe_secret_key'      => array( '/^sk_(test|live)_[A-Za-z0-9_]{8,}$/', __( 'Stripe secret key must start with sk_test_ or sk_live_.', 'guidegrid-travel' ) ),
			'stripe_webhook_secret'  => array( '/^whsec_[A-Za-z0-9_]{8,}$/', __( 'Stripe webhook secret must start with whsec_.', 'guidegrid-travel' ) ),
			'paypal_client_secret'   => array( '/^\S{8,255}$/', __( 'PayPal client secret must contain at least eight characters and no spaces.', 'guidegrid-travel' ) ),
			'webhook_secret'         => array( '/^\S{32,255}$/', __( 'The custom HMAC webhook secret must contain at least 32 characters and no spaces.', 'guidegrid-travel' ) ),
		);
		foreach ( $secret_fields as $key => $validation ) {
			$clear_key = 'clear_' . $key;
			if ( ! empty( $post[ $clear_key ] ) ) {
				$updated[ $key ] = '';
				continue;
			}
			if ( ! isset( $post[ $key ] ) || '' === trim( (string) $post[ $key ] ) ) {
				continue;
			}
			$value = $limit( sanitize_text_field( $post[ $key ] ), 255 );
			if ( preg_match( $validation[0], $value ) ) {
				$updated[ $key ] = $value;
			} else {
				$warnings[] = $validation[1];
			}
		}

		if ( ! empty( $updated['stripe_enabled'] ) && ( empty( $updated['stripe_secret_key'] ) || empty( $updated['stripe_webhook_secret'] ) ) ) {
			$updated['stripe_enabled'] = 0;
			$warnings[] = __( 'Stripe was left disabled because both its secret key and webhook signing secret are required.', 'guidegrid-travel' );
		}
		if ( ! empty( $updated['paypal_enabled'] ) && ( empty( $updated['paypal_client_id'] ) || empty( $updated['paypal_client_secret'] ) ) ) {
			$updated['paypal_enabled'] = 0;
			$warnings[] = __( 'PayPal was left disabled because its Client ID and Client Secret are required.', 'guidegrid-travel' );
		} elseif ( ! empty( $updated['paypal_enabled'] ) && empty( $updated['paypal_webhook_id'] ) ) {
			$warnings[] = __( 'PayPal checkout is enabled, but reliable webhook settlement remains unavailable until a PayPal Webhook ID is saved.', 'guidegrid-travel' );
		}

		$saved = update_option( 'tg_settings', $updated );
		if ( ! $saved && get_option( 'tg_settings', array() ) != $updated ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			return new WP_Error( 'tg_settings_write_failed', __( 'WordPress could not write the settings option to the database.', 'guidegrid-travel' ) );
		}

		return array(
			'saved'    => true,
			'warnings' => array_values( array_unique( $warnings ) ),
		);
	}
}

/**
 * Save settings during admin_init, before the admin header is rendered.
 *
 * @return void
 */
function tg_handle_settings_save(): void {
	$page   = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
	$action = isset( $_POST['tg_settings_action'] ) ? sanitize_key( wp_unslash( $_POST['tg_settings_action'] ) ) : '';
	if ( 'POST' !== $method || 'tg-settings' !== $page || 'save' !== $action ) {
		return;
	}
	if ( ! current_user_can( 'manage_tg' ) ) {
		wp_die(
			esc_html__( 'You do not have permission to update these settings.', 'guidegrid-travel' ),
			esc_html__( 'Forbidden', 'guidegrid-travel' ),
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'tg_save_settings', 'tg_settings_nonce' );
	$result = tg_save_settings();
	$key    = 'tg_settings_notice_' . get_current_user_id();
	if ( is_wp_error( $result ) ) {
		$notice = array( 'type' => 'error', 'message' => $result->get_error_message() );
	} elseif ( ! empty( $result['warnings'] ) ) {
		$notice = array(
			'type'    => 'warning',
			'message' => __( 'Settings saved with warnings: ', 'guidegrid-travel' ) . implode( ' ', $result['warnings'] ),
		);
	} else {
		$notice = array( 'type' => 'success', 'message' => __( 'Settings saved.', 'guidegrid-travel' ) );
	}
	set_transient( $key, $notice, MINUTE_IN_SECONDS );
	wp_safe_redirect( admin_url( 'admin.php?page=tg-settings' ) );
	exit;
}
add_action( 'admin_init', 'tg_handle_settings_save', 20 );
