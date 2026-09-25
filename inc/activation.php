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

		$previous_version = (string) get_option( 'tg_theme_version', '' );
		TG_Database::install();
		tg_add_roles();

		tg_create_workflow_pages();

		// Load latest settings (keeps existing values).
		$settings = tg_settings();
		update_option( 'tg_settings', $settings );
		tg_run_versioned_upgrades( $previous_version );
		update_option( 'tg_theme_version', TG_VERSION );

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
 * Apply data repairs introduced by a theme release.
 *
 * @param string $from_version Previously installed theme version.
 * @return void
 */
function tg_run_versioned_upgrades( string $from_version ): void {
	if ( '' === $from_version || version_compare( $from_version, '1.0.8', '<' ) ) {
		$global    = $GLOBALS['wpdb'];
		$customers = TG_Database::table( 'customers' );
		$bookings  = TG_Database::table( 'bookings' );
		$payments  = TG_Database::table( 'payments' );
		// Rebuild lifetime spend from received payments, net of recorded refunds.
		$global->query(
			"UPDATE {$customers} c
			 SET c.total_spent = GREATEST(0, COALESCE((
				SELECT SUM(CASE
					WHEN p.status = 'paid' THEN p.amount
					WHEN p.status = 'refunded' THEN p.amount
					ELSE 0
				END)
				FROM {$bookings} b
				INNER JOIN {$payments} p ON p.booking_id = b.id
				WHERE b.customer_id = c.id
			 ), 0))"
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table names and fixed SQL.
	}
}

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
			// Assign the workflow template when the page still uses the default.
			$current_template = get_post_meta( $existing->ID, '_wp_page_template', true );
			if ( ! $current_template || 'default' === $current_template ) {
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
 * Apply one-time theme upgrades and keep workflow pages/tables available.
 *
 * This runs on init rather than only after theme activation because replacing
 * an already-active theme directory does not fire after_switch_theme.
 *
 * @return void
 */
function tg_maybe_upgrade_theme() {
	$previous_version = (string) get_option( 'tg_theme_version', '' );
	TG_Database::maybe_install();

	if ( TG_VERSION === $previous_version ) {
		return;
	}

	tg_add_roles();
	tg_create_workflow_pages();
	tg_run_versioned_upgrades( $previous_version );
	update_option( 'tg_theme_version', TG_VERSION );

	// Workflow pages may have just been created or assigned a template.
	flush_rewrite_rules( false );
}
add_action( 'init', 'tg_maybe_upgrade_theme', 20 );
