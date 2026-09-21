<?php
/**
 * Bookings engine.
 *
 * Central create/update flow with:
 *  - server-side price recalculation (TG_Pricing),
 *  - double-booking protection (TG_Availability row locks),
 *  - human-readable unique booking numbers (no exposed sequential IDs),
 *  - immutable price snapshots,
 *  - controlled status transitions,
 *  - configurable payment holds with cron expiry.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TG_Bookings
 */
final class TG_Bookings {

	const NUMBER_PREFIX = 'TG';

	/**
	 * Booking status labels.
	 *
	 * @return array<string,string>
	 */
	public static function status_labels(): array {
		return array(
			'pending'           => __( 'Pending', 'guidegrid-travel' ),
			'awaiting_payment'  => __( 'Awaiting Payment', 'guidegrid-travel' ),
			'confirmed'         => __( 'Confirmed', 'guidegrid-travel' ),
			'partially_paid'    => __( 'Partially Paid', 'guidegrid-travel' ),
			'paid'              => __( 'Paid', 'guidegrid-travel' ),
			'on_hold'           => __( 'On Hold', 'guidegrid-travel' ),
			'completed'         => __( 'Completed', 'guidegrid-travel' ),
			'cancelled'         => __( 'Cancelled', 'guidegrid-travel' ),
			'refund_requested'  => __( 'Refund Requested', 'guidegrid-travel' ),
			'refunded'          => __( 'Refunded', 'guidegrid-travel' ),
			'failed'            => __( 'Failed', 'guidegrid-travel' ),
			'expired'           => __( 'Expired', 'guidegrid-travel' ),
		);
	}

	/**
	 * Payment status labels.
	 *
	 * @return array<string,string>
	 */
	public static function payment_labels(): array {
		return array(
			'unpaid'         => __( 'Unpaid', 'guidegrid-travel' ),
			'pending'        => __( 'Pending', 'guidegrid-travel' ),
			'paid'           => __( 'Paid', 'guidegrid-travel' ),
			'partially_paid' => __( 'Partially Paid', 'guidegrid-travel' ),
			'refunded'       => __( 'Refunded', 'guidegrid-travel' ),
			'failed'         => __( 'Failed', 'guidegrid-travel' ),
			'cancelled'      => __( 'Cancelled', 'guidegrid-travel' ),
		);
	}

	/**
	 * Allowed status transitions (from => [to...]). "any" allows all.
	 *
	 * @return array<string,array|string>
	 */
	public static function transitions(): array {
		return array(
			'pending'          => array( 'awaiting_payment', 'confirmed', 'on_hold', 'cancelled', 'failed', 'expired' ),
			'awaiting_payment' => array( 'paid', 'confirmed', 'on_hold', 'cancelled', 'expired', 'failed' ),
			'confirmed'        => array( 'paid', 'partially_paid', 'on_hold', 'completed', 'cancelled', 'refund_requested' ),
			'partially_paid'   => array( 'paid', 'completed', 'cancelled', 'refund_requested' ),
			'paid'             => array( 'completed', 'cancelled', 'refund_requested' ),
			'on_hold'          => array( 'pending', 'confirmed', 'cancelled', 'expired' ),
			'completed'        => array( 'refund_requested' ),
			'cancelled'        => array( 'refunded' ),
			'refund_requested' => array( 'refunded', 'confirmed', 'cancelled' ),
			'refunded'         => array(),
			'failed'           => array( 'pending' ),
			'expired'          => array(),
		);
	}

	/**
	 * Bookings table (prefixed).
	 *
	 * @return string
	 */
	private static function tbl(): string {
		return TG_Database::table( 'bookings' );
	}

	/**
	 * Generate the next unique booking number (e.g. TG-20260921-4F7A2).
	 *
	 * The suffix is random (not a sequential ID) and uniqueness is
	 * enforced in a retry loop.
	 *
	 * @return string
	 */
	public static function next_number(): string {
		$global = $GLOBALS['wpdb'];
		$day    = gmdate( 'Ymd', time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );

		for ( $i = 0; $i < 5; $i++ ) {
			$number = self::NUMBER_PREFIX . '-' . $day . '-' . strtoupper( substr( md5( wp_rand() . microtime() . wp_uniqid() ), 0, 5 ) );
			$exists = $global->get_var( $global->prepare( 'SELECT id FROM ' . self::tbl() . ' WHERE booking_number = %s LIMIT 1', $number ) );
			if ( ! $exists ) {
				return $number;
			}
		}

		// Ultra-rare fallback: microseconds suffix.
		return self::NUMBER_PREFIX . '-' . $day . '-' . strtoupper( substr( (string) microtime( true ), -5 ) );
	}

	/**
	 * Create a booking.
	 *
	 * @param array $data {
	 *   @type int    $tour_id       Required.
	 *   @type string $date          Required (Y-m-d).
	 *   @type int    $adults        Required, >= 1.
	 *   @type int    $children      Optional.
	 *   @type int    $infants       Optional.
	 *   @type int[]  $addons        Optional add-on IDs.
	 *   @type string $coupon        Optional coupon code.
	 *   @type string $payment_method Manual method key or adapter id.
	 *   @type array  $customer      { first_name, last_name, email, phone, country, address, special_request, emergency_contact }.
	 *   @type int    $user_id       Optional WP user ID (0 = guest).
	 *   @type bool   $admin_paid    Admin created as already paid.
	 *   @type string $source        frontend|admin|rest.
	 * }
	 * @return object|WP_Error Booking row.
	 */
	public static function create( array $data ) {
		$settings = tg_settings();
		$global   = $GLOBALS['wpdb'];

		// ---- Quote (server-side price recalculation). ----
		$quote = TG_Pricing::calculate_quote(
			array(
				'tour_id'  => isset( $data['tour_id'] ) ? $data['tour_id'] : 0,
				'date'     => isset( $data['date'] ) ? $data['date'] : '',
				'adults'   => isset( $data['adults'] ) ? $data['adults'] : 0,
				'children' => isset( $data['children'] ) ? $data['children'] : 0,
				'infants'  => isset( $data['infants'] ) ? $data['infants'] : 0,
				'addons'   => isset( $data['addons'] ) ? $data['addons'] : array(),
				'coupon'   => isset( $data['coupon'] ) ? $data['coupon'] : '',
				'email'    => isset( $data['customer']['email'] ) ? $data['customer']['email'] : '',
			)
		);

		if ( ! $quote['valid'] ) {
			return new WP_Error( 'tg_quote_invalid', implode( ' ', $quote['errors'] ) );
		}

		$tour_id  = $quote['tour_id'];
		$date     = $quote['date'];
		$guests   = $quote['adults'] + $quote['children'] + $quote['infants'];
		$customer = self::sanitize_customer( isset( $data['customer'] ) ? (array) $data['customer'] : array() );

		if ( '' === $customer['email'] ) {
			return new WP_Error( 'tg_customer_email', __( 'Please provide a valid email address.', 'guidegrid-travel' ) );
		}

		$admin_paid = ! empty( $data['admin_paid'] );
		$is_admin   = current_user_can( 'manage_tg_bookings' );
		if ( $admin_paid && ! $is_admin ) {
			$admin_paid = false;
		}

		$payment_method = isset( $data['payment_method'] ) ? sanitize_key( $data['payment_method'] ) : 'bank';
		$online         = array_key_exists( $payment_method, TG_Payments::adapters() );

		// ---- Transaction: reserve capacity then write booking. ----
		$global->query( 'START TRANSACTION' );

		$reserved = TG_Availability::reserve( $tour_id, $date, $guests, $admin_paid ? 'confirmed' : 'hold' );
		if ( is_wp_error( $reserved ) ) {
			$global->query( 'ROLLBACK' );
			return $reserved;
		}

		$now         = current_time( 'mysql', true );
		$number      = self::next_number();
		$user_id     = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0;
		$customer_id = self::upsert_customer( $customer, $user_id, (float) $quote['total'] );

		$end_date = '';
		$days     = tg_tour_duration_days( $tour_id );
		if ( $days > 1 ) {
			$end_ts = strtotime( $date . ' +' . ( $days - 1 ) . ' days' );
			if ( $end_ts ) {
				$end_date = gmdate( 'Y-m-d', $end_ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
			}
		}

		$pending  = ( ! $admin_paid );
		$now_ts   = time();
		$hold_exp = $pending ? gmdate( 'Y-m-d H:i:s', $now_ts + ( (int) $settings['hold_minutes'] * MINUTE_IN_SECONDS ) ) : null;

		$inserted = $global->insert(
			self::tbl(),
			array(
				'booking_number'  => $number,
				'customer_id'     => $customer_id,
				'tour_id'         => $tour_id,
				'booking_date'    => $date,
				'tour_end_date'   => $end_date,
				'adult_count'     => $quote['adults'],
				'child_count'     => $quote['children'],
				'infant_count'    => $quote['infants'],
				'adult_unit_price'  => $quote['prices']['adult'],
				'child_unit_price'  => $quote['prices']['child'],
				'infant_unit_price' => $quote['prices']['infant'],
				'subtotal'        => $quote['subtotal'],
				'discount'        => $quote['discount'],
				'coupon_code'     => $quote['coupon'] ? $quote['coupon']['code'] : '',
				'tax'             => $quote['tax'],
				'service_fee'     => $quote['service_fee'],
				'deposit'         => $quote['deposit'],
				'total'           => $quote['total'],
				'currency'        => $quote['currency'],
				'price_snapshot'  => wp_json_encode( $quote['price_snapshot'] ),
				'addons'          => wp_json_encode( $quote['addons'] ),
				'payment_status'  => $admin_paid ? 'paid' : ( $online ? 'pending' : 'pending' ),
				'booking_status'  => $admin_paid ? 'confirmed' : 'awaiting_payment',
				'hold_expires_at' => $hold_exp,
				'customer_data'   => wp_json_encode( $customer ),
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			TG_Availability::release( $tour_id, $date, $guests, $admin_paid ? 'confirmed' : 'hold' );
			$global->query( 'ROLLBACK' );
			return new WP_Error( 'tg_booking_insert', __( 'Your booking could not be saved. Please try again.', 'guidegrid-travel' ) );
		}

		$booking_id = (int) $global->insert_id;

		// Line items.
		foreach ( $quote['addons'] as $line ) {
			$global->insert(
				TG_Database::table( 'booking_items' ),
				array(
					'booking_id' => $booking_id,
					'item_type'  => 'addon',
					'ref_id'     => $line['id'],
					'name'       => $line['name'],
					'unit_price' => $line['price'],
					'quantity'   => $line['qty'],
					'amount'     => $line['amount'],
					'created_at' => $now,
				),
				array( '%d', '%s', '%d', '%s', '%f', '%d', '%f', '%s' )
			);
		}

		// Coupon usage.
		if ( $quote['coupon'] ) {
			$key = $customer['email'] ? strtolower( $customer['email'] ) : 'guest';
			TG_Coupons::record_usage( $quote['coupon']['id'], $booking_id, $key );
		}

		// Payment record.
		if ( $admin_paid ) {
			TG_Payments::record(
				$booking_id,
				array(
					'gateway'        => 'manual',
					'method'         => $payment_method,
					'amount'         => $quote['total'],
					'currency'       => $quote['currency'],
					'status'         => 'paid',
					'transaction_id' => 'MANUAL-' . $number,
					'paid_at'        => $now,
				)
			);
		} else {
			TG_Payments::record(
				$booking_id,
				array(
					'gateway'        => $online ? $payment_method : 'manual',
					'method'         => $payment_method,
					'amount'         => $quote['total'],
					'currency'       => $quote['currency'],
					'status'         => 'pending',
				)
			);
		}

		$global->query( 'COMMIT' );

		$booking = self::get( $booking_id );
		if ( ! $booking ) {
			TG_Availability::release( $tour_id, $date, $guests, $admin_paid ? 'confirmed' : 'hold' );
			return new WP_Error( 'tg_booking_missing', __( 'Booking could not be created.', 'guidegrid-travel' ) );
		}

		self::increment_tour_popularity( $tour_id );

		do_action( 'tg_booking_created', $booking_id, $booking );

		// Emails.
		TG_Emails::booking_received( $booking );
		TG_Emails::new_booking_admin( $booking );

		/**
		 * Filter the created booking ID.
		 *
		 * @param int $booking_id Booking ID.
		 */
		apply_filters( 'tg_booking_created_id', $booking_id );

		return $booking;
	}

	/**
	 * Sanitize + validate the customer payload.
	 *
	 * @param array $c Raw customer data.
	 * @return array
	 */
	private static function sanitize_customer( array $c ): array {
		$clean = array(
			'first_name'        => isset( $c['first_name'] ) ? sanitize_text_field( $c['first_name'] ) : '',
			'last_name'         => isset( $c['last_name'] ) ? sanitize_text_field( $c['last_name'] ) : '',
			'email'             => isset( $c['email'] ) ? sanitize_email( $c['email'] ) : '',
			'phone'             => isset( $c['phone'] ) ? sanitize_text_field( $c['phone'] ) : '',
			'country'           => isset( $c['country'] ) ? sanitize_text_field( $c['country'] ) : '',
			'address'           => isset( $c['address'] ) ? sanitize_textarea_field( $c['address'] ) : '',
			'special_request'   => isset( $c['special_request'] ) ? sanitize_textarea_field( $c['special_request'] ) : '',
			'emergency_contact' => isset( $c['emergency_contact'] ) ? sanitize_text_field( $c['emergency_contact'] ) : '',
		);

		if ( '' !== $clean['email'] && ! is_email( $clean['email'] ) ) {
			$clean['email'] = '';
		}
		return $clean;
	}

	/**
	 * Upsert the customer record (keyed by email) and refresh lifetime stats.
	 *
	 * @param array $customer  Sanitized customer data.
	 * @param int   $user_id   Optional WP user ID.
	 * @param float $total     The amount of the booking just created.
	 * @return int Customer row ID.
	 */
	private static function upsert_customer( array $customer, int $user_id, float $total = 0.0 ): int {
		$global   = $GLOBALS['wpdb'];
		$table    = TG_Database::table( 'customers' );
		$email    = strtolower( $customer['email'] );
		$name     = trim( $customer['first_name'] . ' ' . $customer['last_name'] );
		$now      = current_time( 'mysql', true );

		$existing = $global->get_row( $global->prepare( 'SELECT * FROM ' . $table . ' WHERE email = %s LIMIT 1', $email ) );

		if ( $existing ) {
			$global->update(
				$table,
				array(
					'name'      => $name ? $name : $existing->name,
					'phone'     => $customer['phone'] ? $customer['phone'] : $existing->phone,
					'country'   => $customer['country'] ? $customer['country'] : $existing->country,
					'address'   => $customer['address'] ? $customer['address'] : $existing->address,
					'user_id'   => $user_id ? $user_id : (int) $existing->user_id,
					'updated_at' => $now,
				),
				array( 'id' => (int) $existing->id )
			);
			$customer_id = (int) $existing->id;
		} else {
			$global->insert(
				$table,
				array(
					'user_id'        => $user_id,
					'email'          => $email,
					'name'           => $name,
					'phone'          => $customer['phone'],
					'country'        => $customer['country'],
					'address'        => $customer['address'],
					'account_status' => 'active',
					'created_at'     => $now,
					'updated_at'     => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			$customer_id = (int) $global->insert_id;
		}

		// Lifetime stats: count this booking and add exactly its total.
		$global->query(
			$global->prepare(
				'UPDATE ' . $table . ' SET
				 booking_count = booking_count + 1,
				 last_booking_at = %s,
				 total_spent = total_spent + %f
				 WHERE id = %d',
				$now,
				$total,
				$customer_id
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $customer_id;
	}

	/**
	 * Fetch a booking row by ID.
	 *
	 * @param int $id Booking ID.
	 * @return object|null
	 */
	public static function get( int $id ) {
		$global = $GLOBALS['wpdb'];
		return $global->get_row( $global->prepare( 'SELECT * FROM ' . self::tbl() . ' WHERE id = %d', $id ) );
	}

	/**
	 * Fetch a booking by its number.
	 *
	 * @param string $number Booking number.
	 * @return object|null
	 */
	public static function by_number( string $number ) {
		$global = $GLOBALS['wpdb'];
		return $global->get_row( $global->prepare( 'SELECT * FROM ' . self::tbl() . ' WHERE booking_number = %s LIMIT 1', trim( $number ) ) );
	}

	/**
	 * Update booking status with transition control.
	 *
	 * @param int    $id      Booking ID.
	 * @param string $status  New status.
	 * @param string $reason  Optional reason.
	 * @return true|WP_Error
	 */
	public static function update_status( int $id, string $status, string $reason = '' ) {
		$booking = self::get( $id );
		if ( ! $booking ) {
			return new WP_Error( 'tg_booking_missing', __( 'Booking not found.', 'guidegrid-travel' ) );
		}

		$from = $booking->booking_status;
		if ( $from === $status ) {
			return true;
		}

		$allowed = self::transitions();
		if ( isset( $allowed[ $from ] ) && ! in_array( $status, (array) $allowed[ $from ], true ) ) {
			return new WP_Error(
				'tg_status_transition',
				sprintf(
					/* translators: 1: from status, 2: to status */
					__( 'Status cannot change from %1$s to %2$s.', 'guidegrid-travel' ),
					$from,
					$status
				)
			);
		}

		$global = $GLOBALS['wpdb'];
		$global->update( self::tbl(), array( 'booking_status' => $status, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );

		// Release seats when the booking leaves a state that holds capacity.
		$held_states = array( 'awaiting_payment', 'confirmed', 'paid', 'partially_paid', 'on_hold', 'pending' );
		$guests      = (int) $booking->adult_count + (int) $booking->child_count + (int) $booking->infant_count;
		if ( in_array( $from, $held_states, true ) && ! in_array( $status, $held_states, true ) && $guests > 0 ) {
			$mode = ( 'pending' === $from || 'awaiting_payment' === $from || 'on_hold' === $from ) ? 'hold' : 'confirmed';
			TG_Availability::release( (int) $booking->tour_id, $booking->booking_date, $guests, $mode );
		}

		if ( 'cancelled' === $status || 'refunded' === $status ) {
			$global->update( self::tbl(), array( 'hold_expires_at' => null ), array( 'id' => $id ) );
		}

		$updated = self::get( $id );

		do_action( 'tg_booking_status_changed', $id, $from, $status, $reason );
		if ( 'cancelled' === $status ) {
			do_action( 'tg_booking_cancelled', $id, $reason );
			TG_Emails::booking_cancelled( $updated );
		}
		if ( 'confirmed' === $status ) {
			do_action( 'tg_booking_confirmed', $id );
			TG_Emails::booking_confirmed( $updated );
		}

		return true;
	}

	/**
	 * Set payment status.
	 *
	 * @param int    $id     Booking ID.
	 * @param string $status Payment status.
	 * @return void
	 */
	public static function set_payment_status( int $id, string $status ): void {
		$global = $GLOBALS['wpdb'];
		$global->update( self::tbl(), array( 'payment_status' => $status, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
	}

	/**
	 * Quick action: confirm a booking.
	 *
	 * @param int $id Booking ID.
	 * @return true|WP_Error
	 */
	public static function confirm( int $id ) {
		return self::update_status( $id, 'confirmed', 'manual' );
	}

	/**
	 * Quick action: cancel a booking.
	 *
	 * @param int    $id     Booking ID.
	 * @param string $reason Reason.
	 * @return true|WP_Error
	 */
	public static function cancel( int $id, string $reason = '' ) {
		return self::update_status( $id, 'cancelled', $reason ? $reason : 'manual' );
	}

	/**
	 * Quick action: mark paid (admin / manual methods).
	 *
	 * @param int    $id       Booking ID.
	 * @param string $method   Method key.
	 * @param string $txn_id   Optional reference.
	 * @return true|WP_Error
	 */
	public static function mark_paid( int $id, string $method = '', string $txn_id = '' ) {
		$booking = self::get( $id );
		if ( ! $booking ) {
			return new WP_Error( 'tg_booking_missing', __( 'Booking not found.', 'guidegrid-travel' ) );
		}
		return TG_Payments::mark_paid( $id, 'manual', $txn_id ? $txn_id : 'MANUAL-' . $booking->booking_number, (float) $booking->total, $method );
	}

	/**
	 * Quick action: complete a trip.
	 *
	 * @param int $id Booking ID.
	 * @return true|WP_Error
	 */
	public static function complete( int $id ) {
		return self::update_status( $id, 'completed', 'manual' );
	}

	/**
	 * Quick action: request refund.
	 *
	 * @param int    $id     Booking ID.
	 * @param string $reason Reason.
	 * @return true|WP_Error
	 */
	public static function request_refund( int $id, string $reason = '' ) {
		return self::update_status( $id, 'refund_requested', $reason );
	}

	/**
	 * Process a refund (delegates to the payment layer).
	 *
	 * @param int    $id       Booking ID.
	 * @param float  $amount   Refund amount (defaults to full total).
	 * @param string $reason   Reason.
	 * @param string $ref      Gateway reference.
	 * @return true|WP_Error
	 */
	public static function refund( int $id, float $amount = 0, string $reason = '', string $ref = '' ) {
		$booking = self::get( $id );
		if ( ! $booking ) {
			return new WP_Error( 'tg_booking_missing', __( 'Booking not found.', 'guidegrid-travel' ) );
		}
		if ( $amount <= 0 ) {
			$amount = (float) $booking->total;
		}
		return TG_Payments::refund( $id, $amount, $reason, $ref );
	}

	/**
	 * Add an internal (admin-only) note.
	 *
	 * @param int    $id      Booking ID.
	 * @param string $note    Note text.
	 * @param int    $user_id Author user ID.
	 * @return int Note ID.
	 */
	public static function add_note( int $id, string $note, int $user_id = 0 ): int {
		$global = $GLOBALS['wpdb'];
		$global->insert(
			TG_Database::table( 'booking_notes' ),
			array(
				'booking_id' => $id,
				'user_id'    => $user_id,
				'note'       => sanitize_textarea_field( $note ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s' )
		);
		return (int) $global->insert_id;
	}

	/**
	 * Notes for a booking (admin only — never rendered to customers).
	 *
	 * @param int $id Booking ID.
	 * @return object[]
	 */
	public static function notes( int $id ): array {
		$global = $GLOBALS['wpdb'];
		$rows   = $global->get_results( $global->prepare( 'SELECT * FROM ' . TG_Database::table( 'booking_notes' ) . ' WHERE booking_id = %d ORDER BY created_at DESC', $id ) );
		return (array) $rows;
	}

	/**
	 * Lightweight booking meta (flags such as reminder-sent).
	 *
	 * @param int    $id    Booking ID.
	 * @param string $key   Meta key.
	 * @param mixed  $value Value to set.
	 * @return void
	 */
	public static function set_meta( int $id, string $key, $value ): void {
		$global = $GLOBALS['wpdb'];
		$table  = TG_Database::table( 'booking_meta' );
		$exists = $global->get_var( $global->prepare( 'SELECT id FROM ' . $table . ' WHERE booking_id = %d AND meta_key = %s LIMIT 1', $id, $key ) );
		if ( $exists ) {
			$global->update( $table, array( 'meta_value' => (string) $value ), array( 'booking_id' => $id, 'meta_key' => $key ) );
		} else {
			$global->insert( $table, array( 'booking_id' => $id, 'meta_key' => $key, 'meta_value' => (string) $value ) );
		}
	}

	/**
	 * Read booking meta.
	 *
	 * @param int    $id  Booking ID.
	 * @param string $key Meta key.
	 * @return string
	 */
	public static function get_meta( int $id, string $key ): string {
		$global = $GLOBALS['wpdb'];
		return (string) $global->get_var( $global->prepare( 'SELECT meta_value FROM ' . TG_Database::table( 'booking_meta' ) . ' WHERE booking_id = %d AND meta_key = %s LIMIT 1', $id, $key ) );
	}

	/**
	 * Bookings belonging to a logged-in user or matching guest email.
	 *
	 * @param int    $user_id User ID.
	 * @param string $email   Email (guest checkout).
	 * @return object[]
	 */
	public static function get_for_user( int $user_id, string $email = '' ): array {
		$global = $GLOBALS['wpdb'];
		$rows   = $global->get_results(
			$global->prepare(
				'SELECT b.* FROM ' . self::tbl() . ' b
				 LEFT JOIN ' . TG_Database::table( 'customers' ) . ' c ON c.id = b.customer_id
				 WHERE c.user_id = %d OR c.email = %s
				 ORDER BY b.created_at DESC',
				$user_id,
				strtolower( $email )
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		return (array) $rows;
	}

	/**
	 * All bookings for a tour (admin).
	 *
	 * @param int $tour_id Tour post ID.
	 * @return object[]
	 */
	public static function for_tour( int $tour_id ): array {
		$global = $GLOBALS['wpdb'];
		return (array) $global->get_results( $global->prepare( 'SELECT * FROM ' . self::tbl() . ' WHERE tour_id = %d ORDER BY created_at DESC', $tour_id ) );
	}

	/**
	 * Look up a booking with number + email (rate-limited by caller).
	 *
	 * @param string $number Booking number.
	 * @param string $email  Customer email.
	 * @return object|WP_Error
	 */
	public static function lookup( string $number, string $email ) {
		$number = strtoupper( trim( $number ) );
		$email  = strtolower( sanitize_email( $email ) );

		if ( '' === $number || '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'tg_lookup_invalid', __( 'Please provide a valid booking number and email address.', 'guidegrid-travel' ) );
		}

		$global  = $GLOBALS['wpdb'];
		$booking = $global->get_row(
			$global->prepare(
				'SELECT b.* FROM ' . self::tbl() . ' b
				 LEFT JOIN ' . TG_Database::table( 'customers' ) . ' c ON c.id = b.customer_id
				 WHERE b.booking_number = %s AND c.email = %s LIMIT 1',
				$number,
				$email
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $booking ) {
			return new WP_Error( 'tg_lookup_not_found', __( 'No booking matches that number and email combination.', 'guidegrid-travel' ) );
		}

		return $booking;
	}

	/**
	 * Dashboard stats.
	 *
	 * @return array
	 */
	public static function stats(): array {
		$global = $GLOBALS['wpdb'];
		$rows   = $global->get_results( 'SELECT booking_status, COUNT(*) AS total FROM ' . self::tbl() . ' GROUP BY booking_status' );
		$map    = array_fill_keys( array_keys( self::status_labels() ), 0 );
		foreach ( (array) $rows as $row ) {
			$map[ $row->booking_status ] = (int) $row->total;
		}
		$map['total']       = array_sum( $map );
		$map['revenue']     = (float) $global->get_var( "SELECT COALESCE(SUM(total),0) FROM " . self::tbl() . " WHERE booking_status IN ('paid','confirmed','completed','partially_paid')" );
		$map['pending_rev'] = (float) $global->get_var( "SELECT COALESCE(SUM(total),0) FROM " . self::tbl() . " WHERE booking_status IN ('pending','awaiting_payment')" );

		return $map;
	}

	/**
	 * Increment cached popularity meta (used for "popular" sorting).
	 *
	 * @param int $tour_id Tour post ID.
	 * @return void
	 */
	public static function increment_tour_popularity( int $tour_id ): void {
		$count = (int) get_post_meta( $tour_id, '_tg_booking_count', true );
		update_post_meta( $tour_id, '_tg_booking_count', $count + 1 );
	}

	/**
	 * Decode price snapshot from a booking row.
	 *
	 * @param object $booking Booking row.
	 * @return array
	 */
	public static function price_snapshot( object $booking ): array {
		$snapshot = json_decode( (string) $booking->price_snapshot, true );
		return is_array( $snapshot ) ? $snapshot : array();
	}

	/**
	 * Decode add-ons from a booking row.
	 *
	 * @param object $booking Booking row.
	 * @return array
	 */
	public static function addons( object $booking ): array {
		$addons = json_decode( (string) $booking->addons, true );
		return is_array( $addons ) ? $addons : array();
	}

	/**
	 * Decode customer data from a booking row.
	 *
	 * @param object $booking Booking row.
	 * @return array
	 */
	public static function customer( object $booking ): array {
		$data = json_decode( (string) $booking->customer_data, true );
		return is_array( $data ) ? $data : array();
	}
}
