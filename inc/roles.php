<?php
/**
 * Custom roles and capabilities.
 *
 * Administrator — everything.
 * Tour Manager  — tour content, pricing, availability, bookings, customers.
 *                 No access to WordPress system settings (manage_options).
 * Booking Manager — view/create bookings, statuses, customers, exports,
 *                 notifications.
 * Customer      — standard WP subscriber; books through the frontend.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_add_roles' ) ) {
	/**
	 * Register custom capabilities and roles.
	 *
	 * @return void
	 */
	function tg_add_roles() {
		add_role(
			'tour_manager',
			__( 'Tour Manager', 'guidegrid-travel' ),
			array(
				'read'                 => true,
				'edit_posts'           => true,
				'edit_others_posts'    => false,
				'publish_posts'        => true,
				'upload_files'         => true,
				'manage_tg'            => true,
				'manage_tg_bookings'   => true,
				'edit_tour'            => true,
				'edit_tours'           => true,
				'edit_others_tours'    => true,
				'publish_tours'        => true,
				'delete_tours'         => true,
				'edit_destination'     => true,
				'edit_destinations'    => true,
				'edit_others_destinations' => true,
				'publish_destinations' => true,
				'delete_destinations'  => true,
				'edit_travel_guide'    => true,
				'edit_travel_guides'   => true,
				'publish_travel_guides' => true,
			)
		);

		add_role(
			'booking_manager',
			__( 'Booking Manager', 'guidegrid-travel' ),
			array(
				'read'               => true,
				'manage_tg_bookings' => true,
			)
		);

		// add_role() does not update roles that already exist. Explicitly restore
		// the theme capabilities on every load so upgrades and migrated sites are
		// not locked out of the booking console.
		$tour_manager = get_role( 'tour_manager' );
		if ( $tour_manager ) {
			$tour_manager->add_cap( 'manage_tg' );
			$tour_manager->add_cap( 'manage_tg_bookings' );
		}
		$booking_manager = get_role( 'booking_manager' );
		if ( $booking_manager ) {
			$booking_manager->add_cap( 'manage_tg_bookings' );
		}

		// WordPress administrators must always be able to configure the theme
		// and manage bookings. Custom capabilities are not granted to the
		// administrator role automatically.
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'manage_tg' );
			$administrator->add_cap( 'manage_tg_bookings' );
		}
	}
}
add_action( 'init', 'tg_add_roles' );
