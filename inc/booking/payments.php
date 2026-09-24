<?php
/**
 * Payment adapters and payment-record lifecycle.
 *
 * Card credentials are handled only by Stripe Checkout or PayPal. This theme
 * stores gateway references and verified results, never card data.
 *
 * @package GuideGrid_Travel
 */
defined( 'ABSPATH' ) || exit;

/**
 * Contract implemented by hosted online-payment adapters.
 */
interface TG_Payment_Adapter {
	public function id(): string;
	public function label(): string;
	public function description(): string;

	/**
	 * @param object $booking Booking row.
	 * @param float  $amount  Amount to charge.
	 * @return array|WP_Error {url:string, transaction_id:string, response?:array}
	 */
	public function create_payment( object $booking, float $amount );

	/**
	 * @param array $data Untrusted gateway callback data.
	 * @return array|WP_Error {valid:bool, transaction_id:string, amount:float, currency?:string, booking_number?:string}
	 */
	public function verify_transaction( array $data );
}

/**
 * Whether the no-charge payment simulator may run in this environment.
 *
 * @return bool
 */
function tg_test_gateway_allowed(): bool {
	$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
	if ( in_array( $environment, array( 'local', 'development', 'staging' ), true ) ) {
		return true;
	}
	$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$host = strtolower( $host );
	return in_array( $host, array( 'localhost', '127.0.0.1', '0.0.0.0', '::1' ), true ) || str_ends_with( $host, '.localhost' ) || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.test' );
}

/**
 * Local no-charge gateway for exercising the full payment state machine.
 */
final class TG_Test_Payment_Adapter implements TG_Payment_Adapter {
	public function id(): string {
		return 'test_gateway';
	}

	public function label(): string {
		return __( 'Test Payment — No Charge', 'guidegrid-travel' );
	}

	public function description(): string {
		return __( 'Simulate an approved or declined online payment. No money or external service is involved.', 'guidegrid-travel' );
	}

	public function create_payment( object $booking, float $amount ) {
		if ( ! tg_test_gateway_allowed() ) {
			return new WP_Error( 'tg_test_gateway_disabled', __( 'The payment simulator is unavailable in production.', 'guidegrid-travel' ) );
		}
		return array(
			'url'            => add_query_arg( 'payment', 'simulate', tg_confirmation_page_url( $booking->booking_number ) ),
			'transaction_id' => 'TEST-SESSION-' . wp_generate_uuid4(),
			'response'       => array( 'simulated' => true, 'amount' => $amount ),
		);
	}

	public function verify_transaction( array $data ) {
		return new WP_Error( 'tg_test_gateway_post_required', __( 'Use the authenticated simulator controls to complete this payment.', 'guidegrid-travel' ) );
	}
}

/**
 * Stripe hosted Checkout adapter.
 */
final class TG_Stripe_Checkout_Adapter implements TG_Payment_Adapter {
	private string $secret_key;

	public function __construct( string $secret_key ) {
		$this->secret_key = trim( $secret_key );
	}

	public function id(): string {
		return 'stripe';
	}

	public function label(): string {
		return __( 'Credit or Debit Card (Stripe)', 'guidegrid-travel' );
	}

	public function description(): string {
		return __( 'Pay securely on Stripe Checkout. GuideGrid never receives your card details.', 'guidegrid-travel' );
	}

	/**
	 * Convert a decimal amount into a gateway minor-unit integer.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency ISO currency code.
	 * @return int
	 */
	private function minor_amount( float $amount, string $currency ): int {
		$zero_decimal = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );
		$factor       = in_array( strtoupper( $currency ), $zero_decimal, true ) ? 1 : 100;
		return (int) round( $amount * $factor );
	}

	/**
	 * Perform a Stripe API request.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   API path.
	 * @param array  $body   Form body.
	 * @return array|WP_Error
	 */
	private function request( string $method, string $path, array $body = array() ) {
		$args = array(
			'method'      => $method,
			'timeout'     => 20,
			'redirection' => 0,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $this->secret_key,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
		);
		if ( $body ) {
			$args['body'] = $body;
		}
		$response = wp_remote_request( 'https://api.stripe.com/v1/' . ltrim( $path, '/' ), $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'tg_stripe_network', __( 'Stripe could not be reached. Please try again.', 'guidegrid-travel' ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			$message = isset( $data['error']['message'] ) ? sanitize_text_field( $data['error']['message'] ) : __( 'Stripe rejected the payment request.', 'guidegrid-travel' );
			return new WP_Error( 'tg_stripe_api', $message );
		}
		return $data;
	}

	public function create_payment( object $booking, float $amount ) {
		$customer    = TG_Bookings::customer( $booking );
		$success_url = add_query_arg(
			array(
				'booking' => $booking->booking_number,
				'payment' => 'processing',
			),
			tg_confirmation_page_url( $booking->booking_number )
		);
		$success_url .= '&session_id={CHECKOUT_SESSION_ID}';
		$cancel_url   = add_query_arg( 'payment', 'cancelled', tg_confirmation_page_url( $booking->booking_number ) );
		$tour_name    = get_the_title( (int) $booking->tour_id );

		$data = $this->request(
			'POST',
			'checkout/sessions',
			array(
				'mode'                                             => 'payment',
				'payment_method_types[0]'                          => 'card',
				'expires_at'                                       => time() + ( max( 30, TG_Payments::hold_minutes_for_method( 'stripe' ) - 1 ) * MINUTE_IN_SECONDS ),
				'success_url'                                      => $success_url,
				'cancel_url'                                       => $cancel_url,
				'client_reference_id'                              => $booking->booking_number,
				'customer_email'                                   => isset( $customer['email'] ) ? $customer['email'] : '',
				'line_items[0][price_data][currency]'              => strtolower( (string) $booking->currency ),
				'line_items[0][price_data][unit_amount]'           => $this->minor_amount( $amount, (string) $booking->currency ),
				'line_items[0][price_data][product_data][name]'    => sprintf( __( 'Booking %1$s — %2$s', 'guidegrid-travel' ), $booking->booking_number, $tour_name ),
				'line_items[0][quantity]'                          => 1,
				'metadata[booking_number]'                         => $booking->booking_number,
				'payment_intent_data[metadata][booking_number]'    => $booking->booking_number,
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['id'] ) || empty( $data['url'] ) || 0 !== strpos( (string) $data['url'], 'https://' ) ) {
			return new WP_Error( 'tg_stripe_response', __( 'Stripe returned an invalid checkout response.', 'guidegrid-travel' ) );
		}
		return array(
			'url'            => esc_url_raw( $data['url'] ),
			'transaction_id' => sanitize_text_field( $data['id'] ),
			'response'       => array(
				'id'     => sanitize_text_field( $data['id'] ),
				'object' => isset( $data['object'] ) ? sanitize_text_field( $data['object'] ) : 'checkout.session',
			),
		);
	}

	/**
	 * Expire an earlier retryable Checkout Session before issuing a new one.
	 *
	 * @param string $session_id Checkout Session ID.
	 * @return void
	 */
	public function expire_session( string $session_id ): void {
		if ( 0 === strpos( $session_id, 'cs_' ) ) {
			$this->request( 'POST', 'checkout/sessions/' . rawurlencode( $session_id ) . '/expire' );
		}
	}

	public function verify_transaction( array $data ) {
		$session_id = isset( $data['session_id'] ) ? sanitize_text_field( $data['session_id'] ) : '';
		if ( '' === $session_id || 0 !== strpos( $session_id, 'cs_' ) ) {
			return new WP_Error( 'tg_stripe_session', __( 'Invalid Stripe session.', 'guidegrid-travel' ) );
		}
		$session = $this->request( 'GET', 'checkout/sessions/' . rawurlencode( $session_id ) );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		if ( 'paid' !== ( $session['payment_status'] ?? '' ) ) {
			return new WP_Error( 'tg_stripe_unpaid', __( 'Stripe has not confirmed this payment.', 'guidegrid-travel' ) );
		}
		$currency = strtoupper( (string) ( $session['currency'] ?? '' ) );
		$amount   = (float) ( $session['amount_total'] ?? 0 ) / (float) $this->minor_amount( 1.0, $currency );
		return array(
			'valid'          => true,
			'transaction_id' => sanitize_text_field( (string) ( $session['payment_intent'] ?? $session_id ) ),
			'amount'         => $amount,
			'currency'       => $currency,
			'booking_number' => sanitize_text_field( (string) ( $session['metadata']['booking_number'] ?? $session['client_reference_id'] ?? '' ) ),
		);
	}
}

/**
 * PayPal Orders v2 hosted checkout adapter.
 */
final class TG_PayPal_Checkout_Adapter implements TG_Payment_Adapter {
	private string $client_id;
	private string $client_secret;
	private bool $sandbox;

	public function __construct( string $client_id, string $client_secret, bool $sandbox = true ) {
		$this->client_id     = trim( $client_id );
		$this->client_secret = trim( $client_secret );
		$this->sandbox       = $sandbox;
	}

	public function id(): string {
		return 'paypal';
	}

	public function label(): string {
		return __( 'PayPal', 'guidegrid-travel' );
	}

	public function description(): string {
		return __( 'Pay with PayPal using its secure international checkout.', 'guidegrid-travel' );
	}

	private function base_url(): string {
		return $this->sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
	}

	/**
	 * Request an OAuth token without persisting credentials outside settings.
	 *
	 * @return string|WP_Error
	 */
	private function access_token() {
		$cache_key = 'tg_pp_token_' . md5( $this->client_id . '|' . ( $this->sandbox ? 'sandbox' : 'live' ) );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
		$response = wp_remote_post(
			$this->base_url() . '/v1/oauth2/token',
			array(
				'timeout'     => 20,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'        => 'grant_type=client_credentials',
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'tg_paypal_network', __( 'PayPal could not be reached. Please try again.', 'guidegrid-travel' ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || empty( $data['access_token'] ) ) {
			return new WP_Error( 'tg_paypal_auth', __( 'PayPal credentials were not accepted.', 'guidegrid-travel' ) );
		}
		$ttl = max( 60, (int) ( $data['expires_in'] ?? 300 ) - 60 );
		set_transient( $cache_key, (string) $data['access_token'], $ttl );
		return (string) $data['access_token'];
	}

	/**
	 * Perform an authenticated JSON request.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   API path.
	 * @param array  $body   JSON body.
	 * @return array|WP_Error
	 */
	private function request( string $method, string $path, array $body = array() ) {
		$token = $this->access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$args = array(
			'method'      => $method,
			'timeout'     => 25,
			'redirection' => 0,
			'headers'     => array(
				'Authorization'   => 'Bearer ' . $token,
				'Content-Type'    => 'application/json',
				'Accept'          => 'application/json',
				'Prefer'          => 'return=representation',
				'PayPal-Request-Id' => wp_generate_uuid4(),
			),
		);
		if ( $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request( $this->base_url() . '/' . ltrim( $path, '/' ), $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'tg_paypal_network', __( 'PayPal could not be reached. Please try again.', 'guidegrid-travel' ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			$message = isset( $data['details'][0]['description'] ) ? sanitize_text_field( $data['details'][0]['description'] ) : ( isset( $data['message'] ) ? sanitize_text_field( $data['message'] ) : __( 'PayPal rejected the payment request.', 'guidegrid-travel' ) );
			$error = new WP_Error( 'tg_paypal_api', $message );
			$error->add_data( array( 'status' => $status, 'response' => $data ) );
			return $error;
		}
		return $data;
	}

	public function create_payment( object $booking, float $amount ) {
		$return_url = add_query_arg(
			array( 'booking' => $booking->booking_number ),
			rest_url( 'tg/v1/payments/paypal/return' )
		);
		$cancel_url = add_query_arg( 'payment', 'cancelled', tg_confirmation_page_url( $booking->booking_number ) );
		$tour_name  = wp_strip_all_tags( get_the_title( (int) $booking->tour_id ) );
		$payload    = array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array(
				array(
					'reference_id' => 'guidegrid-booking',
					'custom_id'    => $booking->booking_number,
					'description'  => substr( sprintf( '%s — %s', $booking->booking_number, $tour_name ), 0, 127 ),
					'amount'       => array(
						'currency_code' => strtoupper( (string) $booking->currency ),
						'value'         => number_format( $amount, 2, '.', '' ),
					),
				),
			),
			'payment_source' => array(
				'paypal' => array(
					'experience_context' => array(
						'brand_name'          => substr( wp_strip_all_tags( get_bloginfo( 'name' ) ), 0, 127 ),
						'user_action'         => 'PAY_NOW',
						'shipping_preference' => 'NO_SHIPPING',
						'return_url'          => $return_url,
						'cancel_url'          => $cancel_url,
					),
				),
			),
		);
		$data = $this->request( 'POST', 'v2/checkout/orders', $payload );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$approval = '';
		foreach ( (array) ( $data['links'] ?? array() ) as $link ) {
			if ( isset( $link['rel'], $link['href'] ) && in_array( $link['rel'], array( 'payer-action', 'approve' ), true ) ) {
				$approval = (string) $link['href'];
				break;
			}
		}
		if ( empty( $data['id'] ) || '' === $approval || 0 !== strpos( $approval, 'https://' ) ) {
			return new WP_Error( 'tg_paypal_response', __( 'PayPal returned an invalid checkout response.', 'guidegrid-travel' ) );
		}
		return array(
			'url'            => esc_url_raw( $approval ),
			'transaction_id' => sanitize_text_field( $data['id'] ),
			'response'       => array(
				'id'     => sanitize_text_field( $data['id'] ),
				'status' => sanitize_text_field( (string) ( $data['status'] ?? '' ) ),
			),
		);
	}

	/**
	 * Capture an approved order, or retrieve an already-completed order.
	 *
	 * @param string $order_id PayPal order ID.
	 * @return array|WP_Error
	 */
	private function capture( string $order_id ) {
		$result = $this->request( 'POST', 'v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture' );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && 422 === (int) ( $data['status'] ?? 0 ) ) {
				$retrieved = $this->request( 'GET', 'v2/checkout/orders/' . rawurlencode( $order_id ) );
				if ( ! is_wp_error( $retrieved ) && 'COMPLETED' === ( $retrieved['status'] ?? '' ) ) {
					return $retrieved;
				}
			}
		}
		return $result;
	}

	public function verify_transaction( array $data ) {
		$order_id = isset( $data['order_id'] ) ? sanitize_text_field( $data['order_id'] ) : '';
		if ( '' === $order_id || ! preg_match( '/^[A-Z0-9-]{8,32}$/i', $order_id ) ) {
			return new WP_Error( 'tg_paypal_order', __( 'Invalid PayPal order.', 'guidegrid-travel' ) );
		}
		$order = $this->capture( $order_id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		if ( 'COMPLETED' !== ( $order['status'] ?? '' ) ) {
			return new WP_Error( 'tg_paypal_incomplete', __( 'PayPal has not completed this payment.', 'guidegrid-travel' ) );
		}
		$unit     = $order['purchase_units'][0] ?? array();
		$payments = $unit['payments']['captures'] ?? array();
		$capture  = ! empty( $payments ) ? end( $payments ) : array();
		$money    = ! empty( $capture['amount'] ) ? $capture['amount'] : ( $unit['amount'] ?? array() );
		if ( ! empty( $capture ) && 'COMPLETED' !== ( $capture['status'] ?? '' ) ) {
			return new WP_Error( 'tg_paypal_capture', __( 'PayPal has not completed the capture.', 'guidegrid-travel' ) );
		}
		return array(
			'valid'          => true,
			'transaction_id' => sanitize_text_field( (string) ( $capture['id'] ?? $order_id ) ),
			'amount'         => (float) ( $money['value'] ?? 0 ),
			'currency'       => strtoupper( sanitize_text_field( (string) ( $money['currency_code'] ?? '' ) ) ),
			'booking_number' => sanitize_text_field( (string) ( $unit['custom_id'] ?? '' ) ),
			'order_id'       => $order_id,
		);
	}
}

/**
 * Payment records, gateway initiation, and verified completion.
 */
final class TG_Payments {
	private static function tbl(): string {
		return TG_Database::table( 'payments' );
	}

	/**
	 * Gateways are hidden until enabled and fully configured.
	 *
	 * @return array<string,TG_Payment_Adapter>
	 */
	public static function adapters(): array {
		$settings = tg_settings();
		$adapters = array();
		if ( ! empty( $settings['stripe_enabled'] ) && ! empty( $settings['stripe_secret_key'] ) && ! empty( $settings['stripe_webhook_secret'] ) ) {
			$adapters['stripe'] = new TG_Stripe_Checkout_Adapter( (string) $settings['stripe_secret_key'] );
		}
		if ( ! empty( $settings['paypal_enabled'] ) && ! empty( $settings['paypal_client_id'] ) && ! empty( $settings['paypal_client_secret'] ) ) {
			$adapters['paypal'] = new TG_PayPal_Checkout_Adapter(
				(string) $settings['paypal_client_id'],
				(string) $settings['paypal_client_secret'],
				! empty( $settings['paypal_sandbox'] )
			);
		}
		if ( ! empty( $settings['test_gateway_enabled'] ) && tg_test_gateway_allowed() ) {
			$adapters['test_gateway'] = new TG_Test_Payment_Adapter();
		}
		return (array) apply_filters( 'tg_payment_adapters', $adapters );
	}

	/**
	 * @return array<string,string>
	 */
	public static function manual_methods(): array {
		$settings = tg_settings();
		$labels   = (array) $settings['manual_payment_methods'];
		$enabled  = (array) $settings['manual_payment_enabled'];
		$methods  = array();
		foreach ( $labels as $key => $label ) {
			$key = sanitize_key( $key );
			if ( $key && ! empty( $enabled[ $key ] ) && '' !== trim( (string) $label ) ) {
				$methods[ $key ] = (string) $label;
			}
		}
		return $methods;
	}

	public static function manual_instructions( string $method ): string {
		$settings = tg_settings();
		return isset( $settings['manual_payment_instructions'][ $method ] ) ? (string) $settings['manual_payment_instructions'][ $method ] : '';
	}

	/**
	 * Cash/pay-later bookings reserve seats immediately and do not use a short
	 * online-payment hold. Sites can alter this set for custom methods.
	 */
	public static function is_deferred_manual( string $method ): bool {
		$deferred = (array) apply_filters( 'tg_deferred_manual_payment_methods', array( 'cash', 'paylater' ) );
		return in_array( $method, $deferred, true ) && isset( self::manual_methods()[ $method ] );
	}

	/**
	 * Online holds outlive hosted checkout sessions by at least one minute.
	 * Stripe requires a minimum 30-minute Checkout Session lifetime.
	 */
	public static function hold_minutes_for_method( string $method ): int {
		$minutes = max( 1, (int) tg_settings()['hold_minutes'] );
		return isset( self::adapters()[ $method ] ) ? max( 31, $minutes ) : $minutes;
	}

	public static function method_exists( string $method ): bool {
		return isset( self::manual_methods()[ $method ] ) || isset( self::adapters()[ $method ] );
	}

	public static function method_label( string $method ): string {
		$manual = self::manual_methods();
		if ( isset( $manual[ $method ] ) ) {
			return $manual[ $method ];
		}
		$adapters = self::adapters();
		return isset( $adapters[ $method ] ) ? $adapters[ $method ]->label() : $method;
	}

	/**
	 * @param int   $booking_id Booking ID.
	 * @param array $args       Payment fields.
	 * @return int
	 */
	public static function record( int $booking_id, array $args ): int {
		$global = $GLOBALS['wpdb'];
		$now    = current_time( 'mysql', true );
		$global->insert(
			self::tbl(),
			array(
				'booking_id'       => $booking_id,
				'gateway'          => isset( $args['gateway'] ) ? sanitize_text_field( $args['gateway'] ) : 'manual',
				'transaction_id'   => isset( $args['transaction_id'] ) ? sanitize_text_field( $args['transaction_id'] ) : '',
				'amount'           => isset( $args['amount'] ) ? (float) $args['amount'] : 0,
				'currency'         => isset( $args['currency'] ) ? strtoupper( sanitize_text_field( $args['currency'] ) ) : '',
				'status'           => isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'pending',
				'payment_method'   => isset( $args['method'] ) ? sanitize_key( $args['method'] ) : '',
				'gateway_response' => isset( $args['response'] ) ? wp_json_encode( $args['response'] ) : null,
				'paid_at'          => isset( $args['paid_at'] ) ? $args['paid_at'] : null,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $global->insert_id;
	}

	/** @return object[] */
	public static function get_for_booking( int $booking_id ): array {
		$global = $GLOBALS['wpdb'];
		return (array) $global->get_results( $global->prepare( 'SELECT * FROM ' . self::tbl() . ' WHERE booking_id = %d ORDER BY id DESC', $booking_id ) );
	}

	/** @return object|null */
	public static function latest_for_booking( int $booking_id ) {
		$rows = self::get_for_booking( $booking_id );
		return $rows ? $rows[0] : null;
	}

	/**
	 * Start an enabled hosted checkout and attach its order/session ID to the
	 * pending payment row.
	 *
	 * @return array|WP_Error
	 */
	public static function initiate( object $booking, string $method ) {
		$adapters = self::adapters();
		if ( ! isset( $adapters[ $method ] ) ) {
			return new WP_Error( 'tg_gateway_unavailable', __( 'That online payment method is not available.', 'guidegrid-travel' ) );
		}
		$global = $GLOBALS['wpdb'];
		$row    = $global->get_row(
			$global->prepare(
				'SELECT id, transaction_id FROM ' . self::tbl() . ' WHERE booking_id = %d AND gateway = %s AND status = %s ORDER BY id DESC LIMIT 1',
				(int) $booking->id,
				$method,
				'pending'
			)
		);
		if ( ! $row ) {
			return new WP_Error( 'tg_payment_record_missing', __( 'The pending payment record could not be found.', 'guidegrid-travel' ) );
		}
		if ( 'stripe' === $method && ! empty( $row->transaction_id ) && ! empty( $row->transaction_id ) && method_exists( $adapters[ $method ], 'expire_session' ) ) {
			$adapters[ $method ]->expire_session( (string) $row->transaction_id );
		}

		$result = $adapters[ $method ]->create_payment( $booking, (float) $booking->total );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $row ) {
			$global->update(
				self::tbl(),
				array(
					'transaction_id'   => sanitize_text_field( (string) $result['transaction_id'] ),
					'gateway_response' => wp_json_encode( $result['response'] ?? array() ),
					'updated_at'       => current_time( 'mysql', true ),
				),
				array( 'id' => (int) $row->id )
			);
		}
		return $result;
	}

	/**
	 * Ensure verified gateway details exactly match the booking.
	 *
	 * @param object $booking Booking.
	 * @param array  $verified Verified transaction data.
	 * @return true|WP_Error
	 */
	public static function validate_verified_payment( object $booking, array $verified ) {
		if ( empty( $verified['valid'] ) ) {
			return new WP_Error( 'tg_payment_unverified', __( 'The payment could not be verified.', 'guidegrid-travel' ) );
		}
		if ( ! empty( $verified['booking_number'] ) && ! hash_equals( (string) $booking->booking_number, (string) $verified['booking_number'] ) ) {
			return new WP_Error( 'tg_payment_booking_mismatch', __( 'The payment does not match this booking.', 'guidegrid-travel' ) );
		}
		if ( ! empty( $verified['currency'] ) && strtoupper( (string) $verified['currency'] ) !== strtoupper( (string) $booking->currency ) ) {
			return new WP_Error( 'tg_payment_currency_mismatch', __( 'The payment currency does not match this booking.', 'guidegrid-travel' ) );
		}
		if ( abs( (float) $verified['amount'] - (float) $booking->total ) > 0.01 ) {
			return new WP_Error( 'tg_payment_amount_mismatch', __( 'The payment amount does not match this booking.', 'guidegrid-travel' ) );
		}
		if ( empty( $verified['transaction_id'] ) ) {
			return new WP_Error( 'tg_payment_reference_missing', __( 'The gateway did not return a transaction reference.', 'guidegrid-travel' ) );
		}
		return true;
	}

	/**
	 * Mark a verified payment paid. Duplicate transaction callbacks are no-ops.
	 *
	 * @return true|WP_Error
	 */
	public static function mark_paid( int $booking_id, string $gateway, string $txn_id, float $amount, string $method = '' ) {
		$booking = TG_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'tg_booking_missing', __( 'Booking not found.', 'guidegrid-travel' ) );
		}
		$global   = $GLOBALS['wpdb'];
		$existing = $txn_id ? $global->get_row(
			$global->prepare( 'SELECT id, booking_id FROM ' . self::tbl() . ' WHERE transaction_id = %s AND status = %s LIMIT 1', $txn_id, 'paid' )
		) : null;
		if ( $existing ) {
			return (int) $existing->booking_id === $booking_id
				? true
				: new WP_Error( 'tg_payment_reference_used', __( 'That transaction reference is already assigned to another booking.', 'guidegrid-travel' ) );
		}
		if ( 'paid' === $booking->payment_status ) {
			return true;
		}

		self::record(
			$booking_id,
			array(
				'gateway'        => $gateway,
				'method'         => $method ? $method : $gateway,
				'transaction_id' => $txn_id,
				'amount'         => $amount,
				'currency'       => $booking->currency,
				'status'         => 'paid',
				'paid_at'        => current_time( 'mysql', true ),
			)
		);
		TG_Bookings::set_payment_status( $booking_id, 'paid' );
		$global->update( TG_Database::table( 'bookings' ), array( 'hold_expires_at' => null ), array( 'id' => $booking_id ) );

		$guests = (int) $booking->adult_count + (int) $booking->child_count + (int) $booking->infant_count;
		if ( $guests > 0 && in_array( $booking->booking_status, array( 'pending', 'awaiting_payment' ), true ) ) {
			TG_Availability::confirm_held( (int) $booking->tour_id, $booking->booking_date, $guests );
			TG_Bookings::update_status( $booking_id, 'confirmed', 'payment_received' );
		}
		do_action( 'tg_payment_completed', $booking_id, $gateway, $txn_id );
		TG_Emails::payment_received( TG_Bookings::get( $booking_id ) );
		return true;
	}

	/**
	 * Record a declined payment attempt without cancelling the booking hold.
	 *
	 * @return true|WP_Error
	 */
	public static function mark_failed( int $booking_id, string $gateway, string $txn_id, array $response = array() ) {
		$booking = TG_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'tg_booking_missing', __( 'Booking not found.', 'guidegrid-travel' ) );
		}
		if ( 'paid' === (string) $booking->payment_status ) {
			return new WP_Error( 'tg_booking_already_paid', __( 'This booking is already paid.', 'guidegrid-travel' ) );
		}

		$global = $GLOBALS['wpdb'];
		$latest = self::latest_for_booking( $booking_id );
		if ( $latest && $gateway === (string) $latest->gateway && 'pending' === (string) $latest->status ) {
			$global->update(
				self::tbl(),
				array(
					'transaction_id'   => sanitize_text_field( $txn_id ),
					'status'           => 'failed',
					'gateway_response' => wp_json_encode( $response ),
					'updated_at'       => current_time( 'mysql', true ),
				),
				array( 'id' => (int) $latest->id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			self::record(
				$booking_id,
				array(
					'gateway'        => $gateway,
					'method'         => $gateway,
					'transaction_id' => $txn_id,
					'amount'         => (float) $booking->total,
					'currency'       => (string) $booking->currency,
					'status'         => 'failed',
					'response'       => $response,
				)
			);
		}
		TG_Bookings::set_payment_status( $booking_id, 'failed' );
		do_action( 'tg_payment_failed', $booking_id, $gateway, $txn_id );
		return true;
	}

	/**
	 * Handle a verified Stripe event. Browser redirects never settle payment.
	 *
	 * @return array{success:bool,message:string}
	 */
	public static function handle_stripe_webhook( string $raw_body, string $signature ): array {
		$secret = (string) tg_settings()['stripe_webhook_secret'];
		if ( '' === $secret ) {
			return array( 'success' => false, 'message' => 'Stripe webhook is not configured.' );
		}
		$timestamp = 0;
		$signatures = array();
		foreach ( explode( ',', $signature ) as $part ) {
			$pair = array_map( 'trim', explode( '=', $part, 2 ) );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}
		if ( ! $timestamp || abs( time() - $timestamp ) > 300 || empty( $signatures ) ) {
			return array( 'success' => false, 'message' => 'Invalid Stripe signature.' );
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );
		$valid    = false;
		foreach ( $signatures as $candidate ) {
			if ( hash_equals( $expected, (string) $candidate ) ) {
				$valid = true;
				break;
			}
		}
		if ( ! $valid ) {
			return array( 'success' => false, 'message' => 'Invalid Stripe signature.' );
		}

		$event = json_decode( $raw_body, true );
		$type  = is_array( $event ) ? (string) ( $event['type'] ?? '' ) : '';
		if ( ! in_array( $type, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true ) ) {
			return array( 'success' => true, 'message' => 'Event acknowledged.' );
		}
		$session = $event['data']['object'] ?? array();
		if ( ! is_array( $session ) || 'paid' !== ( $session['payment_status'] ?? '' ) ) {
			return array( 'success' => true, 'message' => 'Payment is not paid yet.' );
		}
		$number  = sanitize_text_field( (string) ( $session['metadata']['booking_number'] ?? $session['client_reference_id'] ?? '' ) );
		$booking = $number ? TG_Bookings::by_number( $number ) : null;
		if ( ! $booking ) {
			return array( 'success' => false, 'message' => 'Booking not found.' );
		}
		$session_id      = sanitize_text_field( (string) ( $session['id'] ?? '' ) );
		$session_matches = false;
		foreach ( self::get_for_booking( (int) $booking->id ) as $payment ) {
			if ( 'stripe' === $payment->gateway && 'pending' === $payment->status && $session_id && hash_equals( (string) $payment->transaction_id, $session_id ) ) {
				$session_matches = true;
				break;
			}
		}
		if ( ! $session_matches ) {
			return array( 'success' => false, 'message' => 'Stripe session does not match the booking.' );
		}
		$currency = strtoupper( sanitize_text_field( (string) ( $session['currency'] ?? '' ) ) );
		$verified = array(
			'valid'          => true,
			'transaction_id' => sanitize_text_field( (string) ( $session['payment_intent'] ?? $session['id'] ?? '' ) ),
			'amount'         => (float) ( $session['amount_total'] ?? 0 ) / ( in_array( $currency, array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' ), true ) ? 1 : 100 ),
			'currency'       => $currency,
			'booking_number' => $number,
		);
		$checked = self::validate_verified_payment( $booking, $verified );
		if ( is_wp_error( $checked ) ) {
			return array( 'success' => false, 'message' => $checked->get_error_message() );
		}
		$settled = self::mark_paid( (int) $booking->id, 'stripe', $verified['transaction_id'], $verified['amount'], 'stripe' );
		if ( is_wp_error( $settled ) ) {
			return array( 'success' => false, 'message' => $settled->get_error_message() );
		}
		if ( ! empty( $event['id'] ) ) {
			TG_Bookings::set_meta( (int) $booking->id, 'stripe_event_' . md5( (string) $event['id'] ), '1' );
		}
		return array( 'success' => true, 'message' => 'Payment verified.' );
	}

	/**
	 * Legacy signed custom-adapter webhook.
	 *
	 * @return array{success:bool,message:string}
	 */
	public static function handle_webhook( string $raw_body, array $headers ): array {
		$secret = (string) tg_settings()['webhook_secret'];
		if ( '' === $secret ) {
			return array( 'success' => false, 'message' => 'Webhook secret is not configured.' );
		}
		$signature = isset( $headers['tg-signature'] ) ? $headers['tg-signature'] : ( $headers['x-tg-signature'] ?? '' );
		if ( ! hash_equals( hash_hmac( 'sha256', $raw_body, $secret ), (string) $signature ) ) {
			return array( 'success' => false, 'message' => 'Invalid signature.' );
		}
		$data    = json_decode( $raw_body, true );
		$number  = is_array( $data ) ? sanitize_text_field( (string) ( $data['booking_number'] ?? '' ) ) : '';
		$booking = $number ? TG_Bookings::by_number( $number ) : null;
		$gateway = is_array( $data ) ? sanitize_key( (string) ( $data['gateway'] ?? '' ) ) : '';
		$adapters = self::adapters();
		if ( ! $booking || ! isset( $adapters[ $gateway ] ) ) {
			return array( 'success' => false, 'message' => 'Unknown booking or gateway.' );
		}
		$verified = $adapters[ $gateway ]->verify_transaction( $data );
		if ( is_wp_error( $verified ) ) {
			return array( 'success' => false, 'message' => $verified->get_error_message() );
		}
		$checked = self::validate_verified_payment( $booking, $verified );
		if ( is_wp_error( $checked ) ) {
			return array( 'success' => false, 'message' => $checked->get_error_message() );
		}
		$result = self::mark_paid( (int) $booking->id, $gateway, (string) $verified['transaction_id'], (float) $verified['amount'], $gateway );
		return is_wp_error( $result ) ? array( 'success' => false, 'message' => $result->get_error_message() ) : array( 'success' => true, 'message' => 'Payment verified.' );
	}

	/**
	 * Record a refund. Gateway refund API calls remain an administrator action.
	 *
	 * @return true|WP_Error
	 */
	public static function refund( int $booking_id, float $amount, string $reason, string $ref = '' ) {
		$booking = TG_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'tg_booking_missing', __( 'Booking not found.', 'guidegrid-travel' ) );
		}
		$amount = min( $amount, (float) $booking->total );
		$last   = self::latest_for_booking( $booking_id );
		self::record(
			$booking_id,
			array(
				'gateway'        => $last ? $last->gateway : 'manual',
				'method'         => $last ? $last->payment_method : 'manual',
				'transaction_id' => $ref,
				'amount'         => -abs( $amount ),
				'currency'       => $booking->currency,
				'status'         => 'refunded',
				'response'       => array( 'reason' => $reason ),
			)
		);
		if ( ! in_array( $booking->booking_status, array( 'refund_requested', 'cancelled' ), true ) ) {
			$requested = TG_Bookings::update_status( $booking_id, 'refund_requested', $reason );
			if ( is_wp_error( $requested ) ) {
				return $requested;
			}
		}
		$refunded = TG_Bookings::update_status( $booking_id, 'refunded', $reason );
		if ( is_wp_error( $refunded ) ) {
			return $refunded;
		}
		TG_Bookings::set_payment_status( $booking_id, 'refunded' );
		TG_Emails::refund_processed( TG_Bookings::get( $booking_id ), $amount, $reason );
		do_action( 'tg_booking_refunded', $booking_id, $amount, $reason );
		return true;
	}
}

/**
 * Process an explicit simulator decision before any template output.
 */
function tg_handle_test_payment_decision(): void {
	if ( ! is_page_template( 'page-templates/template-confirmation.php' ) || 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
		return;
	}
	$action = isset( $_POST['tg_payment_action'] ) ? sanitize_key( wp_unslash( $_POST['tg_payment_action'] ) ) : '';
	if ( 'test_decision' !== $action ) {
		return;
	}

	$number   = isset( $_POST['booking_number'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['booking_number'] ) ) ) : '';
	$decision = isset( $_POST['test_decision'] ) ? sanitize_key( wp_unslash( $_POST['test_decision'] ) ) : '';
	$nonce    = isset( $_POST['tg_payment_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['tg_payment_nonce'] ) ) : '';
	$booking  = $number ? TG_Bookings::by_number( $number ) : null;
	$target   = $number ? tg_confirmation_page_url( $number ) : home_url( '/' );

	if ( ! $booking || ! is_user_logged_in() || ! wp_verify_nonce( $nonce, 'tg_test_payment_' . $number ) ) {
		wp_safe_redirect( add_query_arg( 'payment', 'invalid', $target ) );
		exit;
	}

	$owns_booking = current_user_can( 'manage_tg_bookings' );
	$user         = wp_get_current_user();
	if ( ! $owns_booking ) {
		foreach ( TG_Bookings::get_for_user( (int) $user->ID, $user->user_email ) as $owned ) {
			if ( (int) $owned->id === (int) $booking->id ) {
				$owns_booking = true;
				break;
			}
		}
	}
	$latest       = TG_Payments::latest_for_booking( (int) $booking->id );
	$settings     = tg_settings();
	$hold_expired = $booking->hold_expires_at && (string) $booking->hold_expires_at < current_time( 'mysql', true );
	if ( ! $owns_booking || empty( $settings['test_gateway_enabled'] ) || ! tg_test_gateway_allowed() || ! $latest || 'test_gateway' !== (string) $latest->payment_method || $hold_expired || ! in_array( (string) $booking->booking_status, array( 'pending', 'awaiting_payment' ), true ) ) {
		wp_safe_redirect( add_query_arg( 'payment', 'unavailable', $target ) );
		exit;
	}

	if ( 'paid' === (string) $booking->payment_status ) {
		wp_safe_redirect( add_query_arg( 'payment', 'success', $target ) );
		exit;
	}

	if ( 'approve' === $decision ) {
		$transaction_id = 'TEST-APPROVED-' . strtoupper( wp_generate_password( 12, false, false ) );
		$result         = TG_Payments::mark_paid( (int) $booking->id, 'test_gateway', $transaction_id, (float) $booking->total, 'test_gateway' );
		$outcome        = is_wp_error( $result ) ? 'failed' : 'success';
	} elseif ( 'decline' === $decision ) {
		$transaction_id = 'TEST-DECLINED-' . strtoupper( wp_generate_password( 12, false, false ) );
		$result         = TG_Payments::mark_failed(
			(int) $booking->id,
			'test_gateway',
			$transaction_id,
			array( 'simulated' => true, 'decision' => 'decline' )
		);
		$outcome = is_wp_error( $result ) ? 'failed' : 'declined';
	} else {
		$outcome = 'invalid';
	}

	wp_safe_redirect( add_query_arg( 'payment', $outcome, $target ) );
	exit;
}
add_action( 'template_redirect', 'tg_handle_test_payment_decision', 7 );

/**
 * Restart an interrupted hosted checkout for an authenticated booking owner.
 *
 * @return void
 */
function tg_restart_online_payment(): void {
	if ( ! is_page_template( 'page-templates/template-confirmation.php' ) || 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
		return;
	}
	$action = isset( $_POST['tg_payment_action'] ) ? sanitize_key( wp_unslash( $_POST['tg_payment_action'] ) ) : '';
	if ( 'restart' !== $action ) {
		return;
	}

	$number  = isset( $_POST['booking_number'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['booking_number'] ) ) ) : '';
	$booking = $number ? TG_Bookings::by_number( $number ) : null;
	$target  = $number ? tg_confirmation_page_url( $number ) : home_url( '/' );
	$nonce   = isset( $_POST['tg_payment_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['tg_payment_nonce'] ) ) : '';
	if ( ! $booking || ! is_user_logged_in() || ! wp_verify_nonce( $nonce, 'tg_restart_payment_' . $number ) ) {
		wp_safe_redirect( add_query_arg( 'payment', 'failed', $target ) );
		exit;
	}

	$owns_booking = false;
	$user         = wp_get_current_user();
	foreach ( TG_Bookings::get_for_user( (int) $user->ID, $user->user_email ) as $owned ) {
		if ( (int) $owned->id === (int) $booking->id ) {
			$owns_booking = true;
			break;
		}
	}
	$payment     = TG_Payments::latest_for_booking( (int) $booking->id );
	$method      = $payment ? (string) $payment->payment_method : '';
	$hold_expired = $booking->hold_expires_at && (string) $booking->hold_expires_at < current_time( 'mysql', true );
	if ( ! $owns_booking || $hold_expired || ! in_array( $booking->booking_status, array( 'pending', 'awaiting_payment' ), true ) || ! isset( TG_Payments::adapters()[ $method ] ) ) {
		wp_safe_redirect( add_query_arg( 'payment', 'failed', $target ) );
		exit;
	}

	$result = TG_Payments::initiate( $booking, $method );
	if ( is_wp_error( $result ) || empty( $result['url'] ) ) {
		wp_safe_redirect( add_query_arg( 'payment', 'failed', $target ) );
		exit;
	}
	wp_redirect( esc_url_raw( $result['url'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- validated adapter-hosted HTTPS URL.
	exit;
}
add_action( 'template_redirect', 'tg_restart_online_payment', 8 );
