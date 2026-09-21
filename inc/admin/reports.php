<?php
/**
 * Reports page: booking volume, revenue, top tours, destination breakdown.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_render_reports_page' ) ) {
	/**
	 * Render the reports page.
	 *
	 * @return void
	 */
	function tg_render_reports_page() {
		if ( ! current_user_can( 'manage_tg' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'guidegrid-travel' ) );
		}

		$global  = $GLOBALS['wpdb'];
		$table   = TG_Database::table( 'bookings' );
		$range   = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'month'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$from    = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to      = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		switch ( $range ) {
			case 'today':
				$from = gmdate( 'Y-m-d', time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
				$to   = $from;
				break;
			case 'week':
				$from = gmdate( 'Y-m-d', strtotime( '-7 days', time() ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
				$to   = gmdate( 'Y-m-d' );
				break;
			case 'year':
				$from = gmdate( 'Y-01-01' );
				$to   = gmdate( 'Y-m-d' );
				break;
			case 'custom':
				$from = $from ? TG_Availability::normalize_date( $from ) : '';
				$to   = $to ? TG_Availability::normalize_date( $to ) : '';
				break;
			case 'month':
			default:
				$from = gmdate( 'Y-m-01' );
				$to   = gmdate( 'Y-m-d' );
				break;
		}

		$where  = '1=1';
		$params = array();
		if ( $from ) {
			$where    .= ' AND created_at >= %s';
			$params[]  = $from . ' 00:00:00';
		}
		if ( $to ) {
			$where    .= ' AND created_at <= %s';
			$params[]  = $to . ' 23:59:59';
		}

		$prep = static function ( $sql ) use ( $global, $params ) {
			return $params ? $global->prepare( $sql, $params ) : $sql;
		};

		$total_bookings  = (int) $global->get_var( $prep( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ) );
		$revenue         = (float) $global->get_var( $prep( "SELECT COALESCE(SUM(total),0) FROM {$table} WHERE {$where} AND booking_status IN ('paid','confirmed','completed','partially_paid')" ) );
		$cancelled       = (int) $global->get_var( $prep( "SELECT COUNT(*) FROM {$table} WHERE {$where} AND booking_status = 'cancelled'" ) );
		$avg_value       = $total_bookings ? round( $revenue / $total_bookings, 2 ) : 0;
		$guests_total    = (int) $global->get_var( $prep( "SELECT COALESCE(SUM(adult_count + child_count + infant_count),0) FROM {$table} WHERE {$where}" ) );

		$top_tours = $global->get_results(
			$prep(
				"SELECT tour_id, COUNT(*) AS bookings, COALESCE(SUM(total),0) AS revenue
				 FROM {$table}
				 WHERE {$where} AND booking_status != 'cancelled'
				 GROUP BY tour_id ORDER BY bookings DESC LIMIT 8"
			)
		);

		$top_destinations = $global->get_results(
			$prep(
				"SELECT pm.meta_value AS destination_id, COUNT(*) AS bookings
				 FROM {$table} b
				 INNER JOIN {$global->prefix}postmeta pm ON pm.post_id = b.tour_id AND pm.meta_key = '_tg_destination_id'
				 WHERE {$where} AND b.booking_status != 'cancelled'
				 GROUP BY pm.meta_value ORDER BY bookings DESC LIMIT 6"
			)
		);

		$payment_methods = $global->get_results(
			$prep(
				"SELECT payment_method, COUNT(*) AS total FROM {$global->prefix}tg_payments WHERE status = 'paid' GROUP BY payment_method ORDER BY total DESC"
			)
		);
		?>
		<div class="wrap tg-admin">
			<h1><?php esc_html_e( 'Reports', 'guidegrid-travel' ); ?></h1>

			<form method="get" class="tg-filter-form">
				<input type="hidden" name="page" value="tg-reports" />
				<select name="range">
					<option value="today" <?php selected( $range, 'today' ); ?>><?php esc_html_e( 'Today', 'guidegrid-travel' ); ?></option>
					<option value="week" <?php selected( $range, 'week' ); ?>><?php esc_html_e( 'Last 7 days', 'guidegrid-travel' ); ?></option>
					<option value="month" <?php selected( $range, 'month' ); ?>><?php esc_html_e( 'This month', 'guidegrid-travel' ); ?></option>
					<option value="year" <?php selected( $range, 'year' ); ?>><?php esc_html_e( 'This year', 'guidegrid-travel' ); ?></option>
					<option value="custom" <?php selected( $range, 'custom' ); ?>><?php esc_html_e( 'Custom range', 'guidegrid-travel' ); ?></option>
				</select>
				<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" />
				<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" />
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Run Report', 'guidegrid-travel' ); ?></button>
			</form>

			<div class="tg-stats-row">
				<div class="tg-stat"><span class="tg-stat-num"><?php echo esc_html( (string) $total_bookings ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Bookings', 'guidegrid-travel' ); ?></span></div>
				<div class="tg-stat"><span class="tg-stat-num"><?php echo esc_html( tg_format_price( $revenue ) ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Revenue', 'guidegrid-travel' ); ?></span></div>
				<div class="tg-stat"><span class="tg-stat-num"><?php echo esc_html( tg_format_price( $avg_value ) ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Avg. Booking Value', 'guidegrid-travel' ); ?></span></div>
				<div class="tg-stat"><span class="tg-stat-num"><?php echo esc_html( (string) $guests_total ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Guests', 'guidegrid-travel' ); ?></span></div>
				<div class="tg-stat"><span class="tg-stat-num"><?php echo esc_html( (string) $cancelled ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Cancellations', 'guidegrid-travel' ); ?></span></div>
			</div>

			<h2><?php esc_html_e( 'Top Tours', 'guidegrid-travel' ); ?></h2>
			<table class="widefat">
				<thead><tr><th><?php esc_html_e( 'Tour', 'guidegrid-travel' ); ?></th><th><?php esc_html_e( 'Bookings', 'guidegrid-travel' ); ?></th><th><?php esc_html_e( 'Revenue', 'guidegrid-travel' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( (array) $top_tours as $t ) : ?>
						<?php $tour = get_post( (int) $t->tour_id ); ?>
						<tr>
							<td><?php echo $tour ? esc_html( get_the_title( $tour ) ) : esc_html( '#' . (int) $t->tour_id ); ?></td>
							<td><?php echo esc_html( (string) $t->bookings ); ?></td>
							<td><?php echo esc_html( tg_format_price( (float) $t->revenue ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Top Destinations', 'guidegrid-travel' ); ?></h2>
			<table class="widefat">
				<thead><tr><th><?php esc_html_e( 'Destination', 'guidegrid-travel' ); ?></th><th><?php esc_html_e( 'Bookings', 'guidegrid-travel' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( (array) $top_destinations as $d ) : ?>
						<?php $dest = get_post( (int) $d->destination_id ); ?>
						<tr>
							<td><?php echo $dest ? esc_html( get_the_title( $dest ) ) : esc_html( '#' . (int) $d->destination_id ); ?></td>
							<td><?php echo esc_html( (string) $d->bookings ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Payment Methods (paid)', 'guidegrid-travel' ); ?></h2>
			<table class="widefat">
				<thead><tr><th><?php esc_html_e( 'Method', 'guidegrid-travel' ); ?></th><th><?php esc_html_e( 'Count', 'guidegrid-travel' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( (array) $payment_methods as $pm ) : ?>
						<tr>
							<td><?php echo esc_html( $pm->payment_method ? $pm->payment_method : '—' ); ?></td>
							<td><?php echo esc_html( (string) $pm->total ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
