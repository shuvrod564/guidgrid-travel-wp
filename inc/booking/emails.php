<?php
/**
 * Email notifications.
 *
 * All templates run through the tg_email_content / tg_email_subject
 * filters so they can be re-skinned or overridden. Emails use the
 * configured sender name/email and a simple, safe HTML layout.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TG_Emails
 */
final class TG_Emails {

	/**
	 * Send an email with the shared layout.
	 *
	 * @param string $key         Template key (for filters + logs).
	 * @param string $to          Recipient.
	 * @param string $subject     Subject.
	 * @param string $inner_html  Inner content HTML.
	 * @param array  $headers     Extra headers.
	 * @return bool
	 */
	public static function send( string $key, string $to, string $subject, string $inner_html, array $headers = array() ): bool {
		if ( ! $to || ! is_email( $to ) ) {
			return false;
		}

		$settings = tg_settings();
		$subject  = apply_filters( 'tg_email_subject', $subject, $key );
		$content  = self::wrap( $key, $inner_html );
		$content  = apply_filters( 'tg_email_content', $content, $key, $inner_html );

		$headers  = array_merge(
			array(
				'From: ' . $settings['email_from_name'] . ' <' . $settings['email_from_email'] . '>',
				'Content-Type: text/html; charset=UTF-8',
			),
			$headers
		);

		do_action( 'tg_before_email', $key, $to, $subject, $content );

		$sent = wp_mail( $to, $subject, $content, $headers );

		do_action( 'tg_after_email', $key, $to, $subject, $content, $sent );

		return (bool) $sent;
	}

	/**
	 * Simple HTML wrapper.
	 *
	 * @param string $key     Template key.
	 * @param string $inner   Inner HTML.
	 * @return string
	 */
	private static function wrap( string $key, string $inner ): string {
		$site_name = esc_html( get_bloginfo( 'name' ) );
		$footer    = sprintf(
			'<p style="color:#87919B;font-size:12px;">%s</p>',
			esc_html( sprintf( /* translators: %s: site name */ __( 'You are receiving this email from %s.', 'guidegrid-travel' ), get_bloginfo( 'name' ) ) )
		);

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#F7F9FA;font-family:Arial,Helvetica,sans-serif;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F9FA;padding:24px 0;">'
			. '<tr><td align="center"><table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border-radius:12px;overflow:hidden;">'
			. '<tr><td style="background:#0B6E69;padding:22px 32px;"><span style="color:#FFFFFF;font-size:18px;font-weight:bold;">' . $site_name . '</span></td></tr>'
			. '<tr><td style="padding:28px 32px;color:#5B6570;font-size:14px;line-height:1.6;">' . $inner . '</td></tr>'
			. '<tr><td style="padding:18px 32px;background:#F7F9FA;border-top:1px solid #E6E9EC;">' . $footer . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	/**
	 * Shared booking detail block.
	 *
	 * @param object $booking Booking row.
	 * @return string
	 */
	private static function booking_block( object $booking ): string {
		$tour     = get_post( (int) $booking->tour_id );
		$tour_url = $tour ? get_permalink( $tour ) : '';
		$tour_name = $tour ? get_the_title( $tour ) : __( 'Tour', 'guidegrid-travel' );

		$settings = tg_settings();
		$date_fmt = $settings['date_format'];

		$rows = array(
			__( 'Booking number', 'guidegrid-travel' ) => esc_html( $booking->booking_number ),
			__( 'Tour', 'guidegrid-travel' )           => '<a href="' . esc_url( $tour_url ) . '">' . esc_html( $tour_name ) . '</a>',
			__( 'Travel date', 'guidegrid-travel' )    => esc_html( tg_format_date( $booking->booking_date, $date_fmt ) ),
			__( 'Guests', 'guidegrid-travel' )         => esc_html(
				sprintf(
					/* translators: 1: adults, 2: children, 3: infants */
					_n( '%1$d adult, %2$d child, %3$d infant', '%1$d adults, %2$d children, %3$d infants', (int) $booking->adult_count + (int) $booking->child_count + (int) $booking->infant_count, 'guidegrid-travel' ),
					(int) $booking->adult_count,
					(int) $booking->child_count,
					(int) $booking->infant_count
				)
			),
			__( 'Total', 'guidegrid-travel' )          => '<strong>' . esc_html( tg_format_price( (float) $booking->total, $booking->currency ) ) . '</strong>',
			__( 'Payment status', 'guidegrid-travel' ) => esc_html( TG_Bookings::payment_labels()[ $booking->payment_status ] ?? $booking->payment_status ),
			__( 'Booking status', 'guidegrid-travel' ) => esc_html( TG_Bookings::status_labels()[ $booking->booking_status ] ?? $booking->booking_status ),
		);

		$html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E6E9EC;border-radius:8px;margin:16px 0;">';
		foreach ( $rows as $label => $value ) {
			$html .= '<tr><td style="padding:10px 14px;border-bottom:1px solid #E6E9EC;color:#87919B;font-size:13px;width:40%;">' . $label . '</td>'
				. '<td style="padding:10px 14px;border-bottom:1px solid #E6E9EC;color:#172026;font-size:13px;">' . $value . '</td></tr>';
		}
		return $html . '</table>';
	}

	/* ------------------------------------------------------------------
	 * Customer emails
	 * ------------------------------------------------------------------ */

	/**
	 * Booking received (customer).
	 *
	 * @param object $booking Booking row.
	 * @return bool
	 */
	public static function booking_received( object $booking ): bool {
		$c  = TG_Bookings::customer( $booking );
		$to = $c['email'] ?? '';
		if ( ! $to ) {
			return false;
		}

		$first = isset( $c['first_name'] ) && $c['first_name'] ? $c['first_name'] : '';
		$greet = $first
			/* translators: %s: customer first name */
			? sprintf( __( 'Thank you for your booking, %s!', 'guidegrid-travel' ), $first )
			: __( 'Thank you for your booking!', 'guidegrid-travel' );

		$payment_note = '';
		$payment       = TG_Payments::latest_for_booking( (int) $booking->id );
		$method        = $payment ? (string) $payment->payment_method : '';
		$method_label  = $method ? TG_Payments::method_label( $method ) : '';
		if ( 'awaiting_payment' === $booking->booking_status ) {
			$payment_note = '<p><strong>' . esc_html__( 'Selected payment method:', 'guidegrid-travel' ) . '</strong> ' . esc_html( $method_label ) . '</p>';
			if ( isset( TG_Payments::manual_methods()[ $method ] ) ) {
				$payment_note .= '<p>' . esc_html( TG_Payments::manual_instructions( $method ) ) . '</p>';
			} else {
				$payment_note .= '<p>' . esc_html__( 'Complete the secure online checkout to confirm your booking.', 'guidegrid-travel' ) . '</p>';
			}
			$payment_note .= '<p>' .
				sprintf(
					/* translators: %s: hold minutes */
					__( 'Unpaid bookings are automatically released after %s minutes.', 'guidegrid-travel' ),
					TG_Payments::hold_minutes_for_method( $method )
				) .
				'</p>';
		} elseif ( 'confirmed' === $booking->booking_status && 'unpaid' === $booking->payment_status && TG_Payments::is_deferred_manual( $method ) ) {
			$payment_note = '<p><strong>' . esc_html__( 'Selected payment method:', 'guidegrid-travel' ) . '</strong> ' . esc_html( $method_label ) . '<br>' . esc_html( TG_Payments::manual_instructions( $method ) ) . '</p>';
		}

		$inner = '<h1 style="color:#172026;font-size:20px;margin:0 0 8px;">' . esc_html( $greet ) . '</h1>'
			. '<p>' . esc_html( __( 'We have received your booking. Here are the details:', 'guidegrid-travel' ) ) . '</p>'
			. self::booking_block( $booking )
			. $payment_note
			. '<p><a href="' . esc_url( tg_booking_lookup_url() ) . '" style="color:#0B6E69;">' . esc_html__( 'View your booking', 'guidegrid-travel' ) . '</a></p>';

		$subject = sprintf(
			/* translators: 1: site name, 2: booking number */
			__( '[%1$s] Booking received – %2$s', 'guidegrid-travel' ),
			get_bloginfo( 'name' ),
			$booking->booking_number
		);

		return self::send( 'booking_received', $to, $subject, $inner );
	}

	/**
	 * Payment received (customer).
	 *
	 * @param object $booking Booking row.
	 * @return bool
	 */
	public static function payment_received( object $booking ): bool {
		$c  = TG_Bookings::customer( $booking );
		$to = $c['email'] ?? '';
		if ( ! $to ) {
			return false;
		}

		$inner = '<h1 style="color:#172026;font-size:20px;margin:0 0 8px;">' . esc_html__( 'Payment received', 'guidegrid-travel' ) . '</h1>'
			. '<p>' . esc_html__( 'We have received your payment. Your booking details:', 'guidegrid-travel' ) . '</p>'
			. self::booking_block( $booking );

		$subject = sprintf(
			/* translators: %s: booking number */
			__( 'Payment received – %s', 'guidegrid-travel' ),
			$booking->booking_number
		);

		return self::send( 'payment_received', $to, $subject, $inner );
	}

	/**
	 * Booking confirmed (customer).
	 *
	 * @param object $booking Booking row.
	 * @return bool
	 */
	public static function booking_confirmed( object $booking ): bool {
		$c  = TG_Bookings::customer( $booking );
		$to = $c['email'] ?? '';
		if ( ! $to ) {
			return false;
		}

		$inner = '<h1 style="color:#2E9B5B;font-size:20px;margin:0 0 8px;">' . esc_html__( 'Your booking is confirmed', 'guidegrid-travel' ) . '</h1>'
			. '<p>' . esc_html__( 'We look forward to welcoming you. Your confirmed booking:', 'guidegrid-travel' ) . '</p>'
			. self::booking_block( $booking );

		$subject = sprintf(
			/* translators: %s: booking number */
			__( 'Booking confirmed – %s', 'guidegrid-travel' ),
			$booking->booking_number
		);

		return self::send( 'booking_confirmed', $to, $subject, $inner );
	}

	/**
	 * Booking cancelled (customer).
	 *
	 * @param object $booking Booking row.
	 * @return bool
	 */
	public static function booking_cancelled( object $booking ): bool {
		$c  = TG_Bookings::customer( $booking );
		$to = $c['email'] ?? '';
		if ( ! $to ) {
			return false;
		}

		$inner = '<h1 style="color:#D64545;font-size:20px;margin:0 0 8px;">' . esc_html__( 'Booking cancelled', 'guidegrid-travel' ) . '</h1>'
			. '<p>' . esc_html__( 'Your booking has been cancelled. Details:', 'guidegrid-travel' ) . '</p>'
			. self::booking_block( $booking )
			. '<p>' . esc_html__( 'Please contact us if you believe this is a mistake.', 'guidegrid-travel' ) . '</p>';

		$subject = sprintf(
			/* translators: %s: booking number */
			__( 'Booking cancelled – %s', 'guidegrid-travel' ),
			$booking->booking_number
		);

		return self::send( 'booking_cancelled', $to, $subject, $inner );
	}

	/**
	 * Refund processed (customer).
	 *
	 * @param object $booking Booking row.
	 * @param float  $amount  Refunded amount.
	 * @param string $reason  Reason.
	 * @return bool
	 */
	public static function refund_processed( object $booking, float $amount, string $reason = '' ): bool {
		$c  = TG_Bookings::customer( $booking );
		$to = $c['email'] ?? '';
		if ( ! $to ) {
			return false;
		}

		$inner = '<h1 style="color:#172026;font-size:20px;margin:0 0 8px;">' . esc_html__( 'Refund processed', 'guidegrid-travel' ) . '</h1>'
			. '<p>' . esc_html__( 'Your refund has been processed:', 'guidegrid-travel' ) . '</p>'
			. self::booking_block( $booking )
			. '<p>' .
			__( 'Refund amount:', 'guidegrid-travel' ) . ' <strong>' . esc_html( tg_format_price( $amount, $booking->currency ) ) . '</strong>'
			. ( $reason ? '<br>' . esc_html__( 'Reason:', 'guidegrid-travel' ) . ' ' . esc_html( $reason ) : '' )
			. '<br>' . esc_html__( 'Please allow 5–10 business days for the amount to appear in your account.', 'guidegrid-travel' )
			. '</p>';

		$subject = sprintf(
			/* translators: %s: booking number */
			__( 'Refund processed – %s', 'guidegrid-travel' ),
			$booking->booking_number
		);

		return self::send( 'refund_processed', $to, $subject, $inner );
	}

	/**
	 * Booking reminder (customer).
	 *
	 * @param object $booking Booking row.
	 * @param int    $days    Days before travel.
	 * @return bool
	 */
	public static function booking_reminder( object $booking, int $days ): bool {
		$c  = TG_Bookings::customer( $booking );
		$to = $c['email'] ?? '';
		if ( ! $to ) {
			return false;
		}

		$settings = tg_settings();
		$inner    = '<h1 style="color:#172026;font-size:20px;margin:0 0 8px;">' .
			esc_html(
				sprintf(
					/* translators: %d: days */
					_n( 'Your trip starts in %d day', 'Your trip starts in %d days', $days, 'guidegrid-travel' ),
					$days
				)
			) . '</h1>'
			. self::booking_block( $booking )
			. '<p>' . esc_html__( 'Please review the meeting point and important information on the tour page before you travel.', 'guidegrid-travel' ) . '</p>';

		$subject = sprintf(
			/* translators: 1: days, 2: booking number */
			__( 'Trip reminder: %1$d day(s) to go – %2$s', 'guidegrid-travel' ),
			$days,
			$booking->booking_number
		);

		$settings = tg_settings(); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return self::send( 'booking_reminder', $to, $subject, $inner );
	}

	/**
	 * Review request (customer).
	 *
	 * @param object $booking Booking row.
	 * @return bool
	 */
	public static function review_request( object $booking ): bool {
		$c  = TG_Bookings::customer( $booking );
		$to = $c['email'] ?? '';
		if ( ! $to ) {
			return false;
		}
		$tour = get_post( (int) $booking->tour_id );
		if ( ! $tour ) {
			return false;
		}

		$inner = '<h1 style="color:#172026;font-size:20px;margin:0 0 8px;">' . esc_html__( 'How was your trip?', 'guidegrid-travel' ) . '</h1>'
			. '<p>' .
			sprintf(
				/* translators: %s: tour title */
				__( 'Thank you for joining us on %s. Would you take a moment to leave a review?', 'guidegrid-travel' ),
				'<strong>' . esc_html( get_the_title( $tour ) ) . '</strong>'
			) .
			'</p><p><a href="' . esc_url( get_permalink( $tour ) . '#tg-reviews' ) . '" style="color:#0B6E69;">' . esc_html__( 'Write a review', 'guidegrid-travel' ) . '</a></p>';

		$subject = sprintf(
			/* translators: %s: tour title */
			__( 'We would love your feedback on %s', 'guidegrid-travel' ),
			get_the_title( $tour )
		);

		return self::send( 'review_request', $to, $subject, $inner );
	}

	/* ------------------------------------------------------------------
	 * Admin emails
	 * ------------------------------------------------------------------ */

	/**
	 * Admin: new booking.
	 *
	 * @param object $booking Booking row.
	 * @return bool
	 */
	public static function new_booking_admin( object $booking ): bool {
		$to = tg_settings()['admin_email'] ? tg_settings()['admin_email'] : get_option( 'admin_email' );
		if ( ! $to ) {
			return false;
		}

		$c     = TG_Bookings::customer( $booking );
		$inner = '<h1 style="color:#172026;font-size:20px;margin:0 0 8px;">' . esc_html__( 'New booking', 'guidegrid-travel' ) . '</h1>'
			. '<p><strong>' . esc_html( $c['first_name'] . ' ' . $c['last_name'] ) . '</strong> (' . esc_html( $c['email'] ) . ')'
			. ( ! empty( $c['phone'] ) ? ' / ' . esc_html( $c['phone'] ) : '' ) . '</p>'
			. self::booking_block( $booking )
			. '<p><a href="' . esc_url( admin_url( 'admin.php?page=tg-booking-detail&booking=' . (int) $booking->id ) ) . '" style="color:#0B6E69;">' . esc_html__( 'Manage in dashboard', 'guidegrid-travel' ) . '</a></p>';

		$subject = sprintf(
			/* translators: %s: booking number */
			__( 'New booking – %s', 'guidegrid-travel' ),
			$booking->booking_number
		);

		return self::send( 'admin_new_booking', $to, $subject, $inner );
	}

	/**
	 * Admin: new enquiry.
	 *
	 * @param object $enquiry Enquiry row.
	 * @return bool
	 */
	public static function new_enquiry_admin( object $enquiry ): bool {
		$settings = tg_settings();
		$to       = $settings['enquiry_notify_email'] ? $settings['enquiry_notify_email'] : get_option( 'admin_email' );
		if ( ! $to ) {
			return false;
		}

		$inner = '<h1 style="color:#172026;font-size:20px;margin:0 0 8px;">' . esc_html__( 'New travel enquiry', 'guidegrid-travel' ) . '</h1>'
			. '<p><strong>' . esc_html( $enquiry->name ) . '</strong> (' . esc_html( $enquiry->email ) . ')'
			. ( $enquiry->phone ? ' / ' . esc_html( $enquiry->phone ) : '' ) . '</p>'
			. '<p>' .
			__( 'Destination:', 'guidegrid-travel' ) . ' ' . esc_html( $enquiry->destination ) . '<br>'
			. __( 'Travel date:', 'guidegrid-travel' ) . ' ' . esc_html( tg_format_date( (string) $enquiry->travel_date ) ) . '<br>'
			. __( 'Travelers:', 'guidegrid-travel' ) . ' ' . esc_html( (string) $enquiry->travelers ) . '<br>'
			. __( 'Budget:', 'guidegrid-travel' ) . ' ' . esc_html( $enquiry->budget ) . '</p>'
			. ( $enquiry->message ? '<p style="background:#F7F9FA;padding:12px;border-radius:8px;">' . nl2br( esc_html( $enquiry->message ) ) . '</p>' : '' );

		$subject = sprintf(
			/* translators: %s: enquiry name */
			__( 'New enquiry from %s', 'guidegrid-travel' ),
			$enquiry->name
		);

		return self::send( 'admin_new_enquiry', $to, $subject, $inner );
	}

	/**
	 * Admin: new review awaiting moderation.
	 *
	 * @param object $review Review row.
	 * @return bool
	 */
	public static function new_review_admin( object $review ): bool {
		$to = get_option( 'admin_email' );
		if ( ! $to ) {
			return false;
		}
		$tour = get_post( (int) $review->tour_id );

		$inner = '<h1 style="color:#172026;font-size:20px;margin:0 0 8px;">' . esc_html__( 'New review', 'guidegrid-travel' ) . '</h1>'
			. '<p><strong>' . esc_html( $review->name ) . '</strong> — ' . esc_html( str_repeat( '★', (int) $review->rating ) ) . ' — ' . esc_html( get_the_title( $tour ) ) . '</p>'
			. ( $review->title ? '<p><strong>' . esc_html( $review->title ) . '</strong></p>' : '' )
			. '<p>' . nl2br( esc_html( (string) $review->content ) ) . '</p>';

		$subject = sprintf(
			/* translators: %s: tour title */
			__( 'New review for %s', 'guidegrid-travel' ),
			$tour ? get_the_title( $tour ) : '#' . (int) $review->tour_id
		);

		return self::send( 'admin_new_review', $to, $subject, $inner );
	}
}
