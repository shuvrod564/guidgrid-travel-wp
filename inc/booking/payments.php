<?php
/**
 * Payment layer.
 *
 * Bookings never talk to a gateway directly — they talk to the payment
 * record and a pluggable adapter. Built-in methods are the manual
 * methods (bank transfer, cash, pay later). Online gateways implement
 * TG_Payment_Adapter and are registered through the tg_payment_adapters
 * filter. An example skeleton gateway is included and disabled by
 * default.
 *
 * Sensitive card data is never stored: only gateway, transaction
 * reference, amount and status.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Interface for online payment gateways.
 */
interface TG_Payment_Adapter {

	/**
	 * Unique adapter ID.
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Display label.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Short description shown at checkout.
	 *
	 * @return string
	 */
	public function description(): string;

	/**
	 * Create an online payment intent for a booking.
	 *
	 * @param object $booking Booking row.
	 * @param float  $amount  Amount to charge.
	 * @return array|WP_Error { url: checkout URL } on success.
	 */
	public function create_payment( object $booking, float $amount );

	/**
	 * Verify a transaction server-side (webhook / return URL).
	 *
	 * @param array $data Gateway payload (raw, untrusted).
	 * @return array|WP_Error { valid: bool, transaction_id: string, amount: float }
	 */
	public function verify_transaction( array $data );
}

/**
 * Skeleton online gateway for integration reference.
 *
 * Activate by adding:
 *   add_filter( 'tg_payment_adapters', function ( $a ) { $a['example_gateway'] = new TG_Payment_Example_Gateway(); return $a; } );
 */
class TG_Payment_Example_Gateway implements TG_Payment_Adapter {

	public function id(): string {
		return 'example_gateway';
	}

	public function label(): string {
		return __( 'Example Online Payment (setup required)', 'guidegrid-travel' );
	}

	public function description(): string {
		return __( 'Integrate your payment gateway in inc/booking/payments.php.', 'guidegrid-travel' );
	}

	public function create_payment( object $booking, float $amount ) {
		return new WP_Error( 'tg_gateway_unconfigured', __( 'This payment method is not configured yet.', 'guidegrid-travel' ) );
	}

	public function verify_transaction( array $data ) {
		return new WP_Error( 'tg_gateway_unconfigured', __( 'Payment verification is not configured.', 'guidegrid-travel' ) );
	}
}

/**
 * Class TG_Payments
 */
final class TG_Payments {

	/**
	 * Table name (prefixed).
	 *
	 * @return string
	 */
	private static function tbl(): string {
		return TG_Database::table( 'payments' );
	}

	/**
	 * Registered online gateway adapters.
	 *
	 * @return array<string,TG_Payment_Adapter>
	 */
	public static function adapters(): array {
		$adapters = array();

		/**
		 * Filter the available payment gateway adapters.
		 *
		 * @param array $adapters Adapter id => adapter instance.
		 */
		$adapters = apply_filters( 'tg_payment_adapters', $adapters );

		return $adapters;
	}

	/**
	 * Manual payment methods offered at checkout.
	 *
	 * @return array<string,string> method key => label.
	 */
	public static function manual_methods(): array {
		$settings = tg_settings();
		return (array) $settings['manual_payment_methods'];
	}

	/**
	 * Insert a payment record.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $args       { gateway, method, amount, currency, status, transaction_id, response, paid_at }.
	 * @return int Payment record ID.
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
				'currency'         => isset( $args['currency'] ) ? sanitize_text_field( $args['currency'] ) : '',
				'status'           => isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : 'pending',
				'payment_method'   => isset( $args['method'] ) ? sanitize_text_field( $args['method'] ) : '',
				'gateway_response' => isset( $args['response'] ) ? wp_json_encode( $args['response'] ) : null,
				'paid_at'          => isset( $args['paid_at'] ) ? $args['paid_at'] : null,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $global->insert_id;
	}

	/**
	 * Payment history for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return object[]
	 */
	public static function get_for_booking( int $booking_id ): array {
		$global = $GLOBALS['wpdb'];
		$rows   = $global->get_results( $global->prepare( 'SELECT * FROM ' . self::tbl() . ' WHERE booking_id = %d ORDER BY id DESC', $booking_id ) );
		return (array) $rows;
	}

	/**
	 * Mark a booking as paid. Verifies nothing here — callers must verify
	 * gateway data server-side first. Safe to call twice (idempotent).
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $gateway    Gateway / method label.
	 * @param string $txn_id     Transaction reference.
	 * @param float  $amount     Amount paid.
	 * @param string $method     Manual method key (bank, cash, paylater...).
	 * @return true|WP_Error
	 */
	public static function mark_paid( int $booking_id, string $gateway, string $txn_id, float $amount, string $method = '' ) {
		$booking = TG_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'tg_booking_missing', __( 'Booking not found.', 'guidegrid-travel' ) );
		}

		if ( in_array( $booking->payment_status, array( 'paid', 'partially_paid' ), true ) && (float) $amount <= 0 ) {
			return true; // Already settled.
		}

		self::record(
			$booking_id,
			array(
				'gateway'        => $gateway,
				'method'         => $method,
				'transaction_id' => $txn_id,
				'amount'         => $amount,
				'currency'       => $booking->currency,
				'status'         => 'paid',
				'paid_at'        => current_time( 'mysql', true ),
			)
		);

		TG_Bookings::set_payment_status( $booking_id, 'paid' );

		// Move payment holds into firm reservations.
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
	 * Record a refund against a booking.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param float  $amount     Refund amount.
	 * @param string $reason     Reason.
	 * @param string $ref        Gateway refund reference.
	 * @return true|WP_Error
	 */
	public static function refund( int $booking_id, float $amount, string $reason, string $ref = '' ) {
		$booking = TG_Bookings::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'tg_booking_missing', __( 'Booking not found.', 'guidegrid-travel' ) );
		}

		$amount = min( $amount, (float) $booking->total );

		$global = $GLOBALS['wpdb'];
		$last   = $global->get_row(
			$global->prepare( 'SELECT * FROM ' . self::tbl() . ' WHERE booking_id = %d AND status = %s ORDER BY id DESC LIMIT 1', $booking_id, 'paid' )
		);

		$gateway = $last ? $last->gateway : 'manual';

		self::record(
			$booking_id,
			array(
				'gateway'        => $gateway,
				'method'         => $last ? $last->payment_method : 'manual',
				'transaction_id' => $ref,
				'amount'         => -abs( $amount ),
				'currency'       => $booking->currency,
				'status'         => 'refunded',
				'response'       => array(
					'reason' => $reason,
				),
			)
		);

		// Release seats.
		$guests = (int) $booking->adult_count + (int) $booking->child_count + (int) $booking->infant_count;
		if ( $guests > 0 ) {
			TG_Availability::release( (int) $booking->tour_id, $booking->booking_date, $guests, 'confirmed' );
		}

		TG_Bookings::set_payment_status( $booking_id, 'refunded' );
		TG_Bookings::update_status( $booking_id, 'refunded', $reason );

		TG_Emails::refund_processed( TG_Bookings::get( $booking_id ), $amount, $reason );

		do_action( 'tg_booking_refunded', $booking_id, $amount, $reason );

		return true;
	}

	/**
	 * Handle a gateway webhook payload (POST /tg/v1/payments/webhook).
	 *
	 * The payload must be signed with the configured webhook secret
	 * (HMAC-SHA256 over the raw body, header: tg-signature). Transactions
	 * are verified server-side through the adapter before any status
	 * change — a browser redirect to a success URL never marks a payment
	 * as paid.
	 *
	 * @param string      $raw_body Raw request body.
	 * @param array       $headers  Lower-cased request headers.
	 * @return array { success: bool, message: string }
	 */
	public static function handle_webhook( string $raw_body, array $headers ): array {
		$settings = tg_settings();
		$secret   = (string) $settings['webhook_secret'];

		if ( '' === $secret ) {
			return array(
				'success' => false,
				'message' => 'Webhook secret is not configured.',
			);
		}

		$signature = isset( $headers['tg-signature'] ) ? $headers['tg-signature'] : ( isset( $headers['x-tg-signature'] ) ? $headers['x-tg-signature'] : '' );
		$expected  = hash_hmac( 'sha256', $raw_body, $secret );

		if ( ! hash_equals( $expected, (string) $signature ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid signature.',
			);
		}

		$data = json_decode( $raw_body, true );
		if ( ! is_array( $data ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid payload.',
			);
		}

		$booking_number = isset( $data['booking_number'] ) ? sanitize_text_field( $data['booking_number'] ) : '';
		$booking        = $booking_number ? TG_Bookings::by_number( $booking_number ) : null;
		if ( ! $booking ) {
			return array(
				'success' => false,
				'message' => 'Booking not found.',
			);
		}

		$gateway_id = isset( $data['gateway'] ) ? sanitize_text_field( $data['gateway'] ) : '';
		$adapters   = self::adapters();
		if ( ! isset( $adapters[ $gateway_id ] ) ) {
			return array(
				'success' => false,
				'message' => 'Unknown gateway.',
			);
		}

		$verified = $adapters[ $gateway_id ]->verify_transaction( $data );
		if ( is_wp_error( $verified ) ) {
			self::record(
				(int) $booking->id,
				array(
					'gateway'        => $gateway_id,
					'status'         => 'failed',
					'amount'         => (float) $booking->total,
					'currency'       => $booking->currency,
					'transaction_id' => isset( $data['transaction_id'] ) ? sanitize_text_field( $data['transaction_id'] ) : '',
					'response'       => $data,
				)
			);
			return array(
				'success' => false,
				'message' => $verified->get_error_message(),
			);
		}

		$amount = isset( $verified['amount'] ) ? (float) $verified['amount'] : (float) $booking->total;
		self::mark_paid( (int) $booking->id, $gateway_id, (string) $verified['transaction_id'], $amount, 'online' );

		return array(
			'success' => true,
			'message' => 'Payment verified and booking updated.',
		);
	}
}
