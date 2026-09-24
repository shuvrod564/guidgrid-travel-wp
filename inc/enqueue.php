<?php
/**
 * Asset loading (frontend + admin).
 *
 * Booking scripts load only where they are used.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_frontend_assets' ) ) {
    /**
     * Enqueue frontend assets.
     *
     * @return void
     */
    function tg_frontend_assets() {
        $settings = tg_settings();

        wp_enqueue_style( 'tg-style', get_stylesheet_uri(), array(), TG_VERSION );
        wp_enqueue_style( 'tg-theme', TG_URI . '/assets/css/theme.css', array( 'tg-style' ), TG_VERSION );

        // Color overrides from customizer settings (kept minimal).
        $colors_css = '<style id="tg-colors-inline">:root{'
            . ( $settings['primary_color'] ? '--tg-primary:' . esc_attr( $settings['primary_color'] ) . ';' : '' )
            . ( $settings['secondary_color'] ? '--tg-secondary:' . esc_attr( $settings['secondary_color'] ) . ';' : '' )
            . ( $settings['accent_color'] ? '--tg-accent:' . esc_attr( $settings['accent_color'] ) . ';' : '' )
            . ( $settings['primary_color'] ? '--tg-primary-dark:' . esc_attr( tg_darken_hex( $settings['primary_color'] ) ) . ';' : '' )
            . ( $settings['primary_color'] ? '--tg-primary-soft:' . esc_attr( tg_alpha_hex( $settings['primary_color'], 0.08 ) ) . ';' : '' )
            . '}';
        wp_add_inline_style( 'tg-theme', $colors_css );

        wp_enqueue_script( 'tg-theme', TG_URI . '/assets/js/theme.js', array(), TG_VERSION, true );

        $wishlist = tg_wishlist_state();

        wp_localize_script(
            'tg-theme',
            'tgData',
            array(
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'restUrl'    => esc_url_raw( rest_url( 'tg/v1/' ) ),
                'restNonce'  => wp_create_nonce( 'wp_rest' ),
                'nonce'      => wp_create_nonce( 'tg_frontend' ),
                'loggedIn'   => is_user_logged_in(),
                'accountUrl' => tg_account_url(),
                'wishlist'   => $wishlist['ids'],
                'i18n'       => array(
                    'added'       => __( 'Added to wishlist', 'guidegrid-travel' ),
                    'removed'     => __( 'Removed from wishlist', 'guidegrid-travel' ),
                    'loginReq'    => __( 'Log in to save tours to your wishlist.', 'guidegrid-travel' ),
                    'saved'       => __( 'Saved successfully.', 'guidegrid-travel' ),
                    'error'       => __( 'Something went wrong. Please try again.', 'guidegrid-travel' ),
                    'copied'      => __( 'Copied', 'guidegrid-travel' ),
                    'required'    => __( 'Please fill in this field.', 'guidegrid-travel' ),
                    'checkFields' => __( 'Please complete the highlighted fields.', 'guidegrid-travel' ),
                    'searching'   => __( 'Searching…', 'guidegrid-travel' ),
                    'notFound'    => __( 'Booking not found. Check the number and email.', 'guidegrid-travel' ),
                    'viewTour'    => __( 'View tour', 'guidegrid-travel' ),
                ),
            )
        );

        if ( tg_is_booking_context() ) {
            wp_enqueue_script( 'tg-booking', TG_URI . '/assets/js/booking.js', array( 'tg-theme' ), TG_VERSION, true );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'tg_frontend_assets' );

if ( ! function_exists( 'tg_is_booking_context' ) ) {
    /**
     * Is the current view one that needs booking scripts?
     *
     * @return bool
     */
    function tg_is_booking_context(): bool {
        if ( is_singular( 'tour' ) ) {
            return true;
        }
        if ( is_page_template(
            array(
                'page-templates/template-booking.php',
                'page-templates/template-confirmation.php',
                'page-templates/template-booking-lookup.php',
                'page-templates/template-my-account.php',
                'page-templates/template-contact.php',
            )
        ) ) {
            return true;
        }
        if ( function_exists( 'is_shortcode_context' ) ) {
            return false;
        }
        return false;
    }
}

if ( ! function_exists( 'tg_darken_hex' ) ) {
    /**
     * Darken a hex color by ~18%.
     *
     * @param string $hex Hex color.
     * @return string
     */
    function tg_darken_hex( string $hex ): string {
        $hex = ltrim( $hex, '#' );
        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( 6 !== strlen( $hex ) || 1 !== preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
            return '#07514E';
        }
        $r = max( 0, (int) hexdec( substr( $hex, 0, 2 ) ) - 34 );
        $g = max( 0, (int) hexdec( substr( $hex, 2, 2 ) ) - 34 );
        $b = max( 0, (int) hexdec( substr( $hex, 4, 2 ) ) - 34 );
        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }
}

if ( ! function_exists( 'tg_alpha_hex' ) ) {
    /**
     * Convert hex color to rgba() string.
     *
     * @param string $hex   Hex color.
     * @param float  $alpha Alpha 0-1.
     * @return string
     */
    function tg_alpha_hex( string $hex, float $alpha ): string {
        $hex = ltrim( $hex, '#' );
        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( 6 !== strlen( $hex ) || 1 !== preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
            return 'rgba(11,110,105,0.08)';
        }
        return sprintf(
            'rgba(%d,%d,%d,%s)',
            hexdec( substr( $hex, 0, 2 ) ),
            hexdec( substr( $hex, 2, 2 ) ),
            hexdec( substr( $hex, 4, 2 ) ),
            $alpha
        );
    }
}

if ( ! function_exists( 'tg_tour_script_data' ) ) {
    /**
     * Localize data for the booking widget on a tour page.
     *
     * @param int|string|null $tour_id Tour post ID or empty string from hook.
     * @return void
     */
    function tg_tour_script_data( int|string|null $tour_id = 0 ): void {
        // This payload belongs only to a single tour page. On a checkout page,
        // get_the_ID() is the booking PAGE id, not the selected tour id.
        if ( ! is_singular( 'tour' ) || ! wp_script_is( 'tg-booking', 'enqueued' ) ) {
            return;
        }

        $tour_id = (int) get_queried_object_id();
        if ( ! $tour_id ) {
            return;
        }

        // single-tour.php used to call this function a second time, which
        // printed duplicate `var tgTour` blocks. Keep the guard for child
        // themes or integrations that may still call it directly.
        static $localized_tours = array();
        if ( isset( $localized_tours[ $tour_id ] ) ) {
            return;
        }
        $localized_tours[ $tour_id ] = true;

        $settings   = tg_settings();
        $price_info = tg_tour_price_info( $tour_id );
        $addons     = TG_Addons::get_for_tour( $tour_id );

        $dates = array();
        try {
            $dates = TG_Availability::get_dates( $tour_id );
        } catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
            $dates = array();
        }

        $addons_out = array();
        foreach ( $addons as $addon ) {
            $addons_out[] = array(
                'id'    => $addon['id'],
                'name'  => $addon['name'],
                'desc'  => wp_trim_words( $addon['description'], 12 ),
                'price' => tg_format_price( $addon['price'], $settings['currency'] ),
                'unit'  => TG_Addons::unit_label( $addon['unit'] ),
            );
        }

        wp_localize_script(
            'tg-booking',
            'tgTour',
            array(
                'tourId'         => $tour_id,
                'bookingUrl'     => tg_booking_page_url( $tour_id ),
                'currency'       => $price_info['currency'],
                'adultPrice'     => (float) tg_get_meta( $tour_id, '_tg_adult_price', $price_info['base'] ),
                'childPrice'     => (float) tg_get_meta( $tour_id, '_tg_child_price', 0 ),
                'infantPrice'    => (float) tg_get_meta( $tour_id, '_tg_infant_price', 0 ),
                'formatted'      => array(
                    'adult'  => tg_format_price( (float) tg_get_meta( $tour_id, '_tg_adult_price', $price_info['base'] ), $price_info['currency'] ),
                    'child'  => tg_format_price( (float) tg_get_meta( $tour_id, '_tg_child_price', 0 ), $price_info['currency'] ),
                    'infant' => tg_format_price( (float) tg_get_meta( $tour_id, '_tg_infant_price', 0 ), $price_info['currency'] ),
                ),
                'addons'         => $addons_out,
                'availableDates' => $dates,
                'i18n'           => array(
                    'noDates'    => __( 'No dates available right now. Contact us for private arrangements.', 'guidegrid-travel' ),
                    'selectDate' => __( 'Select date', 'guidegrid-travel' ),
                    'applying'   => __( 'Checking availability…', 'guidegrid-travel' ),
                    'couponOk'   => __( 'Coupon applied.', 'guidegrid-travel' ),
                    'couponErr'  => __( 'Coupon not applied.', 'guidegrid-travel' ),
                    'submitting' => __( 'Creating your booking…', 'guidegrid-travel' ),
                    'total'      => __( 'Total', 'guidegrid-travel' ),
                    'subtotal'   => __( 'Subtotal', 'guidegrid-travel' ),
                    'discount'   => __( 'Discount', 'guidegrid-travel' ),
                    'tax'        => __( 'Tax', 'guidegrid-travel' ),
                    'fee'        => __( 'Service fee', 'guidegrid-travel' ),
                    'deposit'    => __( 'Deposit', 'guidegrid-travel' ),
                    'left'       => __( 'seats left', 'guidegrid-travel' ),
                ),
            )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'tg_tour_script_data', 20 );

if ( ! function_exists( 'tg_admin_assets' ) ) {
    /**
     * Enqueue admin assets.
     *
     * @return void
     */
    function tg_admin_assets() {
        wp_enqueue_style( 'tg-admin', TG_URI . '/assets/css/admin.css', array(), TG_VERSION );
        wp_enqueue_script( 'tg-admin', TG_URI . '/assets/js/admin.js', array(), TG_VERSION, true );
    }
}
add_action( 'admin_enqueue_scripts', 'tg_admin_assets' );
