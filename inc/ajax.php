<?php
/**
 * AJAX endpoints.
 *
 * Every endpoint: verifies the nonce, sanitizes + validates input,
 * checks permissions where required, recalculates server-side and
 * returns structured JSON.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check the shared frontend nonce.
 *
 * @return bool
 */
function tg_ajax_verify_nonce(): bool {
	$nonce = isset( $_REQUEST['tg_nonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['tg_nonce'] ) ) : '';
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'tg_frontend' ) ) {
		return false;
	}
	return true;
}

if ( ! function_exists( 'tg_ajax_available_dates' ) ) {
	/**
	 * GET available + priced dates for a tour.
	 *
	 * @return void
	 */
	function tg_ajax_available_dates() {
		if ( ! tg_ajax_verify_nonce() ) {
			wp_send_json_error( array( 'message' => 'Invalid session. Refresh the page.' ), 403 );
		}

		$tour_id = isset( $_POST['tour_id'] ) ? absint( $_POST['tour_id'] ) : 0;
		$tour    = $tour_id ? get_post( $tour_id ) : null;
		if ( ! $tour || 'tour' !== $tour->post_type || 'publish' !== $tour->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Tour not found.', 'guidegrid-travel' ) ), 404 );
		}

		$dates = TG_Availability::get_dates( $tour_id );

		wp_send_json_success(
			array(
				'dates' => $dates,
			)
		);
	}
}
add_action( 'wp_ajax_tg_available_dates', 'tg_ajax_available_dates' );
add_action( 'wp_ajax_nopriv_tg_available_dates', 'tg_ajax_available_dates' );

if ( ! function_exists( 'tg_ajax_quote' ) ) {
    /**
     * Calculate a live quote.
     *
     * @return void
     */
    function tg_ajax_quote() {
        if ( ! tg_ajax_verify_nonce() ) {
            wp_send_json_error( array( 'message' => __( 'Invalid session. Refresh the page.', 'guidegrid-travel' ) ), 403 );
        }

        // Sanitize multidimensional array inputs properly
        $raw_addons = isset( $_POST['addons'] ) ? (array) wp_unslash( $_POST['addons'] ) : array();
        $addons     = array_map( 'sanitize_text_field', $raw_addons );

        $params = array(
            'tour_id'  => isset( $_POST['tour_id'] ) ? absint( $_POST['tour_id'] ) : 0,
            'date'      => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
            'adults'   => isset( $_POST['adults'] ) ? absint( $_POST['adults'] ) : 0,
            'children' => isset( $_POST['children'] ) ? absint( $_POST['children'] ) : 0,
            'infants'  => isset( $_POST['infants'] ) ? absint( $_POST['infants'] ) : 0,
            'addons'   => $addons,
            'coupon'   => isset( $_POST['coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon'] ) ) : '',
            'email'    => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
        );

        // Fail early if minimal required data is missing
        if ( empty( $params['tour_id'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid tour ID.', 'guidegrid-travel' ) ), 400 );
        }

        $quote = TG_Pricing::calculate_quote( $params );

        // Fallback check to prevent PHP warnings/fatals on malformed filters.
        if ( ! is_array( $quote ) ) {
            wp_send_json_error( array( 'message' => __( 'Calculation error.', 'guidegrid-travel' ) ), 500 );
        }

        wp_send_json_success( tg_quote_response_payload( $quote ) );
    }
}
add_action( 'wp_ajax_tg_quote', 'tg_ajax_quote' );
add_action( 'wp_ajax_nopriv_tg_quote', 'tg_ajax_quote' );


if ( ! function_exists( 'tg_ajax_create_booking' ) ) {
	/**
	 * Create a booking (full server-side validation).
	 *
	 * @return void
	 */
	function tg_ajax_create_booking() {
		if ( ! tg_ajax_verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid session. Please refresh the page and try again.', 'guidegrid-travel' ) ), 403 );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message'   => __( 'Log in or create an account before confirming your booking.', 'guidegrid-travel' ),
					'login_url' => tg_auth_url( wp_get_referer() ? wp_get_referer() : home_url( '/' ), 'login' ),
				),
				401
			);
		}

		$limit = tg_rate_limit( 'booking:' . tg_ip(), 5, 300 );
		if ( is_wp_error( $limit ) ) {
			wp_send_json_error( array( 'message' => $limit->get_error_message() ), 429 );
		}

		$customer_raw = isset( $_POST['customer'] ) && is_array( $_POST['customer'] ) ? (array) wp_unslash( $_POST['customer'] ) : array();
		$customer     = array(
			'first_name'        => isset( $customer_raw['first_name'] ) ? sanitize_text_field( $customer_raw['first_name'] ) : '',
			'last_name'         => isset( $customer_raw['last_name'] ) ? sanitize_text_field( $customer_raw['last_name'] ) : '',
			'email'             => isset( $customer_raw['email'] ) ? sanitize_email( $customer_raw['email'] ) : '',
			'phone'             => isset( $customer_raw['phone'] ) ? sanitize_text_field( $customer_raw['phone'] ) : '',
			'country'           => isset( $customer_raw['country'] ) ? sanitize_text_field( $customer_raw['country'] ) : '',
			'address'           => isset( $customer_raw['address'] ) ? sanitize_textarea_field( $customer_raw['address'] ) : '',
			'special_request'   => isset( $customer_raw['special_request'] ) ? sanitize_textarea_field( $customer_raw['special_request'] ) : '',
			'emergency_contact' => isset( $customer_raw['emergency_contact'] ) ? sanitize_text_field( $customer_raw['emergency_contact'] ) : '',
		);

		$addons = isset( $_POST['addons'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['addons'] ) ) : array();
		$method = isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : 'bank';

		$result = TG_Bookings::create(
			array(
				'tour_id'        => isset( $_POST['tour_id'] ) ? absint( $_POST['tour_id'] ) : 0,
				'date'           => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
				'adults'         => isset( $_POST['adults'] ) ? absint( $_POST['adults'] ) : 0,
				'children'       => isset( $_POST['children'] ) ? absint( $_POST['children'] ) : 0,
				'infants'        => isset( $_POST['infants'] ) ? absint( $_POST['infants'] ) : 0,
				'addons'         => $addons,
				'coupon'         => isset( $_POST['coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon'] ) ) : '',
				'payment_method' => $method,
				'customer'       => $customer,
				'user_id'        => get_current_user_id(),
				'source'         => 'frontend',
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$payment_url   = '';
		$payment_error = '';
		if ( isset( TG_Payments::adapters()[ $method ] ) ) {
			$checkout = TG_Payments::initiate( $result, $method );
			if ( is_wp_error( $checkout ) ) {
				$payment_error = $checkout->get_error_message();
			} else {
				$payment_url = (string) $checkout['url'];
			}
		}

		wp_send_json_success(
			array(
				'booking_number' => $result->booking_number,
				'total'          => tg_format_price( (float) $result->total, $result->currency ),
				'currency'       => $result->currency,
				'status'         => TG_Bookings::status_labels()[ $result->booking_status ] ?? $result->booking_status,
				'payment_status' => TG_Bookings::payment_labels()[ $result->payment_status ] ?? $result->payment_status,
				'confirmation_url' => tg_confirmation_page_url( $result->booking_number ),
				'payment_url'      => $payment_url,
				'payment_error'    => $payment_error,
			)
		);
	}
}
add_action( 'wp_ajax_tg_create_booking', 'tg_ajax_create_booking' );
add_action( 'wp_ajax_nopriv_tg_create_booking', 'tg_ajax_create_booking' );

if ( ! function_exists( 'tg_ajax_lookup_booking' ) ) {
	/**
	 * Booking lookup by number + email (rate-limited).
	 *
	 * @return void
	 */
	function tg_ajax_lookup_booking() {
		if ( ! tg_ajax_verify_nonce() ) {
			wp_send_json_error( array( 'message' => 'Invalid session. Refresh the page.' ), 403 );
		}

		$limit = tg_rate_limit( 'lookup:' . tg_ip(), 10, 900 );
		if ( is_wp_error( $limit ) ) {
			wp_send_json_error( array( 'message' => $limit->get_error_message() ), 429 );
		}

		$number = isset( $_POST['number'] ) ? sanitize_text_field( wp_unslash( $_POST['number'] ) ) : '';
		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		$result = TG_Bookings::lookup( $number, $email );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 404 );
		}

		$tour  = get_post( (int) $result->tour_id );
		$c     = TG_Bookings::customer( $result );
		$guests = (int) $result->adult_count + (int) $result->child_count + (int) $result->infant_count;

		wp_send_json_success(
			array(
				'booking_number' => $result->booking_number,
				'tour'           => $tour ? get_the_title( $tour ) : '',
				'tour_url'       => $tour ? get_permalink( $tour ) : '',
				'date'           => tg_format_date( $result->booking_date ),
				'guests'         => $guests,
				'total'          => tg_format_price( (float) $result->total, $result->currency ),
				'payment_status' => TG_Bookings::payment_labels()[ $result->payment_status ] ?? $result->payment_status,
				'booking_status' => TG_Bookings::status_labels()[ $result->booking_status ] ?? $result->booking_status,
			)
		);
	}
}
add_action( 'wp_ajax_tg_lookup_booking', 'tg_ajax_lookup_booking' );
add_action( 'wp_ajax_nopriv_tg_lookup_booking', 'tg_ajax_lookup_booking' );

if ( ! function_exists( 'tg_ajax_toggle_wishlist' ) ) {
	/**
	 * Toggle wishlist item (logged-in only).
	 *
	 * @return void
	 */
	function tg_ajax_toggle_wishlist() {
		if ( ! tg_ajax_verify_nonce() ) {
			wp_send_json_error( array( 'message' => 'Invalid session.' ), 403 );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Log in to save tours to your wishlist.', 'guidegrid-travel' ) ), 401 );
		}

		$tour_id = isset( $_POST['tour_id'] ) ? absint( $_POST['tour_id'] ) : 0;
		$tour    = $tour_id ? get_post( $tour_id ) : null;
		if ( ! $tour || 'tour' !== $tour->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Tour not found.', 'guidegrid-travel' ) ), 404 );
		}

		$state = TG_Wishlist::toggle( get_current_user_id(), $tour_id );

		wp_send_json_success(
			array(
				'added' => $state['added'],
				'count' => $state['count'],
				'message' => $state['added'] ? __( 'Added to wishlist', 'guidegrid-travel' ) : __( 'Removed from wishlist', 'guidegrid-travel' ),
			)
		);
	}
}
add_action( 'wp_ajax_tg_toggle_wishlist', 'tg_ajax_toggle_wishlist' );

if ( ! function_exists( 'tg_ajax_submit_review' ) ) {
	/**
	 * Submit a review (logged-in only).
	 *
	 * @return void
	 */
	function tg_ajax_submit_review() {
		if ( ! tg_ajax_verify_nonce() ) {
			wp_send_json_error( array( 'message' => 'Invalid session.' ), 403 );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to submit a review.', 'guidegrid-travel' ) ), 401 );
		}

		$limit = tg_rate_limit( 'review:' . get_current_user_id(), 3, 600 );
		if ( is_wp_error( $limit ) ) {
			wp_send_json_error( array( 'message' => $limit->get_error_message() ), 429 );
		}

		$result = TG_Reviews::submit(
			array(
				'tour_id' => isset( $_POST['tour_id'] ) ? absint( $_POST['tour_id'] ) : 0,
				'user_id' => get_current_user_id(),
				'rating'  => isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 5,
				'title'   => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'content' => isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$approved = ( 'approved' === $result->status );
		wp_send_json_success(
			array(
				'message' => $approved
					? __( 'Thank you! Your review has been published.', 'guidegrid-travel' )
					: __( 'Thank you! Your review is awaiting moderation.', 'guidegrid-travel' ),
			)
		);
	}
}
add_action( 'wp_ajax_tg_submit_review', 'tg_ajax_submit_review' );

if ( ! function_exists( 'tg_ajax_submit_contact' ) ) {
	/**
	 * Contact form (honeypot + rate limit).
	 *
	 * @return void
	 */
	function tg_ajax_submit_contact() {
		if ( ! tg_ajax_verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid session. Please refresh the page.', 'guidegrid-travel' ) ), 403 );
		}
		if ( ! empty( $_POST['tg_website'] ) ) {
			wp_send_json_success( array( 'message' => __( 'Message sent.', 'guidegrid-travel' ) ) );
		}

		$limit = tg_rate_limit( 'contact:' . tg_ip(), 5, 900 );
		if ( is_wp_error( $limit ) ) {
			wp_send_json_error( array( 'message' => $limit->get_error_message() ), 429 );
		}

		$name    = isset( $_POST['name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message = isset( $_POST['message'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) ) : '';
		$length  = function_exists( 'mb_strlen' ) ? mb_strlen( $message ) : strlen( $message );
		$errors  = array();
		if ( '' === $name ) {
			$errors['name'] = __( 'Please enter your name.', 'guidegrid-travel' );
		}
		if ( ! is_email( $email ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'guidegrid-travel' );
		}
		if ( $length < 5 ) {
			$errors['message'] = __( 'Please enter a message containing at least five characters.', 'guidegrid-travel' );
		}
		if ( $errors ) {
			wp_send_json_error( array( 'message' => __( 'Please correct the highlighted fields.', 'guidegrid-travel' ), 'fields' => $errors ), 422 );
		}

		$settings   = tg_settings();
		$to         = sanitize_email( ! empty( $settings['contact_email'] ) ? $settings['contact_email'] : get_option( 'admin_email' ) );
		$from_name  = sanitize_text_field( ! empty( $settings['email_from_name'] ) ? $settings['email_from_name'] : get_bloginfo( 'name' ) );
		$from_email = sanitize_email( ! empty( $settings['email_from_email'] ) ? $settings['email_from_email'] : get_option( 'admin_email' ) );
		if ( ! is_email( $to ) || ! is_email( $from_email ) ) {
			wp_send_json_error( array( 'message' => __( 'The contact email settings are incomplete.', 'guidegrid-travel' ) ), 500 );
		}

		$body_lines = array(
			__( 'New contact form submission', 'guidegrid-travel' ),
			'',
			sprintf( /* translators: %s: sender name */ __( 'Name: %s', 'guidegrid-travel' ), $name ),
			sprintf( /* translators: %s: sender email */ __( 'Email: %s', 'guidegrid-travel' ), $email ),
		);
		if ( $phone ) {
			$body_lines[] = sprintf( /* translators: %s: sender phone */ __( 'Phone: %s', 'guidegrid-travel' ), $phone );
		}
		if ( $subject ) {
			$body_lines[] = sprintf( /* translators: %s: submitted subject */ __( 'Subject: %s', 'guidegrid-travel' ), $subject );
		}
		$body_lines[] = '';
		$body_lines[] = __( 'Message:', 'guidegrid-travel' );
		$body_lines[] = $message;
		$body         = implode( "\n", $body_lines );
		$mail_subject = '[TG] ' . ( $subject ? $subject : __( 'Contact form', 'guidegrid-travel' ) );
		$headers      = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
			'Reply-To: ' . $name . ' <' . $email . '>',
		);
		if ( ! wp_mail( $to, $mail_subject, $body, $headers ) ) {
			wp_send_json_error( array( 'message' => __( 'Your message could not be emailed. Please try again later.', 'guidegrid-travel' ) ), 500 );
		}

		do_action( 'tg_contact_submitted', $name, $email, $message );
		wp_send_json_success( array( 'message' => __( 'Thank you! We have received your message and will reply soon.', 'guidegrid-travel' ) ) );
	}
}
add_action( 'wp_ajax_tg_submit_contact', 'tg_ajax_submit_contact' );
add_action( 'wp_ajax_nopriv_tg_submit_contact', 'tg_ajax_submit_contact' );

if ( ! function_exists( 'tg_ajax_submit_enquiry' ) ) {
	/**
	 * Tour enquiry form (stored + admin email).
	 *
	 * @return void
	 */
	function tg_ajax_submit_enquiry() {
		if ( ! tg_ajax_verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid session. Please refresh the page.', 'guidegrid-travel' ) ), 403 );
		}
		if ( ! empty( $_POST['tg_website'] ) ) {
			wp_send_json_success( array( 'message' => __( 'Enquiry sent.', 'guidegrid-travel' ) ) );
		}

		$limit = tg_rate_limit( 'enquiry:' . tg_ip(), 5, 900 );
		if ( is_wp_error( $limit ) ) {
			wp_send_json_error( array( 'message' => $limit->get_error_message() ), 429 );
		}

		$name            = isset( $_POST['name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
		$email           = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone           = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$destination     = isset( $_POST['destination'] ) ? sanitize_text_field( wp_unslash( $_POST['destination'] ) ) : '';
		$tour_id         = isset( $_POST['tour_id'] ) ? absint( $_POST['tour_id'] ) : 0;
		$raw_travel_date = isset( $_POST['travel_date'] ) ? sanitize_text_field( wp_unslash( $_POST['travel_date'] ) ) : '';
		$travel_date     = $raw_travel_date ? TG_Availability::normalize_date( $raw_travel_date ) : '';
		$travelers       = isset( $_POST['travelers'] ) ? max( 1, absint( $_POST['travelers'] ) ) : 1;
		$budget          = isset( $_POST['budget'] ) ? sanitize_text_field( wp_unslash( $_POST['budget'] ) ) : '';
		$message         = isset( $_POST['message'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) ) : '';
		$length          = function_exists( 'mb_strlen' ) ? mb_strlen( $message ) : strlen( $message );
		$errors          = array();
		if ( '' === $name ) {
			$errors['name'] = __( 'Please enter your name.', 'guidegrid-travel' );
		}
		if ( ! is_email( $email ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'guidegrid-travel' );
		}
		if ( $length < 5 ) {
			$errors['message'] = __( 'Please enter a message containing at least five characters.', 'guidegrid-travel' );
		}
		if ( $raw_travel_date && ! $travel_date ) {
			$errors['travel_date'] = __( 'Please enter a valid travel date.', 'guidegrid-travel' );
		}
		if ( $errors ) {
			wp_send_json_error( array( 'message' => __( 'Please correct the highlighted enquiry fields.', 'guidegrid-travel' ), 'fields' => $errors ), 422 );
		}

		if ( $tour_id ) {
			$tour = get_post( $tour_id );
			if ( ! $tour || 'tour' !== $tour->post_type || 'publish' !== $tour->post_status ) {
				$tour_id = 0;
			}
		}

		$global = $GLOBALS['wpdb'];
		$table  = TG_Database::table( 'enquiries' );
		$now    = current_time( 'mysql', true );
		$saved  = $global->insert(
			$table,
			array(
				'name'        => $name,
				'email'       => $email,
				'phone'       => $phone,
				'destination' => $destination,
				'tour_id'     => $tour_id,
				'travel_date' => $travel_date ? $travel_date : null,
				'travelers'   => $travelers,
				'budget'      => $budget,
				'message'     => $message,
				'status'      => 'new',
				'created_at'  => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		if ( false === $saved || ! $global->insert_id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GuideGrid enquiry insert failed: ' . sanitize_text_field( (string) $global->last_error ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			wp_send_json_error( array( 'message' => __( 'Your enquiry could not be saved. Please try again.', 'guidegrid-travel' ) ), 500 );
		}

		$enquiry_id = (int) $global->insert_id;
		$enquiry    = $global->get_row( $global->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d LIMIT 1', $enquiry_id ) );
		if ( ! $enquiry ) {
			wp_send_json_error( array( 'message' => __( 'Your enquiry was saved but could not be loaded. Please contact us if you need assistance.', 'guidegrid-travel' ) ), 500 );
		}

		$mail_sent = TG_Emails::new_enquiry_admin( $enquiry );
		if ( ! $mail_sent && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GuideGrid enquiry #%d was saved, but its admin email failed.', $enquiry_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		do_action( 'tg_enquiry_submitted', $enquiry_id, $enquiry );
		wp_send_json_success( array( 'message' => __( 'Thank you! Our team will contact you within 24 hours.', 'guidegrid-travel' ), 'enquiry_id' => $enquiry_id ) );
	}
}
add_action( 'wp_ajax_tg_submit_enquiry', 'tg_ajax_submit_enquiry' );
add_action( 'wp_ajax_nopriv_tg_submit_enquiry', 'tg_ajax_submit_enquiry' );

if ( ! function_exists( 'tg_ajax_subscribe_newsletter' ) ) {
	/**
	 * Newsletter signup.
	 *
	 * @return void
	 */
	function tg_ajax_subscribe_newsletter() {
		if ( ! tg_ajax_verify_nonce() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid session. Please refresh the page.', 'guidegrid-travel' ) ), 403 );
		}
		if ( ! empty( $_POST['tg_newsletter_website'] ) ) {
			wp_send_json_success( array( 'message' => __( 'You are subscribed. Safe travels!', 'guidegrid-travel' ) ) );
		}

		$limit = tg_rate_limit( 'newsletter:' . tg_ip(), 3, 3600 );
		if ( is_wp_error( $limit ) ) {
			wp_send_json_error( array( 'message' => $limit->get_error_message() ), 429 );
		}

		$email = isset( $_POST['email'] ) ? strtolower( sanitize_email( wp_unslash( $_POST['email'] ) ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'guidegrid-travel' ), 'fields' => array( 'email' => __( 'Please enter a valid email address.', 'guidegrid-travel' ) ) ), 422 );
		}

		$stored = get_option( 'tg_newsletter_subscribers', array() );
		$list   = array();
		foreach ( is_array( $stored ) ? $stored : array() as $stored_email ) {
			$stored_email = strtolower( sanitize_email( $stored_email ) );
			if ( is_email( $stored_email ) ) {
				$list[] = $stored_email;
			}
		}
		$list = array_values( array_unique( $list ) );
		if ( in_array( $email, $list, true ) ) {
			wp_send_json_success( array( 'message' => __( 'You are already subscribed. Safe travels!', 'guidegrid-travel' ) ) );
		}

		$list[]  = $email;
		$updated = update_option( 'tg_newsletter_subscribers', array_values( array_unique( $list ) ), false );
		if ( ! $updated && ! in_array( $email, (array) get_option( 'tg_newsletter_subscribers', array() ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Your subscription could not be saved. Please try again.', 'guidegrid-travel' ) ), 500 );
		}

		do_action( 'tg_newsletter_subscribed', $email );
		wp_send_json_success( array( 'message' => __( 'You are subscribed. Safe travels!', 'guidegrid-travel' ) ) );
	}
}
add_action( 'wp_ajax_tg_subscribe_newsletter', 'tg_ajax_subscribe_newsletter' );
add_action( 'wp_ajax_nopriv_tg_subscribe_newsletter', 'tg_ajax_subscribe_newsletter' );

if ( ! function_exists( 'tg_ajax_save_profile' ) ) {
	/**
	 * Save account profile (phone/country/address + customer row).
	 *
	 * @return void
	 */
	function tg_ajax_save_profile() {
		if ( ! tg_ajax_verify_nonce() ) {
			wp_send_json_error( array( 'message' => 'Invalid session.' ), 403 );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'guidegrid-travel' ) ), 401 );
		}

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();

		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$address = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';

		update_user_meta( $user_id, '_tg_phone', $phone );
		update_user_meta( $user_id, '_tg_country', $country );
		update_user_meta( $user_id, '_tg_address', $address );

		// Mirror to the customers table (guest-checkout records too).
		$global   = $GLOBALS['wpdb'];
		$customer_table = TG_Database::table( 'customers' );
		$existing = $global->get_row( $global->prepare( 'SELECT id FROM ' . $customer_table . ' WHERE user_id = %d OR email = %s LIMIT 1', $user_id, $user->user_email ) );
		if ( $existing ) {
			$global->update(
				$customer_table,
				array(
					'phone'      => $phone,
					'country'    => $country,
					'address'    => $address,
					'updated_at' => current_time( 'mysql', true ),
				),
				array( 'id' => (int) $existing->id )
			);
		} else {
			$global->insert(
				$customer_table,
				array(
					'user_id'      => $user_id,
					'email'        => strtolower( $user->user_email ),
					'name'         => $user->display_name,
					'phone'        => $phone,
					'country'      => $country,
					'address'      => $address,
					'account_status' => 'active',
					'created_at'   => current_time( 'mysql', true ),
					'updated_at'   => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		wp_send_json_success( array( 'message' => __( 'Profile updated.', 'guidegrid-travel' ) ) );
	}
}
add_action( 'wp_ajax_tg_save_profile', 'tg_ajax_save_profile' );
