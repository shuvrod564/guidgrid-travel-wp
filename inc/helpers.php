<?php
/**
 * Shared helper functions.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_settings' ) ) {
	/**
	 * Theme settings (single option + defaults).
	 *
	 * @return array
	 */
	function tg_settings(): array {
		$defaults = array(
			'currency'                => 'USD',
			'currency_symbol'         => '',
			'date_format'             => 'F j, Y',
			'tax_percent'             => 0,
			'service_fee'             => 0,
			'deposit_percent'         => 0,
			'tax_on_addons'           => 1,
			'hold_minutes'            => 15,
			'available_days_ahead'    => 90,
			'min_booking_notice_days' => 1,
			'allow_guest_checkout'    => 0, // Legacy key; require_customer_account is authoritative.
			'require_customer_account' => 1,
			'reminder_days'           => array( 7, 3, 1 ),
			'manual_payment_methods'  => array(
				'bank'     => __( 'Bank Transfer', 'guidegrid-travel' ),
				'cash'     => __( 'Cash on Arrival', 'guidegrid-travel' ),
				'paylater' => __( 'Pay Later at Office', 'guidegrid-travel' ),
			),
			'manual_payment_enabled'  => array(
				'bank'     => 1,
				'cash'     => 1,
				'paylater' => 1,
			),
			'manual_payment_instructions' => array(
				'bank'     => __( 'We will send the bank details and payment reference by email. Your seats remain on hold until payment is confirmed.', 'guidegrid-travel' ),
				'cash'     => __( 'Your reservation is confirmed. Pay in cash at our office or on arrival, as arranged with our team.', 'guidegrid-travel' ),
				'paylater' => __( 'Your reservation is confirmed. Pay at our office before departure.', 'guidegrid-travel' ),
			),
			'stripe_enabled'          => 0,
			'stripe_secret_key'       => '',
			'stripe_webhook_secret'   => '',
			'paypal_enabled'          => 0,
			'paypal_sandbox'          => 1,
			'paypal_client_id'        => '',
			'paypal_client_secret'    => '',
			'paypal_webhook_id'       => '',
			'test_gateway_enabled'    => 0,
			'webhook_secret'          => '', // Legacy custom-adapter webhook secret.
			'admin_email'             => '',
			'enquiry_notify_email'    => '',
			'email_from_name'         => get_bloginfo( 'name' ),
			'email_from_email'        => get_option( 'admin_email' ),
			'sticky_header'           => 1,
			'show_header_search'      => 1,
			'show_header_wishlist'    => 1,
			'show_header_account'     => 1,
			'show_header_cta'         => 1,
			'header_cta_text'         => __( 'Book a Tour', 'guidegrid-travel' ),
			'header_cta_url'          => '',
			'footer_about'            => '',
			'footer_copyright'        => '',
			'contact_address'         => '',
			'contact_phone'           => '',
			'contact_email'           => '',
			'contact_hours'           => '',
			'social_facebook'         => '',
			'social_instagram'        => '',
			'social_x'                => '',
			'social_youtube'          => '',
			'primary_color'           => '#0B6E69',
			'secondary_color'         => '#F4A261',
			'accent_color'            => '#2A9D8F',
			'search_per_page'         => 12,
		);

		$settings = wp_parse_args( (array) get_option( 'tg_settings', array() ), $defaults );

		/**
		 * Filter all theme settings.
		 *
		 * @param array $settings Settings.
		 */
		return apply_filters( 'tg_settings', $settings );
	}
}


if ( ! function_exists( 'tg_get_meta' ) ) {
	/**
	 * Read post meta with a default.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $key      Meta key.
	 * @param mixed  $default  Default value.
	 * @return mixed
	 */
	function tg_get_meta( int $post_id, string $key, $default = '' ) {
		$value = get_post_meta( $post_id, $key, true );
		return ( '' === $value || null === $value ) ? $default : $value;
	}
}

if ( ! function_exists( 'tg_currency_symbol' ) ) {
	/**
	 * Symbol for a currency code.
	 *
	 * @param string $code Currency code (USD, BDT...).
	 * @return string
	 */
	function tg_currency_symbol( string $code ): string {
		$settings = tg_settings();
		if ( ! empty( $settings['currency_symbol'] ) && strcasecmp( $code, $settings['currency'] ) === 0 ) {
			return $settings['currency_symbol'];
		}
		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'BDT' => '৳',
			'AED' => 'AED ',
			'INR' => '₹',
			'JPY' => '¥',
			'CAD' => 'CA$',
			'AUD' => 'A$',
			'CHF' => 'CHF ',
			'NGN' => '₦',
			'ZAR' => 'R',
		);
		return isset( $symbols[ strtoupper( $code ) ] ) ? $symbols[ strtoupper( $code ) ] : strtoupper( $code ) . ' ';
	}
}

if ( ! function_exists( 'tg_format_price' ) ) {
	/**
	 * Format a monetary amount for display (escaping is the caller's job).
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	function tg_format_price( float $amount, string $currency = '' ): string {
		if ( '' === $currency ) {
			$currency = tg_settings()['currency'];
		}
		$decimals = (float) $amount == floor( $amount ) ? 0 : 2;
		return tg_currency_symbol( $currency ) . number_format( $amount, $decimals );
	}
}

if ( ! function_exists( 'tg_quote_response_payload' ) ) {
	/**
	 * Convert an internal pricing quote into the public response shape used by
	 * both AJAX and REST clients.
	 *
	 * @param array $quote Internal quote from TG_Pricing::calculate_quote().
	 * @return array
	 */
	function tg_quote_response_payload( array $quote ): array {
		$currency    = isset( $quote['currency'] ) ? (string) $quote['currency'] : tg_settings()['currency'];
		$subtotal    = isset( $quote['subtotal'] ) ? (float) $quote['subtotal'] : 0.0;
		$discount    = isset( $quote['discount'] ) ? (float) $quote['discount'] : 0.0;
		$tax         = isset( $quote['tax'] ) ? (float) $quote['tax'] : 0.0;
		$service_fee = isset( $quote['service_fee'] ) ? (float) $quote['service_fee'] : 0.0;
		$total       = isset( $quote['total'] ) ? (float) $quote['total'] : 0.0;
		$deposit     = isset( $quote['deposit'] ) ? (float) $quote['deposit'] : 0.0;

		return array(
			'valid'       => ! empty( $quote['valid'] ),
			'errors'      => isset( $quote['errors'] ) && is_array( $quote['errors'] ) ? array_values( $quote['errors'] ) : array(),
			'lines'       => array(
				'subtotal' => tg_format_price( $subtotal, $currency ),
				'discount' => tg_format_price( $discount, $currency ),
				'tax'      => tg_format_price( $tax, $currency ),
				'fee'      => tg_format_price( $service_fee, $currency ),
				'total'    => tg_format_price( $total, $currency ),
				'deposit'  => tg_format_price( $deposit, $currency ),
			),
			'raw'         => array(
				'subtotal'    => $subtotal,
				'discount'    => $discount,
				'tax'         => $tax,
				'service_fee' => $service_fee,
				'total'       => $total,
				'deposit'     => $deposit,
			),
			// Keep top-level numbers for backwards compatibility with booking.js.
			'subtotal'    => $subtotal,
			'discount'    => $discount,
			'tax'         => $tax,
			'service_fee' => $service_fee,
			'total'       => $total,
			'deposit'     => $deposit,
			'currency'    => $currency,
			'prices'      => isset( $quote['prices'] ) && is_array( $quote['prices'] ) ? $quote['prices'] : array(),
			'addons'      => isset( $quote['addons'] ) && is_array( $quote['addons'] ) ? $quote['addons'] : array(),
		);
	}
}

if ( ! function_exists( 'tg_format_date' ) ) {
	/**
	 * Format a Y-m-d (or any) date using the theme date format.
	 *
	 * @param string $date   Date.
	 * @param string $format Optional explicit format.
	 * @return string
	 */
	function tg_format_date( string $date, string $format = '' ): string {
		if ( ! $date ) {
			return '';
		}
		$ts = strtotime( $date );
		if ( ! $ts ) {
			return $date;
		}
		if ( '' === $format ) {
			$format = tg_settings()['date_format'];
		}
		return gmdate( $format, $ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}
}

if ( ! function_exists( 'tg_tour_destination' ) ) {
	/**
	 * Destination post for a tour.
	 *
	 * @param int $tour_id Tour post ID.
	 * @return WP_Post|null
	 */
	function tg_tour_destination( int $tour_id ): ?WP_Post {
		$dest_id = (int) tg_get_meta( $tour_id, '_tg_destination_id', 0 );
		if ( ! $dest_id ) {
			return null;
		}
		$dest = get_post( $dest_id );
		return ( $dest && 'destination' === $dest->post_type ) ? $dest : null;
	}
}

if ( ! function_exists( 'tg_tour_destination_name' ) ) {
	/**
	 * Destination name for a tour.
	 *
	 * @param int $tour_id Tour post ID.
	 * @return string
	 */
	function tg_tour_destination_name( int $tour_id ): string {
		$dest = tg_tour_destination( $tour_id );
		return $dest ? get_the_title( $dest ) : tg_get_meta( $tour_id, '_tg_country', '' );
	}
}

if ( ! function_exists( 'tg_tour_duration_days' ) ) {
	/**
	 * Tour duration in whole days (1 for hour-based tours).
	 *
	 * @param int $tour_id Tour post ID.
	 * @return int
	 */
	function tg_tour_duration_days( int $tour_id ): int {
		$unit   = tg_get_meta( $tour_id, '_tg_duration_unit', 'days' );
		$value  = (int) tg_get_meta( $tour_id, '_tg_duration', 1 );
		if ( 'hours' === $unit ) {
			return 1;
		}
		return max( 1, $value );
	}
}

if ( ! function_exists( 'tg_tour_duration_text' ) ) {
	/**
	 * Human duration label, e.g. "3 Days / 2 Nights".
	 *
	 * @param int $tour_id Tour post ID.
	 * @return string
	 */
	function tg_tour_duration_text( int $tour_id ): string {
		$unit  = tg_get_meta( $tour_id, '_tg_duration_unit', 'days' );
		$value = (int) tg_get_meta( $tour_id, '_tg_duration', 0 );
		if ( ! $value ) {
			return '';
		}
		if ( 'hours' === $unit ) {
			return sprintf(
				/* translators: %d: hours */
				_n( '%d hour', '%d hours', $value, 'guidegrid-travel' ),
				$value
			);
		}
		if ( 1 === $value ) {
			return __( '1 Day', 'guidegrid-travel' );
		}
		return sprintf(
			/* translators: 1: days, 2: nights */
			_n( '%1$d Day / %2$d Night', '%1$d Days / %2$d Nights', $value, 'guidegrid-travel' ),
			$value,
			$value - 1
		);
	}
}

if ( ! function_exists( 'tg_tour_price_info' ) ) {
	/**
	 * Price info for display (today's prices).
	 *
	 * @param int $tour_id Tour post ID.
	 * @return array
	 */
	function tg_tour_price_info( int $tour_id ): array {
		$settings  = tg_settings();
		$base      = (float) tg_get_meta( $tour_id, '_tg_adult_price', tg_get_meta( $tour_id, '_tg_base_price', 0 ) );
		$previous  = (float) tg_get_meta( $tour_id, '_tg_previous_price', 0 );
		$currency  = tg_get_meta( $tour_id, '_tg_currency', $settings['currency'] );
		return array(
			'base'     => $base,
			'previous' => $previous > $base ? $previous : 0,
			'currency' => $currency,
		);
	}
}

if ( ! function_exists( 'tg_tour_start_price' ) ) {
	/**
	 * "From $X" label.
	 *
	 * @param int $tour_id Tour post ID.
	 * @return string
	 */
	function tg_tour_start_price( int $tour_id ): string {
		$info = tg_tour_price_info( $tour_id );
		if ( $info['base'] <= 0 ) {
			return __( 'Contact us', 'guidegrid-travel' );
		}
		return sprintf(
			/* translators: %s: formatted price */
			__( 'From %s', 'guidegrid-travel' ),
			tg_format_price( $info['base'], $info['currency'] )
		);
	}
}

if ( ! function_exists( 'tg_get_tour_rating' ) ) {
	/**
	 * Cached rating for a tour (from approved reviews).
	 *
	 * @param int $tour_id Tour post ID.
	 * @return array{avg:float,count:int}
	 */
	function tg_get_tour_rating( int $tour_id ): array {
		return array(
			'avg'   => (float) tg_get_meta( $tour_id, '_tg_rating_avg', 0 ),
			'count' => (int) tg_get_meta( $tour_id, '_tg_rating_count', 0 ),
		);
	}
}

if ( ! function_exists( 'tg_star_html' ) ) {
	/**
	 * Star rating markup (accessible, escaped).
	 *
	 * @param float $rating      0-5.
	 * @param int   $count       Review count (null to hide).
	 * @param bool  $show_count  Show "(count)".
	 * @return string
	 */
	function tg_star_html( float $rating, ?int $count = null, bool $show_count = true ): string {
		$rating = max( 0, min( 5, $rating ) );
		$pct    = ( $rating / 5 ) * 100;

		$html  = '<span class="tg-rating" role="img" aria-label="' . esc_attr(
			sprintf(
				/* translators: %s: rating value */
				__( 'Rated %s out of 5', 'guidegrid-travel' ),
				number_format( $rating, 1 )
			)
		) . '">';
		$html .= '<span class="tg-stars" aria-hidden="true"><span class="tg-stars-fill" style="width:' . esc_attr( $pct ) . '%">★★★★★</span>★★★★★</span>';
		$html .= '<span aria-hidden="true">' . esc_html( number_format( $rating, 1 ) ) . '</span>';
		if ( $show_count && null !== $count ) {
			$html .= '<span class="tg-rating-count" aria-hidden="true">(' . esc_html( (string) $count ) . ')</span>';
		}
		$html .= '</span>';
		return $html;
	}
}

if ( ! function_exists( 'tg_status_badge' ) ) {
	/**
	 * Status badge markup (color + text, never color-only).
	 *
	 * @param string $status Status key.
	 * @param string $type   booking|payment.
	 * @return string
	 */
	function tg_status_badge( string $status, string $type = 'booking' ): string {
		$labels = ( 'payment' === $type ) ? TG_Bookings::payment_labels() : TG_Bookings::status_labels();
		$label  = $labels[ $status ] ?? $status;

		$classes = array(
			'pending'          => 'tg-badge--neutral',
			'awaiting_payment' => 'tg-badge--info',
			'confirmed'        => 'tg-badge--success',
			'paid'             => 'tg-badge--success',
			'partially_paid'   => 'tg-badge--info',
			'completed'        => 'tg-badge--primary',
			'cancelled'        => 'tg-badge--danger',
			'refund_requested' => 'tg-badge--secondary',
			'refunded'         => 'tg-badge--secondary',
			'failed'           => 'tg-badge--danger',
			'expired'          => 'tg-badge--neutral',
			'on_hold'          => 'tg-badge--secondary',
			'unpaid'           => 'tg-badge--neutral',
			'rejected'         => 'tg-badge--danger',
			'approved'         => 'tg-badge--success',
		);
		$class = $classes[ $status ] ?? 'tg-badge--neutral';

		return '<span class="tg-badge ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}
}

if ( ! function_exists( 'tg_ip' ) ) {
	/**
	 * Best-effort client IP.
	 *
	 * @return string
	 */
	function tg_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $ip ? $ip : '0.0.0.0';
	}
}

if ( ! function_exists( 'tg_rate_limit' ) ) {
	/**
	 * Simple transient-based rate limiter.
	 *
	 * @param string $key    Bucket key.
	 * @param int    $max    Max hits.
	 * @param int    $window Window seconds.
	 * @return true|WP_Error WP_Error when limited.
	 */
	function tg_rate_limit( string $key, int $max, int $window ) {
		$bucket = 'tg_rl_' . md5( $key );
		$now    = time();
		$state  = get_transient( $bucket );

		// Versions before 1.0.1 stored a scalar and then accidentally changed
		// its timeout to zero, which could block a visitor permanently. Discard
		// that legacy value instead of carrying the lock forward.
		if ( ! is_array( $state ) || ! isset( $state['count'], $state['reset'] ) || (int) $state['reset'] <= $now ) {
			$state = array(
				'count' => 0,
				'reset' => $now + max( 1, $window ),
			);
		}

		if ( (int) $state['count'] >= $max ) {
			return new WP_Error( 'tg_rate_limited', __( 'Too many requests. Please try again later.', 'guidegrid-travel' ) );
		}

		$state['count'] = (int) $state['count'] + 1;
		$ttl            = max( 1, (int) $state['reset'] - $now );
		set_transient( $bucket, $state, $ttl );
		return true;
	}
}

if ( ! function_exists( 'tg_page_id_by_path' ) ) {
	/**
	 * Find a page ID by its path slug.
	 *
	 * @param string $path Page slug path.
	 * @return int
	 */
	function tg_page_id_by_path( string $path ): int {
		$page = get_page_by_path( $path, OBJECT, 'page' );
		return $page ? $page->ID : 0;
	}
}

if ( ! function_exists( 'tg_page_url_by_template' ) ) {
	/**
	 * URL of the page assigned a template file (0 if none).
	 *
	 * @param string $template File name, e.g. 'page-templates/template-booking.php'.
	 * @return string
	 */
	function tg_page_url_by_template( string $template ): string {
		// WordPress stores templates relative to the theme root. Older theme
		// code passed only the basename even though activation stored the full
		// page-templates/... path, so the workflow page could never be found.
		$candidates = array( $template );
		if ( false === strpos( $template, '/' ) ) {
			$candidates[] = 'page-templates/' . $template;
		}

		foreach ( array_unique( $candidates ) as $candidate ) {
			$pages = get_pages(
				array(
					'meta_key'   => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value' => $candidate,          // phpcs:ignore WordPress.DB.SlowDBQuery
					'number'     => 1,
				)
			);
			if ( ! empty( $pages ) ) {
				return get_permalink( $pages[0]->ID );
			}
		}
		return '';
	}
}

if ( ! function_exists( 'tg_booking_page_url' ) ) {
	/**
	 * Checkout page URL (with tour pre-selected).
	 *
	 * @param int   $tour_id Tour post ID.
	 * @param array $args    Extra query args (date, adults...).
	 * @return string
	 */
	function tg_booking_page_url( int $tour_id ): string {
		$url = tg_page_url_by_template( 'page-templates/template-booking.php' );
		if ( ! $url ) {
			$page_id = tg_page_id_by_path( 'booking' );
			$url     = $page_id ? get_permalink( $page_id ) : add_query_arg( 'pagename', 'booking', home_url( '/' ) );
		}
		return add_query_arg( array( 'tour_id' => (string) $tour_id ), $url );
	}
}

if ( ! function_exists( 'tg_confirmation_page_url' ) ) {
	/**
	 * Confirmation page URL for a booking number.
	 *
	 * @param string $number Booking number.
	 * @return string
	 */
	function tg_confirmation_page_url( string $number ): string {
		$url = tg_page_url_by_template( 'page-templates/template-confirmation.php' );
		if ( ! $url ) {
			$page_id = tg_page_id_by_path( 'booking-confirmation' );
			$url     = $page_id ? get_permalink( $page_id ) : add_query_arg( 'pagename', 'booking-confirmation', home_url( '/' ) );
		}
		return add_query_arg( 'booking', $number, $url );
	}
}

if ( ! function_exists( 'tg_booking_lookup_url' ) ) {
	/**
	 * Booking lookup page URL.
	 *
	 * @return string
	 */
	function tg_booking_lookup_url(): string {
		$url = tg_page_url_by_template( 'page-templates/template-booking-lookup.php' );
		if ( $url ) {
			return $url;
		}
		$page_id = tg_page_id_by_path( 'booking-lookup' );
		return $page_id ? get_permalink( $page_id ) : add_query_arg( 'pagename', 'booking-lookup', home_url( '/' ) );
	}
}

if ( ! function_exists( 'tg_account_url' ) ) {
	/**
	 * My Account page URL.
	 *
	 * @param string $tab   Tab key.
	 * @return string
	 */
	function tg_account_url( string $tab = '' ): string {
		$url = tg_page_url_by_template( 'page-templates/template-my-account.php' );
		if ( ! $url ) {
			$page_id = tg_page_id_by_path( 'my-account' );
			$url     = $page_id ? get_permalink( $page_id ) : add_query_arg( 'pagename', 'my-account', home_url( '/' ) );
		}
		if ( $tab ) {
			$url = add_query_arg( 'tg_tab', $tab, $url );
		}
		return $url;
	}
}

if ( ! function_exists( 'tg_tour_book_url' ) ) {
	/**
	 * Checkout URL for a tour with pre-filled selections.
	 *
	 * @param int   $tour_id Tour post ID.
	 * @param array $args    date/adults/children/infants/addons.
	 * @return string
	 */
	function tg_tour_book_url( int $tour_id, array $args = array() ): string {
		$url   = tg_booking_page_url( $tour_id );
		$query = array();
		foreach ( $args as $k => $v ) {
			if ( '' === $v || null === $v ) {
				continue;
			}
			if ( is_array( $v ) ) {
				$query[ $k ] = implode( ',', array_filter( array_map( 'absint', $v ) ) );
			} else {
				$query[ $k ] = (string) $v;
			}
		}
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}
		return $url;
	}
}

if ( ! function_exists( 'tg_wishlist_state' ) ) {
	/**
	 * Wishlist state for the header (server side).
	 *
	 * @return array{count:int,ids:int[]}
	 */
	function tg_wishlist_state(): array {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$ids = TG_Wishlist::get( $user_id );
			return array(
				'count' => count( $ids ),
				'ids'   => $ids,
			);
		}
		return array(
			'count' => 0,
			'ids'   => array(),
		);
	}
}

if ( ! function_exists( 'tg_svg' ) ) {
	/**
	 * Inline SVG icon (stroke/fill paths, currentColor).
	 *
	 * @param string $name Icon key.
	 * @return string
	 */
	function tg_svg( string $name ): string {
		$icons = array(
			'pin'        => '<path d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/>',
			'clock'      => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm4.2 14.2L11 13V7h1.5v5.2l4.5 2.7-.8 1.3z"/>',
			'users'      => '<path d="M9 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3.3 0-8 1.7-8 5v3h16v-3c0-3.3-4.7-5-8-5zm7.5-3.5A4 4 0 1 0 12 6a4 4 0 0 0 4.5 3.5zM21 21v-3c0-2.2-2.6-3.8-5.6-4.6a6.8 6.8 0 0 1 5.6 4.6z"/>',
			'calendar'   => '<path d="M7 2v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm12 8v10H5V10h14zM5 8V6h14v2H5z"/>',
			'shield'     => '<path d="M12 2 4 5v6c0 5.25 3.4 10.15 8 11.4C16.6 21.15 20 16.25 20 11V5l-8-3zm-1.2 14.5-3.3-3.3 1.4-1.4 1.9 1.9 4.3-4.3 1.4 1.4-5.7 5.7z"/>',
			'support'    => '<path d="M12 3a9 9 0 0 0-9 9v5a3 3 0 0 0 3 3h2v-8H5v0a7 7 0 1 1 14 0h-3v8h2a3 3 0 0 0 3-3v-5a9 9 0 0 0-9-9z"/>',
			'check'      => '<path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>',
			'close'      => '<path d="M19 6.4 17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12 19 6.4z"/>',
			'heart'      => '<path d="M12 21s-7.5-4.7-10-9.3C.4 8.6 2.2 5 5.6 5c2 0 3.4 1.1 4.4 2.5h4c1-1.4 2.4-2.5 4.4-2.5 3.4 0 5.2 3.6 3.6 6.7C19.5 16.3 12 21 12 21z"/>',
			'search'     => '<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>',
			'user'       => '<path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4.4 0-8 2.2-8 5v3h16v-3c0-2.8-3.6-5-8-5z"/>',
			'menu'       => '<path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z"/>',
			'chevron'    => '<path d="m12 15.4-6-6 1.4-1.4 4.6 4.6 4.6-4.6L18 9.4l-6 6z"/>',
			'play'       => '<path d="M8 5v14l11-7L8 5z"/>',
			'expand'     => '<path d="M15 3h6v6h-2V6.4l-5.6 5.6-1.4-1.4L18.6 5H15V3zM3 15h2v3.4l5.6-5.6 1.4 1.4L6.4 19.8H9.4V22H3v-7z"/>',
			'mail'       => '<path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/>',
			'phone'      => '<path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/>',
			'globe'      => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.9 9h-3.4a15.7 15.7 0 0 0-1.4-6 8.1 8.1 0 0 1 4.8 6zM12 4.1c.9 1.2 2.4 3.7 2.9 7.9H9.1c.5-4.2 2-6.7 2.9-7.9zM4.1 11a8.1 8.1 0 0 1 4.8-6c-.9 1.9-1.5 4-1.7 6H4.1zm0 2h3.1c.2 2 .8 4.1 1.7 6a8.1 8.1 0 0 1-4.8-6zm7.9 8.9c-.9-1.2-2.4-3.7-2.9-7.9h5.8c-.5 4.2-2 6.7-2.9 7.9zm5.3-2.9c.9-1.9 1.5-4 1.7-6h3.1a8.1 8.1 0 0 1-4.8 6z"/>',
			'flag'       => '<path d="M5 3v18h2v-7h12l-2-4 2-4H7V3H5z"/>',
			'award'      => '<path d="M12 2a7 7 0 0 0-4.9 12L4 21l4.5-2A7 7 0 1 0 12 2zm1 9.6 3.5 1-1-3.9 3-2.6-4-.4L12 3l-1.5 3.7-4 .4 3 2.6-1 3.9L11 13l1-1.4z"/>',
			'gift'       => '<path d="M20 7h-2.5A3.5 3.5 0 0 0 12 3.2 3.5 3.5 0 0 0 6.5 7H4a1 1 0 0 0-1 1v3a2 2 0 0 0 2 2h1v7a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-7h1a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zM12 5a1.5 1.5 0 1 1-1.5 1.5A1.5 1.5 0 0 1 12 5zm-5.5 2a1.5 1.5 0 0 1 2.3-1.3A4.5 4.5 0 0 0 9 7H6.5zM9 19H7v-5h2v5zm8 0h-6v-5h6v5zm1-9V7h2.5v3H18z"/>',
			'wallet'     => '<path d="M21 7H5a2 2 0 0 1 0-4h14v2h-2a1 1 0 0 0 0 2h2v2H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1zm-4 9a1.5 1.5 0 1 1 1.5-1.5A1.5 1.5 0 0 1 17 16z"/>',
			'print'      => '<path d="M19 8H5a3 3 0 0 0-3 3v6h4v4h12v-4h4v-6a3 3 0 0 0-3-3zm-3 12H8v-4h8v4zm3-10v2H7v-2h12zM7 13h2v2H7v-2z"/>',
			'download'   => '<path d="M12 3v10.6l3.3-3.3 1.4 1.4L12 16.4 7.3 11.7 8.7 10.3 12 13.6V3h2zM5 19h14v2H5v-2z"/>',
			'eye'        => '<path d="M12 5c-5 0-9.3 3-11 7 1.7 4 6 7 11 7s9.3-3 11-7c-1.7-4-6-7-11-7zm0 12a5 5 0 1 1 5-5 5 5 0 0 1-5 5zm0-8a3 3 0 1 0 3 3 3 3 0 0 0-3-3z"/>',
			'edit'       => '<path d="M3 17.2V21h3.8L17.8 9.9l-3.8-3.8L3 17.2zM20.7 7a1 1 0 0 0 0-1.4L18.4 3.3a1 1 0 0 0-1.4 0l-1.8 1.8 3.7 3.7L20.7 7z"/>',
			'trash'      => '<path d="M6 7v14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7h8V5H4v2h2zm14 2-1 12H7L6 9h14zM9 13v6h2v-6H9zm4 0v6h2v-6h-2z"/>',
			'note'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>',
			'book'       => '<path d="M6 2h13a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h12V4H6z"/>',
			'compass'    => '<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm4.5 13.9-4.6 1.9a1 1 0 0 1-1.2-.4L6.1 12a1 1 0 0 1 .4-1.2l4.6-1.9a1 1 0 0 1 1.2.4l4.6 5.4a1 1 0 0 1-.4 1.2z"/>',
			'map'        => '<path d="M20.5 3 14 6.3 8 3 2.5 5.6a1 1 0 0 0-.5.9V20l6.5-3.3 6 3.3 5.5-2.6a1 1 0 0 0 .5-.9V4a1 1 0 0 0-1-1zM8 15.5 3 18V6.6L8 4.3zm2-1.3V4.4l4 2.2v9.8l-4-2.2zm6 1.3-4-2.2V7l5-2.4V15.5z"/>',
			'temperature'=> '<path d="M15 13.4V4a3 3 0 1 0-6 0v9.4a5 5 0 1 0 6 0zM9 4a1 1 0 0 1 2 0v9.5a3 3 0 1 1-2 0V4z"/>',
			'currency'   => '<path d="M11 3v2h4a3 3 0 0 1 0 6H9a3 3 0 0 0 0 6h6v2H7v-2h4a5 5 0 1 0 0-10H7V3h4z"/>',
			'language'   => '<path d="m12.9 6.3-2-2L15 2.3 13.1.3l1.8-1.8h-2l-1.9 1.9L8.3-1H6.3l2.5 2.5-2 2 1.4 1.4-3.1 3.1a12 12 0 0 1 7.8 1.3zM0 12a12 12 0 0 0 24 0H0zm18 6h-2.5a8.7 8.7 0 0 1-2.3-3.4 12.4 12.4 0 0 1 4.8 3.4zm-12 0c1-1.2 2.4-3 2.4-5H6c0 2 1.4 3.8 2.4 5H6zm1.5-7c.8-1 2-2.4 2.8-3.8A10.4 10.4 0 0 0 4.3 8h3.2z"/>',
			'child'      => '<path d="M12 2a8 8 0 0 0-8 8c0 2.5 1.2 4.8 3 6.2V19a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-2.8c1.8-1.4 3-3.7 3-6.2a8 8 0 0 0-8-8zm-2 11a1.5 1.5 0 1 1 1.5-1.5A1.5 1.5 0 0 1 10 13zm4 0a1.5 1.5 0 1 1 1.5-1.5A1.5 1.5 0 0 1 14 13z"/>',
			'ticket'     => '<path d="M20 8h-2a2 2 0 1 0 0 4h2v3a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-3h2a2 2 0 1 0 0-4H2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3zm-9 4a1 1 0 0 0 0-2 1 1 0 0 0 0 2z"/>',
			'filter'     => '<path d="M3 5h18l-7 8v6l-4 2v-8L3 5z"/>',
			'sort'       => '<path d="m7 15-4-4 4-4 1.4 1.4L5.8 10.6H16v2H5.8l2.6 2.6L7 15zm10 4-1.4-1.4 2.6-2.6H8v-2h10.2l-2.6-2.6L17 9l4 4-4 4z"/>',
			'check-circle' => '<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm-1.2 14.5-4-4 1.4-1.4 2.6 2.6 5.2-5.2 1.4 1.4-6.6 6.6z"/>',
			'arrow'      => '<path d="M13.4 10.6 8.8 6 10.2 4.6 17 11l-6.8 6.4-1.4-1.4 4.6-4.6z"/>',
		);

		$path = $icons[ $name ] ?? $icons['check'];
		return '<svg class="tg-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}
} 

if ( ! function_exists( 'tg_lucide' ) ) {
	/**
	 * Inline SVG icon (stroke/fill paths, currentColor).
	 *
	 * @param string $name Icon key.
	 * @return string
	 */
	function tg_lucide( string $name ): string {
		$icons = array(
			'search'     => '<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>', 
			'heart'     => '<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"/>', 
			'question-mark'     => '<path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>', 
			'user'     => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>', 
			'menu'     => '<path d="M3 5h18"/><path d="M3 12h18"/><path d="M3 19h18"/>', 
		);

		$path = $icons[ $name ] ?? $icons['check'];
		return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search preview-icon" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}
} 

if ( ! function_exists( 'tg_tour_gallery_ids' ) ) {
	/**
	 * Gallery attachment IDs for a tour.
	 *
	 * @param int $tour_id Tour post ID.
	 * @return int[]
	 */
	function tg_tour_gallery_ids( int $tour_id ): array {
		$ids = (array) tg_get_meta( $tour_id, '_tg_gallery', array() );
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}
}

if ( ! function_exists( 'tg_get_next_available_date' ) ) {
	/**
	 * Next bookable date for a tour (empty string when none).
	 *
	 * @param int $tour_id Tour post ID.
	 * @return string
	 */
	function tg_get_next_available_date( int $tour_id ): string {
		$dates = TG_Availability::get_dates( $tour_id );
		return $dates ? $dates[0]['date'] : '';
	}
}

if ( ! function_exists( 'tg_difficulty_label' ) ) {
	/**
	 * Difficulty label + icon.
	 *
	 * @param string $key Difficulty key.
	 * @return string
	 */
	function tg_difficulty_label( string $key ): string {
		$labels = array(
			'easy'       => __( 'Easy', 'guidegrid-travel' ),
			'moderate'   => __( 'Moderate', 'guidegrid-travel' ),
			'challenging' => __( 'Challenging', 'guidegrid-travel' ),
		);
		return $labels[ $key ] ?? __( '—', 'guidegrid-travel' );
	}
}

if ( ! function_exists( 'tg_demo_img' ) ) {
	/**
	 * Demo image URL (free remote placeholder).
	 *
	 * @param string $seed Seed.
	 * @param int    $w    Width.
	 * @param int    $h    Height.
	 * @return string
	 */
	function tg_demo_img( string $seed, int $w = 800, int $h = 600 ): string {
		return 'https://picsum.photos/seed/' . rawurlencode( $seed ) . '/' . $w . '/' . $h;
	}
}

if ( ! function_exists( 'tg_tour_image_url' ) ) {
	/**
	 * Best available image URL for a tour (featured image or demo URL).
	 *
	 * @param int $tour_id Tour post ID.
	 * @return string
	 */
	function tg_tour_image_url( int $tour_id ): string {
		if ( has_post_thumbnail( $tour_id ) ) {
			return (string) get_the_post_thumbnail_url( $tour_id, 'full' );
		}
		return (string) tg_get_meta( $tour_id, '_tg_demo_image', tg_demo_img( 'tg-tour-' . (string) $tour_id, 1200, 700 ) );
	}
}

if ( ! function_exists( 'tg_guest_label' ) ) {
	/**
	 * Guest type labels.
	 *
	 * @return array
	 */
	function tg_guest_labels(): array {
		return array(
			'adults'   => array(
				'label' => __( 'Adults', 'guidegrid-travel' ),
				'desc'  => __( 'Age 12+', 'guidegrid-travel' ),
			),
			'children' => array(
				'label' => __( 'Children', 'guidegrid-travel' ),
				'desc'  => __( 'Age 2–11', 'guidegrid-travel' ),
			),
			'infants'  => array(
				'label' => __( 'Infants', 'guidegrid-travel' ),
				'desc'  => __( 'Under 2', 'guidegrid-travel' ),
			),
		);
	}
}
