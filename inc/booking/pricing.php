<?php
/**
 * Pricing engine.
 *
 * Price resolution priority (deterministic):
 *   1. Specific-date rule   (rule_type = specific, start_date = end_date = date)
 *   2. Seasonal rule        (rule_type = seasonal, date within start/end)
 *   3. Weekly rule          (rule_type = weekly, date's weekday in days_of_week)
 *   4. Tour base prices     (post meta)
 *
 * When multiple rules of the same type match, the rule with the lowest
 * `priority` value wins; ties are broken by the most recently created rule.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TG_Pricing
 */
final class TG_Pricing {

	/**
	 * Table name (prefixed).
	 *
	 * @return string
	 */
	private static function tbl(): string {
		return TG_Database::table( 'pricing_rules' );
	}

	/**
	 * Base (default) prices from post meta.
	 *
	 * @param int $tour_id Tour post ID.
	 * @return array{adult:float,child:float,infant:float,currency:string}
	 */
	public static function base_prices( int $tour_id ): array {
		$settings = tg_settings();
		$adult    = (float) tg_get_meta( $tour_id, '_tg_adult_price', tg_get_meta( $tour_id, '_tg_base_price', 0 ) );
		return array(
			'adult'    => $adult,
			'child'    => (float) tg_get_meta( $tour_id, '_tg_child_price', 0 ),
			'infant'   => (float) tg_get_meta( $tour_id, '_tg_infant_price', 0 ),
			'currency' => tg_get_meta( $tour_id, '_tg_currency', $settings['currency'] ) ? tg_get_meta( $tour_id, '_tg_currency', $settings['currency'] ) : $settings['currency'],
		);
	}

	/**
	 * Resolve the prices that apply to a tour on a given date.
	 *
	 * @param int    $tour_id Tour post ID.
	 * @param string $date    Y-m-d.
	 * @return array{adult:float,child:float,infant:float,currency:string,rule_id:int,source:string}
	 */
	public static function get_prices_for_date( int $tour_id, string $date ): array {
		$base  = self::base_prices( $tour_id );
		$dates = array( self::normalize_date( $date ) );

		$result = array_merge(
			$base,
			array(
				'rule_id' => 0,
				'source'  => 'base',
			)
		);

		if ( empty( $dates[0] ) ) {
			return $result;
		}

		$global     = $GLOBALS['wpdb'];
		$table      = self::tbl();
		$d          = $dates[0];
		$dow        = (string) (int) gmdate( 'w', strtotime( $d ) );

		// 1. Specific date.
		$row = $global->get_row(
			$global->prepare(
				"SELECT * FROM {$table}
				 WHERE tour_id = %d AND rule_type = 'specific' AND status = 1 AND start_date = %s
				 ORDER BY priority ASC, id DESC LIMIT 1",
				$tour_id,
				$d
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( $row ) {
			return self::rule_to_prices( $row, $base, 'specific' );
		}

		// 2. Seasonal.
		$row = $global->get_row(
			$global->prepare(
				"SELECT * FROM {$table}
				 WHERE tour_id = %d AND rule_type = 'seasonal' AND status = 1 AND start_date <= %s AND end_date >= %s
				 ORDER BY priority ASC, id DESC LIMIT 1",
				$tour_id,
				$d,
				$d
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( $row ) {
			return self::rule_to_prices( $row, $base, 'seasonal' );
		}

		// 3. Weekly. days_of_week stored as comma list (e.g. "1,3,5"); LIKE
		// on a padded string keeps matching exact tokens.
		$row = $global->get_row(
			$global->prepare(
				"SELECT * FROM {$table}
				 WHERE tour_id = %d AND rule_type = 'weekly' AND status = 1 AND ( days_of_week = %s OR days_of_week LIKE %s OR days_of_week LIKE %s OR days_of_week LIKE %s )
				 ORDER BY priority ASC, id DESC LIMIT 1",
				$tour_id,
				$dow,
				$dow . ',%',
				'%,' . $dow,
				'%,' . $dow . ',%'
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( $row ) {
			return self::rule_to_prices( $row, $base, 'weekly' );
		}

		return $result;
	}

	/**
	 * Build a price result from a rule row.
	 *
	 * @param object $rule    Rule row.
	 * @param array  $base    Base prices.
	 * @param string $source  Source label.
	 * @return array
	 */
	private static function rule_to_prices( object $rule, array $base, string $source ): array {
		return array(
			'adult'    => (float) $rule->adult_price,
			'child'    => (float) $rule->child_price,
			'infant'   => (float) $rule->infant_price,
			'currency' => $base['currency'],
			'rule_id'  => (int) $rule->id,
			'source'   => $source,
		);
	}

	/**
	 * Local date helper (site timezone aware).
	 *
	 * @param string $date Date string.
	 * @return string Y-m-d
	 */
	private static function normalize_date( string $date ): string {
		return TG_Availability::normalize_date( $date );
	}

	/**
	 * Add-on line calculation for a quote.
	 *
	 * @param int $addon_id  Add-on post ID.
	 * @param int $guests    Total guests (adults + children + infants).
	 * @param int $days      Tour duration in days.
	 * @return array|null
	 */
	public static function addon_line( int $addon_id, int $guests, int $days ): ?array {
		$addon = TG_Addons::get( $addon_id );
		if ( ! $addon ) {
			return null;
		}

		$unit   = $addon['unit'];
		$price  = (float) $addon['price'];
		$qty    = 1;

		switch ( $unit ) {
			case 'per_person':
				$qty = max( 1, $guests );
				break;
			case 'per_booking':
				$qty = 1;
				break;
			case 'per_day':
				$qty = max( 1, $days );
				break;
			case 'per_night':
				$qty = 1; // One unit per booking.
				break;
			case 'per_vehicle':
				$qty = 1;
				break;
		}

		return array(
			'id'      => $addon_id,
			'name'    => $addon['name'],
			'unit'    => $unit,
			'price'   => $price,
			'qty'     => $qty,
			'amount'  => round( $price * $qty, 2 ),
			'taxable' => ! empty( $addon['taxable'] ),
		);
	}

	/**
	 * Calculate a full booking quote server-side.
	 *
	 * Accepts raw (already sanitized) input and validates everything:
	 * date availability, guest counts, group rules, add-ons and coupons.
	 * Prices submitted by the client are never trusted.
	 *
	 * @param array $args {
	 *   @type int    $tour_id   Required.
	 *   @type string $date      Required, Y-m-d.
	 *   @type int    $adults    Required, >= 1.
	 *   @type int    $children  Optional.
	 *   @type int    $infants   Optional.
	 *   @type int[]  $addons    Add-on post IDs.
	 *   @type string $coupon    Coupon code.
	 *   @type int    $user_id   Current user (0 = guest).
	 *   @type string $email     Customer email (for per-coupon limits).
	 * }
	 * @return array{
	 *   valid:bool, errors:string[], tour_id:int, date:string, adults:int, children:int, infants:int,
	 *   prices:array, addons:array, subtotal:float, discount:float, coupon:array|null, tax:float,
	 *   service_fee:float, total:float, deposit:float, currency:string, price_snapshot:array
	 * }
	 */
	public static function calculate_quote( array $args ): array {
		$settings = tg_settings();
		$errors   = array();

		$tour_id  = absint( $args['tour_id'] ?? 0 );
		$date     = TG_Availability::normalize_date( (string) ( $args['date'] ?? '' ) );
		$adults   = absint( $args['adults'] ?? 0 );
		$children = absint( $args['children'] ?? 0 );
		$infants  = absint( $args['infants'] ?? 0 );
		$addons   = array_map( 'absint', (array) ( $args['addons'] ?? array() ) );
		$coupon   = isset( $args['coupon'] ) ? sanitize_text_field( (string) $args['coupon'] ) : '';
		$email    = isset( $args['email'] ) ? sanitize_email( (string) $args['email'] ) : '';

		$quote = array(
			'valid'         => false,
			'errors'        => array(),
			'tour_id'       => $tour_id,
			'date'          => $date,
			'adults'        => $adults,
			'children'      => $children,
			'infants'       => $infants,
			'prices'        => array( 'adult' => 0, 'child' => 0, 'infant' => 0 ),
			'addons'        => array(),
			'subtotal'      => 0.0,
			'discount'      => 0.0,
			'coupon'        => null,
			'tax'           => 0.0,
			'service_fee'   => 0.0,
			'total'         => 0.0,
			'deposit'       => 0.0,
			'currency'      => $settings['currency'],
			'price_snapshot' => array(),
		);

		// --- Tour. ---
		$tour = $tour_id ? get_post( $tour_id ) : null;
		if ( ! $tour || 'tour' !== $tour->post_type || 'publish' !== $tour->post_status ) {
			$quote['errors'][] = __( 'The selected tour was not found.', 'guidegrid-travel' );
			$quote['valid']    = false;
			return $quote;
		}
		$quote['tour_id']  = $tour_id;
		$quote['currency'] = tg_get_meta( $tour_id, '_tg_currency', $settings['currency'] ) ? tg_get_meta( $tour_id, '_tg_currency', $settings['currency'] ) : $settings['currency'];

		// --- Guests. ---
		if ( $adults < 1 ) {
			$quote['errors'][] = __( 'At least one adult is required.', 'guidegrid-travel' );
		}
		if ( $children < 0 || $infants < 0 ) {
			$quote['errors'][] = __( 'Guest counts cannot be negative.', 'guidegrid-travel' );
		}

		$guests       = $adults + $children + $infants;
		$min_group    = absint( tg_get_meta( $tour_id, '_tg_min_group', 0 ) );
		$max_group    = absint( tg_get_meta( $tour_id, '_tg_max_group', 0 ) );
		$min_booking  = TG_Availability::get_meta( $tour_id )['min_booking'];
		$max_booking  = TG_Availability::get_meta( $tour_id )['max_booking'];

		if ( $min_group > 0 && $guests < $min_group ) {
			/* translators: %d: minimum group size */
			$quote['errors'][] = sprintf( __( 'This tour requires a minimum group of %d travelers.', 'guidegrid-travel' ), $min_group );
		}
		if ( $max_group > 0 && $guests > $max_group ) {
			/* translators: %d: maximum group size */
			$quote['errors'][] = sprintf( __( 'This tour is limited to %d travelers per booking.', 'guidegrid-travel' ), $max_group );
		}
		if ( $min_booking > 1 && $guests < $min_booking ) {
			/* translators: %d: minimum booking quantity */
			$quote['errors'][] = sprintf( __( 'Minimum booking size is %d guests.', 'guidegrid-travel' ), $min_booking );
		}
		if ( $max_booking > 0 && $guests > $max_booking ) {
			/* translators: %d: maximum booking quantity */
			$quote['errors'][] = sprintf( __( 'Maximum booking size is %d guests.', 'guidegrid-travel' ), $max_booking );
		}

		// --- Date. ---
		if ( ! $date ) {
			$quote['errors'][] = __( 'Please select a valid travel date.', 'guidegrid-travel' );
		} else {
			$status = TG_Availability::date_status( $tour_id, $date );
			if ( ! $status['available'] ) {
				$labels = array(
					'past'     => __( 'The selected date is in the past or too close. Please choose another date.', 'guidegrid-travel' ),
					'blackout' => __( 'The tour is not operating on the selected date.', 'guidegrid-travel' ),
					'full'     => __( 'This date is no longer available. Please select another date.', 'guidegrid-travel' ),
					'closed'   => __( 'The tour is not operating on the selected date.', 'guidegrid-travel' ),
				);
				$quote['errors'][] = $labels[ $status['status'] ] ?? __( 'The selected date is not available.', 'guidegrid-travel' );
			} elseif ( $status['capacity'] > 0 && $status['remaining'] < $guests ) {
				$quote['errors'][] = __( 'Not enough seats remain on the selected date.', 'guidegrid-travel' );
			}
		}

		// --- Prices. ---
		$prices           = TG_Pricing::get_prices_for_date( $tour_id, $date );
		$quote['prices']  = $prices;
		$quote['currency'] = $prices['currency'];

		$subtotal = round(
			( $prices['adult'] * $adults )
			+ ( $prices['child'] * $children )
			+ ( $prices['infant'] * $infants ),
			2
		);

		// --- Add-ons. ---
		$days = tg_tour_duration_days( $tour_id );
		foreach ( $addons as $addon_id ) {
			if ( ! $addon_id ) {
				continue;
			}
			// Add-on must be assigned to this tour.
			$assigned = array_map( 'absint', (array) tg_get_meta( $tour_id, '_tg_addons', array() ) );
			if ( ! in_array( $addon_id, $assigned, true ) ) {
				continue;
			}
			$line = TG_Pricing::addon_line( $addon_id, $guests, $days );
			if ( $line ) {
				$quote['addons'][] = $line;
				$subtotal          = round( $subtotal + $line['amount'], 2 );
			}
		}

		$quote['subtotal'] = $subtotal;

		// --- Coupon. ---
		if ( '' !== $coupon ) {
			$coupon_result = TG_Coupons::validate( $coupon, array(
				'subtotal' => $subtotal,
				'tour_id'  => $tour_id,
				'email'    => $email,
			) );
			if ( $coupon_result['valid'] ) {
				$quote['discount'] = $coupon_result['discount'];
				$quote['coupon']   = array(
					'id'     => (int) $coupon_result['coupon']->id,
					'code'   => $coupon,
					'type'   => $coupon_result['coupon']->discount_type,
					'amount' => $coupon_result['discount'],
				);
			} else {
				$quote['errors'][] = $coupon_result['error'];
			}
		}

		// --- Tax & fees. ---
		$tax_percent = tg_get_meta( $tour_id, '_tg_tax_percent', $settings['tax_percent'] );
		$taxable     = round( ( $subtotal - $quote['discount'] ), 2 );
		if ( ! $settings['tax_on_addons'] ) {
			$addon_tax_excluded = 0.0;
			foreach ( $quote['addons'] as $line ) {
				if ( ! $line['taxable'] ) {
					$addon_tax_excluded += $line['amount'];
				}
			}
			$taxable = round( $taxable - $addon_tax_excluded, 2 );
		}
		$taxable       = max( 0, $taxable );
		$quote['tax']  = round( $taxable * ( (float) $tax_percent / 100 ), 2 );

		$service_fee     = tg_get_meta( $tour_id, '_tg_service_fee', $settings['service_fee'] );
		$quote['service_fee'] = (float) $service_fee;

		$quote['total'] = round( $subtotal - $quote['discount'] + $quote['tax'] + $quote['service_fee'], 2 );
		$quote['total'] = max( 0, $quote['total'] );

		// --- Deposit. ---
		$deposit_percent = tg_get_meta( $tour_id, '_tg_deposit_percent', $settings['deposit_percent'] );
		$quote['deposit'] = round( $quote['total'] * ( (float) $deposit_percent / 100 ), 2 );

		// --- Price snapshot (immutable after booking creation). ---
		$quote['price_snapshot'] = array(
			'adult_unit_price'  => $prices['adult'],
			'child_unit_price'  => $prices['child'],
			'infant_unit_price' => $prices['infant'],
			'price_rule_id'     => isset( $prices['rule_id'] ) ? $prices['rule_id'] : 0,
			'price_source'      => $prices['source'] ?? 'base',
			'addons'            => $quote['addons'],
			'subtotal'          => $quote['subtotal'],
			'discount'          => $quote['discount'],
			'coupon_code'       => $coupon,
			'tax_percent'       => (float) $tax_percent,
			'tax'               => $quote['tax'],
			'service_fee'       => $quote['service_fee'],
			'deposit_percent'   => (float) $deposit_percent,
			'deposit'           => $quote['deposit'],
			'total'             => $quote['total'],
			'currency'          => $quote['currency'],
		);

		$quote['errors'] = array_values( array_unique( $quote['errors'] ) );
		$quote['valid']  = empty( $quote['errors'] );

		/**
		 * Filter the calculated booking quote.
		 *
		 * @param array $quote Quote.
		 * @param array $args  Raw arguments.
		 */
		return apply_filters( 'tg_calculated_quote', $quote, $args );
	}
}
