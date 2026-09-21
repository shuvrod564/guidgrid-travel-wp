<?php
/**
 * Scheduled tasks:
 *  - Hourly: expire stale payment holds (release capacity).
 *  - Daily:  trip reminders for confirmed bookings (7/3/1 days before).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_cron_init' ) ) {
	/**
	 * Register cron events (idempotent).
	 *
	 * @return void
	 */
	function tg_cron_init() {
		if ( ! wp_next_scheduled( 'tg_hourly_maintenance' ) ) {
			wp_schedule_event( time(), 'hourly', 'tg_hourly_maintenance' );
		}
		if ( ! wp_next_scheduled( 'tg_daily_reminders' ) ) {
			wp_schedule_event( time(), 'daily', 'tg_daily_reminders' );
		}
	}
}
add_action( 'init', 'tg_cron_init' );

if ( ! function_exists( 'tg_cron_hourly' ) ) {
	/**
	 * Hourly maintenance: expire overdue unpaid holds.
	 *
	 * @return void
	 */
	function tg_cron_hourly() {
		$global = $GLOBALS['wpdb'];
		$table  = TG_Database::table( 'bookings' );
		$now    = current_time( 'mysql', true );

		$stale = $global->get_results(
			$global->prepare(
				"SELECT * FROM {$table}
				 WHERE hold_expires_at IS NOT NULL AND hold_expires_at < %s
				 AND booking_status IN ('pending','awaiting_payment')
				 LIMIT 200",
				$now
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		foreach ( (array) $stale as $booking ) {
			$guests = (int) $booking->adult_count + (int) $booking->child_count + (int) $booking->infant_count;
			if ( $guests > 0 ) {
				TG_Availability::release( (int) $booking->tour_id, $booking->booking_date, $guests, 'hold' );
			}
			TG_Bookings::update_status( (int) $booking->id, 'expired', 'hold_expired' );
			$global->update( $table, array( 'hold_expires_at' => null ), array( 'id' => (int) $booking->id ) );
		}

		do_action( 'tg_cron_hourly_done', is_countable( $stale ) ? count( (array) $stale ) : 0 );
	}
}
add_action( 'tg_hourly_maintenance', 'tg_cron_hourly' );

if ( ! function_exists( 'tg_cron_daily_reminders' ) ) {
	/**
	 * Daily reminders for confirmed bookings.
	 *
	 * Sends for each configured reminder offset (default 7/3/1 days)
	 * exactly once per booking.
	 *
	 * @return void
	 */
	function tg_cron_daily_reminders() {
		$settings = tg_settings();
		$days     = array_map( 'absint', (array) $settings['reminder_days'] );
		if ( empty( $days ) ) {
			return;
		}

		$global      = $GLOBALS['wpdb'];
		$table       = TG_Database::table( 'bookings' );
		$meta_table  = TG_Database::table( 'booking_meta' );
		$offsets     = array();

		foreach ( $days as $offset ) {
			if ( $offset > 0 ) {
				$offsets[] = $offset;
			}
		}

		foreach ( $offsets as $offset ) {
			$target_date = gmdate( 'Y-m-d', strtotime( 'today +' . $offset . ' days' ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );

			$bookings = $global->get_results(
				$global->prepare(
					"SELECT b.* FROM {$table} b
					 LEFT JOIN {$meta_table} m ON m.booking_id = b.id AND m.meta_key = %s
					 WHERE b.booking_status = %s AND b.booking_date = %s AND m.id IS NULL
					 LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'reminded_' . $offset,
					'confirmed',
					$target_date
				) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);

			foreach ( (array) $bookings as $booking ) {
				TG_Bookings::set_meta( (int) $booking->id, 'reminded_' . $offset, '1' );
				TG_Emails::booking_reminder( $booking, $offset );
			}
		}
	}
}
add_action( 'tg_daily_reminders', 'tg_cron_daily_reminders' );
