<?php
/**
 * Coupon engine.
 *
 * Coupons live in the tg_coupons table and support percentage or fixed
 * discounts, minimum amounts, maximum discounts, date windows, usage limits
 * and scoping to tours / categories / destinations.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TG_Coupons
 */
final class TG_Coupons {

	/**
	 * Table name (prefixed).
	 *
	 * @return string
	 */
	private static function tbl(): string {
		return TG_Database::table( 'coupons' );
	}

	/**
	 * Usage table (prefixed).
	 *
	 * @return string
	 */
	private static function usage_tbl(): string {
		return TG_Database::table( 'coupon_usage' );
	}

	/**
	 * Find a coupon by code (case-insensitive).
	 *
	 * @param string $code Coupon code.
	 * @return object|null
	 */
	public static function get( string $code ) {
		$global = $GLOBALS['wpdb'];
		$code   = strtoupper( trim( $code ) );
		if ( '' === $code ) {
			return null;
		}
		return $global->get_row(
			$global->prepare( 'SELECT * FROM ' . self::tbl() . ' WHERE code = %s LIMIT 1', $code )
		);
	}

	/**
	 * Validate a coupon against a quote context and compute the discount.
	 *
	 * Validation order:
	 *   code exists, active, within valid dates, usage limits, minimum
	 *   amount, tour/category/destination eligibility.
	 *
	 * @param string $code Coupon code.
	 * @param array  $ctx  { subtotal, tour_id, email }.
	 * @return array{valid:bool,error:string,discount:float,coupon:object|null}
	 */
	public static function validate( string $code, array $ctx ): array {
		$result = array(
			'valid'    => false,
			'error'    => '',
			'discount' => 0.0,
			'coupon'   => null,
		);

		$global = $GLOBALS['wpdb'];
		$coupon = self::get( $code );
		if ( ! $coupon ) {
			$result['error'] = __( 'This coupon is not valid for the selected tour.', 'guidegrid-travel' );
			return $result;
		}
		$result['coupon'] = $coupon;

		if ( ! $coupon->active ) {
			$result['error'] = __( 'This coupon is no longer active.', 'guidegrid-travel' );
			return $result;
		}

		$today = gmdate( 'Y-m-d', time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		if ( $coupon->starts_at && $today < $coupon->starts_at ) {
			$result['error'] = __( 'This coupon is not yet active.', 'guidegrid-travel' );
			return $result;
		}
		if ( $coupon->expires_at && $today > $coupon->expires_at ) {
			$result['error'] = __( 'This coupon has expired.', 'guidegrid-travel' );
			return $result;
		}

		if ( (int) $coupon->usage_limit > 0 && (int) $coupon->usage_count >= (int) $coupon->usage_limit ) {
			$result['error'] = __( 'This coupon has reached its usage limit.', 'guidegrid-travel' );
			return $result;
		}

		$email    = isset( $ctx['email'] ) ? sanitize_email( (string) $ctx['email'] ) : '';
		$customer = $email ? strtolower( $email ) : 'guest';
		if ( (int) $coupon->per_customer_limit > 0 ) {
			$used = (int) $global->get_var(
				$global->prepare(
					'SELECT COUNT(*) FROM ' . self::usage_tbl() . ' WHERE coupon_id = %d AND customer_key = %s',
					(int) $coupon->id,
					$customer
				)
			);
			if ( $used >= (int) $coupon->per_customer_limit ) {
				$result['error'] = __( 'You have already used this coupon the maximum number of times.', 'guidegrid-travel' );
				return $result;
			}
		}

		// Eligibility: tours.
		$subs  = (float) ( $ctx['subtotal'] ?? 0 );
		$tour  = isset( $ctx['tour_id'] ) ? (int) $ctx['tour_id'] : 0;
		$tours = array_filter( array_map( 'trim', explode( ',', (string) $coupon->applicable_tours ) ) );
		if ( $tours && ! in_array( (string) $tour, $tours, true ) ) {
			$result['error'] = __( 'This coupon is not valid for the selected tour.', 'guidegrid-travel' );
			return $result;
		}

		// Eligibility: categories.
		$cats = array_filter( array_map( 'trim', explode( ',', (string) $coupon->applicable_categories ) ) );
		if ( $cats ) {
			$terms = $tour ? wp_get_post_terms( $tour, 'tour_category', array( 'fields' => 'ids' ) ) : array();
			if ( is_wp_error( $terms ) ) {
				$terms = array();
			}
			if ( ! array_intersect( $cats, array_map( 'strval', $terms ) ) ) {
				$result['error'] = __( 'This coupon is not valid for the selected tour.', 'guidegrid-travel' );
				return $result;
			}
		}

		// Eligibility: destinations.
		$dests = array_filter( array_map( 'trim', explode( ',', (string) $coupon->applicable_destinations ) ) );
		if ( $dests ) {
			$dest_id = $tour ? (int) tg_get_meta( $tour, '_tg_destination_id', 0 ) : 0;
			if ( ! $dest_id || ! in_array( (string) $dest_id, $dests, true ) ) {
				$result['error'] = __( 'This coupon is not valid for the selected tour.', 'guidegrid-travel' );
				return $result;
			}
		}

		if ( $subs < (float) $coupon->min_amount ) {
			/* translators: %s: formatted minimum amount */
			$result['error'] = sprintf( __( 'This coupon requires a minimum booking amount of %s.', 'guidegrid-travel' ), tg_format_price( (float) $coupon->min_amount ) );
			return $result;
		}

		// Compute discount.
		if ( 'fixed' === $coupon->discount_type ) {
			$discount = min( (float) $coupon->discount_value, $subs );
		} else {
			$discount = $subs * ( (float) $coupon->discount_value / 100 );
			if ( (float) $coupon->max_discount > 0 ) {
				$discount = min( $discount, (float) $coupon->max_discount );
			}
		}
		$discount = round( min( $discount, $subs ), 2 );

		$result['valid']    = true;
		$result['discount'] = $discount;
		return $result;
	}

	/**
	 * Record coupon usage (called when a booking is created).
	 *
	 * @param int    $coupon_id  Coupon ID.
	 * @param int    $booking_id Booking ID.
	 * @param string $customer_key Customer key (lowercase email or "guest").
	 * @return void
	 */
	public static function record_usage( int $coupon_id, int $booking_id, string $customer_key ): void {
		$global = $GLOBALS['wpdb'];
		$now    = current_time( 'mysql', true );

		$global->insert(
			self::usage_tbl(),
			array(
				'coupon_id'    => $coupon_id,
				'booking_id'   => $booking_id,
				'customer_key' => $customer_key,
				'used_at'      => $now,
			),
			array( '%d', '%d', '%s', '%s' )
		);

		$global->query( $global->prepare( 'UPDATE ' . self::tbl() . ' SET usage_count = usage_count + 1, updated_at = %s WHERE id = %d', $now, $coupon_id ) );
	}

	/**
	 * List coupons with pagination (admin).
	 *
	 * @param array $args { per_page, page, search }.
	 * @return array{items:object[],total:int}
	 */
	public static function list_coupons( array $args = array() ): array {
		$global   = $GLOBALS['wpdb'];
		$per_page = isset( $args['per_page'] ) ? max( 1, absint( $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$search   = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';
		$offset   = ( $page - 1 ) * $per_page;

		$where  = 'WHERE 1=1';
		$params = array();
		if ( $search ) {
			$where    .= ' AND (code LIKE %s OR description LIKE %s)';
			$like      = '%' . $global->esc_like( $search ) . '%';
			$params[]  = $like;
			$params[]  = $like;
		}

		$total = (int) $global->get_var(
			$params ? $global->prepare( 'SELECT COUNT(*) FROM ' . self::tbl() . ' ' . $where, $params ) : 'SELECT COUNT(*) FROM ' . self::tbl() . ' ' . $where
		);

		$sql = 'SELECT * FROM ' . self::tbl() . ' ' . $where . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;
		$items    = $global->get_results( $global->prepare( $sql, $params ) );

		return array(
			'items' => (array) $items,
			'total' => $total,
		);
	}

	/**
	 * Insert or update a coupon.
	 *
	 * @param array $data Coupon fields (sanitized by caller).
	 * @return int|WP_Error Coupon ID.
	 */
	public static function save( array $data ) {
		$global = $GLOBALS['wpdb'];
		$now    = current_time( 'mysql', true );

		$code = strtoupper( sanitize_text_field( $data['code'] ?? '' ) );
		if ( '' === $code ) {
			return new WP_Error( 'tg_coupon_code', __( 'Coupon code is required.', 'guidegrid-travel' ) );
		}

		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		if ( $id ) {
			$existing = $global->get_row( $global->prepare( 'SELECT id FROM ' . self::tbl() . ' WHERE id = %d', $id ) );
			if ( ! $existing ) {
				return new WP_Error( 'tg_coupon_missing', __( 'Coupon not found.', 'guidegrid-travel' ) );
			}
			// Code uniqueness.
			$duplicate = $global->get_var( $global->prepare( 'SELECT id FROM ' . self::tbl() . ' WHERE code = %s AND id != %d LIMIT 1', $code, $id ) );
			if ( $duplicate ) {
				return new WP_Error( 'tg_coupon_duplicate', __( 'A coupon with this code already exists.', 'guidegrid-travel' ) );
			}
		} else {
			$duplicate = $global->get_var( $global->prepare( 'SELECT id FROM ' . self::tbl() . ' WHERE code = %s LIMIT 1', $code ) );
			if ( $duplicate ) {
				return new WP_Error( 'tg_coupon_duplicate', __( 'A coupon with this code already exists.', 'guidegrid-travel' ) );
			}
		}

		$row = array(
			'code'                    => $code,
			'description'             => sanitize_textarea_field( $data['description'] ?? '' ),
			'discount_type'           => in_array( $data['discount_type'] ?? '', array( 'percent', 'fixed' ), true ) ? $data['discount_type'] : 'percent',
			'discount_value'          => max( 0, (float) ( $data['discount_value'] ?? 0 ) ),
			'min_amount'              => max( 0, (float) ( $data['min_amount'] ?? 0 ) ),
			'max_discount'            => max( 0, (float) ( $data['max_discount'] ?? 0 ) ),
			'starts_at'               => self::date_or_null( $data['starts_at'] ?? '' ),
			'expires_at'              => self::date_or_null( $data['expires_at'] ?? '' ),
			'usage_limit'             => max( 0, absint( $data['usage_limit'] ?? 0 ) ),
			'per_customer_limit'      => max( 0, absint( $data['per_customer_limit'] ?? 0 ) ),
			'applicable_tours'        => sanitize_text_field( $data['applicable_tours'] ?? '' ),
			'applicable_categories'   => sanitize_text_field( $data['applicable_categories'] ?? '' ),
			'applicable_destinations' => sanitize_text_field( $data['applicable_destinations'] ?? '' ),
			'active'                  => empty( $data['active'] ) ? 0 : 1,
			'updated_at'              => $now,
		);

		if ( $id ) {
			$global->update( self::tbl(), $row, array( 'id' => $id ) );
			return $id;
		}

		$row['usage_count'] = 0;
		$row['created_at']  = $now;
		$global->insert( self::tbl(), $row );
		return (int) $global->insert_id;
	}

	/**
	 * Delete a coupon (and its usage rows).
	 *
	 * @param int $id Coupon ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		$global   = $GLOBALS['wpdb'];
		$deleted  = $global->delete( self::tbl(), array( 'id' => $id ) );
		$global->delete( self::usage_tbl(), array( 'coupon_id' => $id ) );
		return (bool) $deleted;
	}

	/**
	 * Date field helper: valid Y-m-d or null.
	 *
	 * @param string $value Raw value.
	 * @return string|null
	 */
	private static function date_or_null( string $value ) {
		$value = trim( $value );
		if ( ! $value ) {
			return null;
		}
		$ts = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d', $ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) : null;
	}
}
