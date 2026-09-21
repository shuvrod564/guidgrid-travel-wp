<?php
/**
 * Dashboard widget: today's bookings, revenue, pending payments.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_register_dashboard_widget' ) ) {
	/**
	 * Register the dashboard widget.
	 *
	 * @return void
	 */
	function tg_register_dashboard_widget() {
		if ( ! current_user_can( 'manage_tg_bookings' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'tg_dashboard',
			__( 'Tours: Booking Overview', 'guidegrid-travel' ),
			'tg_render_dashboard_widget'
		);
	}
}
add_action( 'wp_dashboard_setup', 'tg_register_dashboard_widget' );

if ( ! function_exists( 'tg_render_dashboard_widget' ) ) {
	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	function tg_render_dashboard_widget() {
		$stats = TG_Bookings::stats();
		$today = gmdate( 'Y-m-d', time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );

		$global = $GLOBALS['wpdb'];
		$today_rows = $global->get_results(
			$global->prepare( 'SELECT * FROM ' . TG_Database::table( 'bookings' ) . " WHERE booking_date = %s ORDER BY created_at DESC LIMIT 5", $today )
		);

		echo '<div class="tg-dash-stats">';
		foreach (
			array(
				__( 'Total', 'guidegrid-travel' )         => (string) $stats['total'],
				__( 'Pending', 'guidegrid-travel' )       => (string) ( $stats['pending'] + $stats['awaiting_payment'] ),
				__( 'Confirmed', 'guidegrid-travel' )     => (string) $stats['confirmed'],
				__( 'Revenue', 'guidegrid-travel' )       => tg_format_price( (float) $stats['revenue'] ),
				__( 'Pending payment value', 'guidegrid-travel' ) => tg_format_price( (float) $stats['pending_rev'] ),
			) as $label => $value
		) :
			?>
			<div class="tg-dash-stat"><strong><?php echo esc_html( $value ); ?></strong><span><?php echo esc_html( $label ); ?></span></div>
			<?php
		endforeach;
		echo '</div>';

		echo '<h4>' . esc_html__( "Today's bookings", 'guidegrid-travel' ) . '</h4>';
		if ( empty( $today_rows ) ) {
			echo '<p class="tg-muted">' . esc_html__( 'No bookings for today yet.', 'guidegrid-travel' ) . '</p>';
		} else {
			echo '<ul class="tg-dash-list">';
			foreach ( $today_rows as $row ) {
				$tour = get_post( (int) $row->tour_id );
				printf(
					'<li><a href="%1$s">%2$s</a> — %3$s<br /><span class="tg-muted">%4$s · %5$s</span></li>',
					esc_url( admin_url( 'admin.php?page=tg-booking-detail&booking=' . (int) $row->id ) ),
					esc_html( $row->booking_number ),
					esc_html( $tour ? get_the_title( $tour ) : '#' . (int) $row->tour_id ),
					esc_html( tg_format_price( (float) $row->total, $row->currency ) ),
					esc_html( TG_Bookings::status_labels()[ $row->booking_status ] ?? $row->booking_status )
				);
			}
			echo '</ul>';
		}

		echo '<p style="margin-top:12px;"><a class="button" href="' . esc_url( admin_url( 'admin.php?page=tg-bookings' ) ) . '">' . esc_html__( 'Manage bookings', 'guidegrid-travel' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=tg-reports' ) ) . '">' . esc_html__( 'Reports', 'guidegrid-travel' ) . '</a></p>';
	}
}
