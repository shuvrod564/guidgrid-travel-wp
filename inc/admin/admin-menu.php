<?php
/**
 * Admin menu registration.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_admin_menu' ) ) {
	/**
	 * Register the top-level Tours menu and submenus.
	 *
	 * @return void
	 */
	function tg_admin_menu() {
		$can_bookings = current_user_can( 'manage_tg_bookings' );
		$can_all      = current_user_can( 'manage_tg' );

		add_menu_page(
			__( 'Tours', 'guidegrid-travel' ),
			__( 'Tours', 'guidegrid-travel' ),
			$can_all ? 'manage_tg' : 'manage_tg_bookings',
			'tg-bookings',
			'tg_render_bookings_page',
			'dashicons-location-alt',
			25
		);

		if ( $can_bookings ) {
			add_submenu_page( 'tg-bookings', __( 'Bookings', 'guidegrid-travel' ), __( 'Bookings', 'guidegrid-travel' ), 'manage_tg_bookings', 'tg-bookings', 'tg_render_bookings_page' );
			add_submenu_page( 'tg-bookings', __( 'New Booking', 'guidegrid-travel' ), __( 'New Booking', 'guidegrid-travel' ), 'manage_tg_bookings', 'tg-new-booking', 'tg_render_manual_booking_page' );
			// Register the detail route as a real child page. It is removed from the
			// visible menu later, after WordPress completes its access check.
			add_submenu_page( 'tg-bookings', __( 'Booking Details', 'guidegrid-travel' ), __( 'Booking Details', 'guidegrid-travel' ), 'manage_tg_bookings', 'tg-booking-detail', 'tg_render_booking_detail_page' );
		}
		if ( $can_all ) {
			add_submenu_page( 'tg-bookings', __( 'Calendar', 'guidegrid-travel' ), __( 'Calendar', 'guidegrid-travel' ), 'manage_tg', 'tg-calendar', 'tg_render_calendar_page' );
			add_submenu_page( 'tg-bookings', __( 'Reviews', 'guidegrid-travel' ), __( 'Reviews', 'guidegrid-travel' ), 'manage_tg', 'tg-reviews', 'tg_render_reviews_admin_page' );
			add_submenu_page( 'tg-bookings', __( 'Enquiries', 'guidegrid-travel' ), __( 'Enquiries', 'guidegrid-travel' ), 'manage_tg', 'tg-enquiries', 'tg_render_enquiries_page' );
			add_submenu_page( 'tg-bookings', __( 'Coupons', 'guidegrid-travel' ), __( 'Coupons', 'guidegrid-travel' ), 'manage_tg', 'tg-coupons', 'tg_render_coupons_page' );
			add_submenu_page( 'tg-bookings', __( 'Reports', 'guidegrid-travel' ), __( 'Reports', 'guidegrid-travel' ), 'manage_tg', 'tg-reports', 'tg_render_reports_page' );
			add_submenu_page( 'tg-bookings', __( 'Settings', 'guidegrid-travel' ), __( 'Settings', 'guidegrid-travel' ), 'manage_tg', 'tg-settings', 'tg_render_settings_page' );
			add_submenu_page( 'tg-bookings', __( 'Demo Data', 'guidegrid-travel' ), __( 'Demo Data', 'guidegrid-travel' ), 'manage_tg', 'tg-demo', 'tg_render_demo_page' );
		}
	}
}
add_action( 'admin_menu', 'tg_admin_menu' );

/**
 * Hide the detail route without unregistering it during WordPress's permission
 * check. Removing it inside admin_menu makes get_admin_page_parent() lose the
 * route's parent and causes a false "not allowed" response for direct links.
 *
 * admin_head runs after user_can_access_admin_page() but before the menu HTML
 * is rendered, so the page remains accessible without adding a menu item.
 *
 * @return void
 */
function tg_hide_booking_detail_submenu(): void {
	remove_submenu_page( 'tg-bookings', 'tg-booking-detail' );
}
add_action( 'admin_head', 'tg_hide_booking_detail_submenu', 1 );

/**
 * Redirect from the top-level menu to the first submenu (prevents the "duplicate" page).
 *
 * @return void
 */
function tg_admin_redirect() {
	if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
}
add_action( 'admin_init', 'tg_admin_redirect' );
