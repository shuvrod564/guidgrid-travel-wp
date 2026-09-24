<?php
/**
 * Availability engine.
 *
 * Supports fixed dates, date ranges, weekly schedules, blackout dates and
 * per-date capacity with race-condition protection (row locking inside a
 * transaction).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TG_Availability
 */
final class TG_Availability {

	/**
	 * Table name (prefixed).
	 *
	 * @return string
	 */
	private static function tbl(): string {
		return TG_Database::table( 'availability' );
	}

	/**
	 * Normalize a date string to Y-m-d or false.
	 *
	 * @param string $date Date string.
	 * @return string|false
	 */
	public static function normalize_date( string $date ) {
		$ts = strtotime( $date );
		if ( false === $ts ) {
			return false;
		}
		return gmdate( 'Y-m-d', $ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}

	/**
	 * Get the raw availability meta for a tour.
	 *
	 * @param int $tour_id Tour post ID.
	 * @return array
	 */
	public static function get_meta( int $tour_id ): array {
		$settings = tg_settings();
		return array(
			'mode'             => tg_get_meta( $tour_id, '_tg_availability_mode', 'any' ),
			'dates'            => array_filter( array_map( 'trim', explode( "\n", (string) tg_get_meta( $tour_id, '_tg_availability_dates' ) ) ) ),
			'range_start'      => tg_get_meta( $tour_id, '_tg_range_start', '' ),
			'range_end'        => tg_get_meta( $tour_id, '_tg_range_end', '' ),
			'weekly_days'      => array_map( 'absint', (array) tg_get_meta( $tour_id, '_tg_weekly_days', array() ) ),
			'blackout'         => array_map( 'trim', explode( "\n", (string) tg_get_meta( $tour_id, '_tg_blackout_dates' ) ) ),
			'capacity'         => absint( tg_get_meta( $tour_id, '_tg_capacity', 0 ) ),
			'min_booking'      => absint( tg_get_meta( $tour_id, '_tg_min_booking', 1 ) ),
			'max_booking'      => absint( tg_get_meta( $tour_id, '_tg_max_booking', 0 ) ),
			'window_days'      => max( 1, absint( $settings['available_days_ahead'] ) ),
			'min_notice_days'  => max( 0, absint( $settings['min_booking_notice_days'] ) ),
		);
	}

	/**
	 * List of candidate dates (Y-m-d) on which the tour may run between two
	 * dates, honoring the tour's availability mode. Blackout, past-date and
	 * capacity checks are applied afterwards by date_status().
	 *
	 * @param int      $tour_id Tour post ID.
	 * @param string   $start   Start date (default: today).
	 * @param string   $end     End date (default: today + window).
	 * @return string[]
	 */
	public static function candidate_dates( int $tour_id, string $start = '', string $end = '' ): array {
		$meta  = self::get_meta( $tour_id );
		$today = gmdate( 'Y-m-d', time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );

		$start = $start ? self::normalize_date( $start ) : $today;
		$end   = $end ? self::normalize_date( $end ) : gmdate( 'Y-m-d', strtotime( $today . ' +' . $meta['window_days'] . ' days' ) );

		if ( ! $start || ! $end || $start > $end ) {
			return array();
		}

		$dates = array();

		switch ( $meta['mode'] ) {
			case 'fixed':
				foreach ( $meta['dates'] as $d ) {
					$d = self::normalize_date( $d );
					if ( $d && $d >= $start && $d <= $end ) {
						$dates[] = $d;
					}
				}
				sort( $dates );
				return array_values( array_unique( $dates ) );

			case 'range':
				$rs = self::normalize_date( $meta['range_start'] );
				$re = self::normalize_date( $meta['range_end'] );
				if ( ! $rs || ! $re ) {
					return array();
				}
				$rs = $rs > $start ? $rs : $start;
				$re = $re < $end ? $re : $end;
				for ( $i = $rs; $i <= $re; $i = gmdate( 'Y-m-d', strtotime( $i . ' +1 day' ) ) ) {
					$dates[] = $i;
				}
				return $dates;

			case 'weekly':
				if ( empty( $meta['weekly_days'] ) ) {
					return array();
				}
				$days = array_map( 'strval', $meta['weekly_days'] );
				for ( $i = $start; $i <= $end; $i = gmdate( 'Y-m-d', strtotime( $i . ' +1 day' ) ) ) {
					if ( in_array( gmdate( 'w', strtotime( $i ) ), $days, true ) ) {
						$dates[] = $i;
					}
				}
				return $dates;

			case 'any':
			default:
				for ( $i = $start; $i <= $end; $i = gmdate( 'Y-m-d', strtotime( $i . ' +1 day' ) ) ) {
					$dates[] = $i;
				}
				return $dates;
		}
	}

	/**
	 * Full status for a single date.
	 *
	 * @param int    $tour_id Tour post ID.
	 * @param string $date    Y-m-d.
	 * @return array {
	 *   @type bool   $available Whether a booking can be made on this date.
	 *   @type string $status    past|blackout|full|open.
	 *   @type int    $capacity  Capacity for the date (0 = unlimited).
	 *   @type int    $reserved  Reserved seats.
	 *   @type int    $hold      Seats under payment hold.
	 *   @type int    $remaining Remaining seats (-1 = unlimited).
	 * }
	 */
	public static function date_status( int $tour_id, string $date ): array {
		$date = self::normalize_date( $date );
		$meta = self::get_meta( $tour_id );

		$result = array(
			'available' => false,
			'status'    => 'closed',
			'capacity'  => $meta['capacity'],
			'reserved'  => 0,
			'hold'      => 0,
			'remaining' => $meta['capacity'] > 0 ? $meta['capacity'] : -1,
		);

		$today = gmdate( 'Y-m-d', time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );

		if ( ! $date || $date < gmdate( 'Y-m-d', strtotime( $today . ' +' . $meta['min_notice_days'] . ' days' ) ) ) {
			$result['status'] = 'past';
			return $result;
		}

		if ( in_array( $date, $meta['blackout'], true ) ) {
			$result['status'] = 'blackout';
			return $result;
		}

		$candidates = self::candidate_dates( $tour_id, $date, $date );
		if ( empty( $candidates ) ) {
			$result['status'] = 'closed';
			return $result;
		}

		$row = self::ensure_row( $tour_id, $date );
		$result['capacity'] = (int) $row->capacity;
		$result['reserved'] = (int) $row->reserved;
		$result['hold']     = (int) $row->hold;

		if ( $result['capacity'] > 0 ) {
			$result['remaining'] = max( 0, $result['capacity'] - $result['reserved'] - $result['hold'] );
			if ( $result['remaining'] <= 0 ) {
				$result['status'] = 'full';
				return $result;
			}
		}

		$result['available'] = true;
		$result['status']    = 'open';
		return $result;
	}

	/**
	 * List of bookable dates with status info for a date window.
	 *
	 * @param int    $tour_id Tour post ID.
	 * @param string $start   Start date.
	 * @param string $end     End date.
	 * @return array[]
	 */
	public static function get_dates( int $tour_id, string $start = '', string $end = '' ): array {
		$dates  = self::candidate_dates( $tour_id, $start, $end );
		$priced = array();

		foreach ( $dates as $d ) {
			$status = self::date_status( $tour_id, $d );
			if ( $status['available'] ) {
				$prices              = TG_Pricing::get_prices_for_date( $tour_id, $d );
				$prices['date']      = $d;
				$prices['remaining'] = $status['remaining'];
				$priced[]            = $prices;
			}
		}
		return $priced;
	}

	/**
	 * Get (or lazily create) the availability row for a date.
	 *
	 * @param int    $tour_id Tour post ID.
	 * @param string $date    Y-m-d.
	 * @return object
	 */
	public static function ensure_row( int $tour_id, string $date ): object {
		$global = $GLOBALS['wpdb'];
		$table  = self::tbl();
		$date   = self::normalize_date( $date );

		$row = $global->get_row(
			$global->prepare( "SELECT * FROM {$table} WHERE tour_id = %d AND availability_date = %s", $tour_id, $date ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( $row ) {
			return $row;
		}

		$meta      = self::get_meta( $tour_id );
		$now       = current_time( 'mysql', true );
		$candidate = in_array( $date, self::candidate_dates( $tour_id, $date, $date ), true );

		$global->insert(
			$table,
			array(
				'tour_id'           => $tour_id,
				'availability_date' => $date,
				'capacity'          => $meta['capacity'],
				'reserved'          => 0,
				'hold'              => 0,
				'status'            => $candidate ? 'open' : 'closed',
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		return $global->get_row(
			$global->prepare( "SELECT * FROM {$table} WHERE tour_id = %d AND availability_date = %s", $tour_id, $date ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Reserve seats for a date. Runs inside a caller-managed or its own
	 * transaction and locks the row to prevent double booking.
	 *
	 * @param int    $tour_id Tour post ID.
	 * @param string $date    Y-m-d.
	 * @param int    $qty     Seats to reserve.
	 * @param string $mode               hold|confirmed.
	 * @param bool   $manage_transaction Whether this method should start/finish its own transaction.
	 * @return true|WP_Error
	 */
	public static function reserve( int $tour_id, string $date, int $qty, string $mode = 'hold', bool $manage_transaction = true ) {
		$global = $GLOBALS['wpdb'];
		$table  = self::tbl();
		$date   = self::normalize_date( $date );
		$qty    = max( 0, (int) $qty );
		$column = ( 'hold' === $mode ) ? 'hold' : 'reserved';

		self::ensure_row( $tour_id, $date );

		if ( $manage_transaction ) {
			$global->query( 'START TRANSACTION' );
		}

		$row = $global->get_row(
			$global->prepare( "SELECT * FROM {$table} WHERE tour_id = %d AND availability_date = %s FOR UPDATE", $tour_id, $date ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $row ) {
			if ( $manage_transaction ) {
				$global->query( 'ROLLBACK' );
			}
			return new WP_Error( 'tg_no_availability', __( 'No availability found for the selected date.', 'guidegrid-travel' ) );
		}

		$capacity = (int) $row->capacity;
		if ( $capacity > 0 ) {
			$remaining = $capacity - (int) $row->reserved - (int) $row->hold;
			if ( $remaining < $qty ) {
				if ( $manage_transaction ) {
					$global->query( 'ROLLBACK' );
				}
				return new WP_Error(
					'tg_sold_out',
					__( 'This date is no longer available. Please select another date.', 'guidegrid-travel' )
				);
			}
		}

		$updated = $global->query(
			$global->prepare(
				"UPDATE {$table} SET {$column} = {$column} + %d, status = 'open', updated_at = %s WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$qty,
				current_time( 'mysql', true ),
				(int) $row->id
			)
		);

		if ( false === $updated ) {
			if ( $manage_transaction ) {
				$global->query( 'ROLLBACK' );
			}
			return new WP_Error( 'tg_availability_error', __( 'Availability could not be reserved. Please try again.', 'guidegrid-travel' ) );
		}

		if ( $manage_transaction ) {
			$global->query( 'COMMIT' );
		}
		return true;
	}

	/**
	 * Release previously reserved seats.
	 *
	 * @param int    $tour_id Tour post ID.
	 * @param string $date    Y-m-d.
	 * @param int    $qty     Seats to release.
	 * @param string $mode    hold|confirmed.
	 * @return void
	 */
	public static function release( int $tour_id, string $date, int $qty, string $mode = 'hold' ): void {
		$global = $GLOBALS['wpdb'];
		$table  = self::tbl();
		$date   = self::normalize_date( $date );
		$qty    = max( 0, (int) $qty );
		$column = ( 'hold' === $mode ) ? 'hold' : 'reserved';

		self::ensure_row( $tour_id, $date );

		$global->query(
			$global->prepare(
				"UPDATE {$table} SET {$column} = GREATEST({$column} - %d, 0), updated_at = %s WHERE tour_id = %d AND availability_date = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$qty,
				current_time( 'mysql', true ),
				$tour_id,
				$date
			)
		);
	}

	/**
	 * Move seats from payment hold to confirmed reservation.
	 *
	 * @param int    $tour_id Tour post ID.
	 * @param string $date    Y-m-d.
	 * @param int    $qty     Seats to move.
	 * @return void
	 */
	public static function confirm_held( int $tour_id, string $date, int $qty ): void {
		self::release( $tour_id, $date, $qty, 'hold' );
		self::reserve( $tour_id, $date, $qty, 'confirmed' );
	}

	/**
	 * Blackout helper.
	 *
	 * @param int    $tour_id Tour post ID.
	 * @param string $date    Y-m-d.
	 * @return bool
	 */
	public static function is_blackout( int $tour_id, string $date ): bool {
		$meta = self::get_meta( $tour_id );
		return in_array( self::normalize_date( $date ), $meta['blackout'], true );
	}
}
