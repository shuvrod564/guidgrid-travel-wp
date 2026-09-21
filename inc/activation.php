<?php
/**
 * Theme activation / deactivation.
 *
 * Activation:
 *  - creates all custom tables (dbDelta),
 *  - creates required workflow pages (Booking, Confirmation, Lookup,
 *    My Account, Contact) with templates,
 *  - schedules cron events,
 *  - flushes rewrite rules.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_activate_theme' ) ) {
	/**
	 * Run on theme activation.
	 *
	 * @return void
	 */
	function tg_activate_theme() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		TG_Database::install();
		tg_add_roles();

		tg_create_workflow_pages();

		// Load latest settings (keeps existing values).
		$settings = tg_settings();
		update_option( 'tg_settings', $settings );

		// Cron.
		tg_cron_init();

		// Rewrite flush after CPTs are registered on this request.
		tg_register_post_types();
		tg_register_taxonomies();
		flush_rewrite_rules();
	}
}
add_action( 'after_switch_theme', 'tg_activate_theme' );

if ( ! function_exists( 'tg_deactivate_theme' ) ) {
	/**
	 * Run on theme deactivation.
	 *
	 * @return void
	 */
	function tg_deactivate_theme() {
		wp_clear_scheduled_hook( 'tg_hourly_maintenance' );
		wp_clear_scheduled_hook( 'tg_daily_reminders' );
		flush_rewrite_rules();
	}
}
add_action( 'switch_theme', 'tg_deactivate_theme' );

/**
 * Create the workflow pages the booking flow depends on.
 * Existing pages are left untouched (content preserved).
 *
 * @return array<string,int> slug => page ID.
 */
function tg_create_workflow_pages(): array {
	$pages = array(
		'booking'              => array(
			'title'    => __( 'Book Now', 'guidegrid-travel' ),
			'template' => 'page-templates/template-booking.php',
			'content'  => '',
		),
		'booking-confirmation' => array(
			'title'    => __( 'Booking Confirmation', 'guidegrid-travel' ),
			'template' => 'page-templates/template-confirmation.php',
			'content'  => '',
		),
		'booking-lookup'       => array(
			'title'    => __( 'Find My Booking', 'guidegrid-travel' ),
			'template' => 'page-templates/template-booking-lookup.php',
			'content'  => '',
		),
		'my-account'           => array(
			'title'    => __( 'My Account', 'guidegrid-travel' ),
			'template' => 'page-templates/template-my-account.php',
			'content'  => '',
		),
		'contact'              => array(
			'title'    => __( 'Contact', 'guidegrid-travel' ),
			'template' => 'page-templates/template-contact.php',
			'content'  => '',
		),
	);

	$created = array();

	foreach ( $pages as $slug => $config ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			// Assign template only if the page has none.
			$current_template = get_post_meta( $existing->ID, '_wp_page_template', true );
			if ( ! $current_template ) {
				update_post_meta( $existing->ID, '_wp_page_template', $config['template'] );
			}
			$created[ $slug ] = $existing->ID;
			continue;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $config['title'],
				'post_name'    => $slug,
				'post_content' => $config['content'],
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_post_meta( $page_id, '_wp_page_template', $config['template'] );
			$created[ $slug ] = (int) $page_id;
		}
	}

	return $created;
}

/**
 * Keep tables current when the theme loads (version checked).
 *
 * @return void
 */
function tg_maybe_install_tables() {
	TG_Database::maybe_install();
}
add_action( 'admin_init', 'tg_maybe_install_tables' );
