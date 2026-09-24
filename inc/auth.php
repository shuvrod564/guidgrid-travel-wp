<?php
/**
 * Branded customer authentication and checkout access control.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve and validate a post-authentication redirect.
 *
 * Only same-site destinations are accepted. This prevents the auth forms
 * from being used as an open redirect.
 *
 * @param string $requested Requested URL.
 * @return string
 */
function tg_auth_redirect_target( string $requested = '' ): string {
	$fallback = tg_account_url();
	if ( '' === $requested ) {
		return $fallback;
	}
	return wp_validate_redirect( esc_url_raw( $requested ), $fallback );
}

/**
 * Clear the temporary social-login destination cookie.
 *
 * @return void
 */
function tg_clear_auth_redirect_cookie(): void {
	setcookie(
		'tg_auth_redirect',
		'',
		array(
			'expires'  => time() - HOUR_IN_SECONDS,
			'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => (string) ( defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '' ),
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}

/**
 * Build a frontend login/create-account URL.
 *
 * @param string $redirect URL to visit after authentication.
 * @param string $view     login|register.
 * @return string
 */
function tg_auth_url( string $redirect = '', string $view = 'login' ): string {
	$args = array();
	if ( 'register' === $view ) {
		$args['auth'] = 'register';
	}
	if ( $redirect ) {
		$args['redirect_to'] = tg_auth_redirect_target( $redirect );
	}
	return $args ? add_query_arg( $args, tg_account_url() ) : tg_account_url();
}

/**
 * Current frontend auth errors, populated during template_redirect.
 *
 * @return WP_Error
 */
function tg_auth_errors(): WP_Error {
	if ( ! isset( $GLOBALS['tg_auth_errors'] ) || ! is_wp_error( $GLOBALS['tg_auth_errors'] ) ) {
		$GLOBALS['tg_auth_errors'] = new WP_Error();
	}
	return $GLOBALS['tg_auth_errors'];
}

/**
 * Generate an available, non-sensitive username from an email address.
 *
 * @param string $email Email address.
 * @return string
 */
function tg_auth_username_from_email( string $email ): string {
	$local = strstr( $email, '@', true );
	$base  = sanitize_user( $local ? $local : 'traveler', true );
	if ( strlen( $base ) < 3 ) {
		$base = 'traveler';
	}
	$base      = substr( $base, 0, 48 );
	$candidate = $base;
	$counter   = 1;
	while ( username_exists( $candidate ) ) {
		$candidate = substr( $base, 0, 43 ) . '-' . $counter;
		$counter++;
	}
	return $candidate;
}

/**
 * Process the branded login and registration forms before headers render.
 *
 * @return void
 */
function tg_process_frontend_auth(): void {
	if ( ! is_page_template( 'page-templates/template-my-account.php' ) || is_user_logged_in() ) {
		return;
	}

	// Loginizer and other social-login providers normally use WordPress's
	// login_redirect filter after their OAuth callback. Preserve the requested
	// same-site checkout URL in a short-lived, HTTP-only cookie for that flow.
	if ( isset( $_REQUEST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$remember_redirect = tg_auth_redirect_target( (string) wp_unslash( $_REQUEST['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		setcookie(
			'tg_auth_redirect',
			rawurlencode( $remember_redirect ),
			array(
				'expires'  => time() + 15 * MINUTE_IN_SECONDS,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => (string) ( defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '' ),
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
		return;
	}

	$action = isset( $_POST['tg_auth_action'] ) ? sanitize_key( wp_unslash( $_POST['tg_auth_action'] ) ) : '';
	if ( ! in_array( $action, array( 'login', 'register' ), true ) ) {
		return;
	}

	$errors = tg_auth_errors();
	$nonce  = isset( $_POST['tg_auth_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['tg_auth_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'tg_frontend_auth' ) ) {
		$errors->add( 'invalid_session', __( 'Your session expired. Refresh the page and try again.', 'guidegrid-travel' ) );
		return;
	}

	$redirect = isset( $_POST['redirect_to'] ) ? tg_auth_redirect_target( (string) wp_unslash( $_POST['redirect_to'] ) ) : tg_account_url();
	$login    = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
	$email    = isset( $_POST['email'] ) ? strtolower( sanitize_email( wp_unslash( $_POST['email'] ) ) ) : '';
	$key      = 'auth:' . $action . ':' . tg_ip();
	$limit    = tg_rate_limit( $key, 'login' === $action ? 10 : 5, 15 * MINUTE_IN_SECONDS );
	if ( is_wp_error( $limit ) ) {
		$errors->add( $limit->get_error_code(), $limit->get_error_message() );
		return;
	}

	if ( 'login' === $action ) {
		$password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
		if ( '' === $login || '' === $password ) {
			$errors->add( 'missing_credentials', __( 'Enter your email or username and password.', 'guidegrid-travel' ) );
			return;
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => ! empty( $_POST['rememberme'] ),
			),
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			// Avoid exposing whether a particular account exists.
			$errors->add( 'login_failed', __( 'The login details were not accepted. Check them and try again.', 'guidegrid-travel' ) );
			return;
		}

		tg_clear_auth_redirect_cookie();
		wp_safe_redirect( $redirect );
		exit;
	}

	$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
	$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
	$password   = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
	$confirm    = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : '';

	if ( '' === $first_name ) {
		$errors->add( 'first_name', __( 'Enter your first name.', 'guidegrid-travel' ) );
	}
	if ( ! is_email( $email ) ) {
		$errors->add( 'email', __( 'Enter a valid email address.', 'guidegrid-travel' ) );
	} elseif ( email_exists( $email ) ) {
		$errors->add( 'email_exists', __( 'An account already uses that email. Log in or reset your password instead.', 'guidegrid-travel' ) );
	}
	if ( strlen( $password ) < 8 ) {
		$errors->add( 'password_short', __( 'Use a password with at least 8 characters.', 'guidegrid-travel' ) );
	}
	if ( $password !== $confirm ) {
		$errors->add( 'password_mismatch', __( 'The passwords do not match.', 'guidegrid-travel' ) );
	}
	if ( $errors->has_errors() ) {
		return;
	}

	$username = tg_auth_username_from_email( $email );
	$user_id  = wp_insert_user(
		array(
			'user_login'   => $username,
			'user_pass'    => $password,
			'user_email'   => $email,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
			'role'         => 'subscriber',
		)
	);
	if ( is_wp_error( $user_id ) ) {
		$errors->add( 'registration_failed', __( 'Your account could not be created. Please try again.', 'guidegrid-travel' ) );
		return;
	}

	$user = get_user_by( 'id', $user_id );
	wp_set_current_user( $user_id, $user ? $user->user_login : $username );
	wp_set_auth_cookie( $user_id, true, is_ssl() );
	if ( $user ) {
		do_action( 'wp_login', $user->user_login, $user );
	}
	do_action( 'tg_customer_registered', $user_id );

	tg_clear_auth_redirect_cookie();
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'template_redirect', 'tg_process_frontend_auth', 5 );

/**
 * Restore the frontend destination after a social-login callback.
 *
 * @param string $redirect_to           Redirect selected by WordPress/plugin.
 * @param string $requested_redirect_to Originally requested redirect.
 * @param mixed  $user                  Logged-in user or WP_Error.
 * @return string
 */
function tg_social_login_redirect( string $redirect_to, string $requested_redirect_to, $user ): string {
	if ( is_wp_error( $user ) || empty( $_COOKIE['tg_auth_redirect'] ) ) {
		return $redirect_to;
	}
	$remembered = rawurldecode( sanitize_text_field( wp_unslash( $_COOKIE['tg_auth_redirect'] ) ) );
	$remembered = tg_auth_redirect_target( $remembered );
	tg_clear_auth_redirect_cookie();
	return $remembered;
}
add_filter( 'login_redirect', 'tg_social_login_redirect', 20, 3 );

/**
 * Require authentication before the checkout template can render.
 *
 * The booking REST/AJAX handlers enforce the same rule server-side.
 *
 * @return void
 */
function tg_require_account_for_checkout(): void {
	if ( is_user_logged_in() || ! is_page_template( 'page-templates/template-booking.php' ) ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$checkout    = $request_uri ? home_url( $request_uri ) : tg_booking_page_url( 0 );
	$checkout    = wp_validate_redirect( $checkout, home_url( '/' ) );
	wp_safe_redirect( tg_auth_url( $checkout, 'login' ) );
	exit;
}
add_action( 'template_redirect', 'tg_require_account_for_checkout', 10 );
