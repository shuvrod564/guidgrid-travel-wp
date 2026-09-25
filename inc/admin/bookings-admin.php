<?php
/**
 * Bookings admin: list, detail, actions, export, manual booking.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query bookings with admin filters.
 *
 * @param array $args Filters.
 * @return array{items:object[],total:int}
 */
function tg_query_admin_bookings( array $args ): array {
	$global   = $GLOBALS['wpdb'];
	$table    = TG_Database::table( 'bookings' );
	$per_page = max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) );
	$page     = max( 1, absint( $args['page'] ?? 1 ) );
	$offset   = ( $page - 1 ) * $per_page;

	$where  = array( '1=1' );
	$params = array();

	if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
		$where[]  = 'b.booking_status = %s';
		$params[] = sanitize_key( $args['status'] );
	}
	if ( ! empty( $args['payment_status'] ) && 'all' !== $args['payment_status'] ) {
		$where[]  = 'b.payment_status = %s';
		$params[] = sanitize_key( $args['payment_status'] );
	}
	if ( ! empty( $args['tour_id'] ) ) {
		$where[]  = 'b.tour_id = %d';
		$params[] = absint( $args['tour_id'] );
	}
	if ( ! empty( $args['search'] ) ) {
		$like     = '%' . $global->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		$where[]  = '(b.booking_number LIKE %s OR c.email LIKE %s OR c.name LIKE %s)';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}
	if ( ! empty( $args['date_from'] ) && TG_Availability::normalize_date( (string) $args['date_from'] ) ) {
		$where[]  = 'b.booking_date >= %s';
		$params[] = TG_Availability::normalize_date( (string) $args['date_from'] );
	}
	if ( ! empty( $args['date_to'] ) && TG_Availability::normalize_date( (string) $args['date_to'] ) ) {
		$where[]  = 'b.booking_date <= %s';
		$params[] = TG_Availability::normalize_date( (string) $args['date_to'] );
	}
	if ( ! empty( $args['destination_id'] ) ) {
		$where[]  = 'b.tour_id IN (SELECT pm.post_id FROM ' . $global->prefix . 'postmeta pm WHERE pm.meta_key = %s AND pm.meta_value = %d)';
		$params[] = '_tg_destination_id';
		$params[] = absint( $args['destination_id'] );
	}

	$where_sql = implode( ' AND ', $where );

	$total = (int) $global->get_var(
		$params
			? $global->prepare( "SELECT COUNT(*) FROM {$table} b LEFT JOIN " . TG_Database::table( 'customers' ) . ' c ON c.id = b.customer_id WHERE ' . $where_sql, $params ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			: "SELECT COUNT(*) FROM {$table} b LEFT JOIN " . TG_Database::table( 'customers' ) . ' c ON c.id = b.customer_id WHERE ' . $where_sql
	);

	$sql    = "SELECT b.* FROM {$table} b LEFT JOIN " . TG_Database::table( 'customers' ) . ' c ON c.id = b.customer_id WHERE ' . $where_sql . ' ORDER BY b.created_at DESC LIMIT %d OFFSET %d';
	$params = array_merge( $params, array( $per_page, $offset ) );

	$items = $global->get_results( $global->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return array(
		'items' => (array) $items,
		'total' => $total,
	);
}

if ( ! function_exists( 'tg_render_bookings_page' ) ) {
	/**
	 * Bookings list page.
	 *
	 * @return void
	 */
	function tg_render_bookings_page() {
		if ( ! current_user_can( 'manage_tg_bookings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'guidegrid-travel' ) );
		}

		$base_url = admin_url( 'admin.php?page=tg-bookings' );

		$stats = TG_Bookings::stats();

		// ---- Filters. ----
		$f = array(
			'status'         => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all',
			'payment_status' => isset( $_GET['payment_status'] ) ? sanitize_key( $_GET['payment_status'] ) : 'all',
			'tour_id'        => isset( $_GET['tour_id'] ) ? absint( $_GET['tour_id'] ) : 0,
			'search'         => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'date_from'      => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'        => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'page'           => isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1,
		);

		$result   = tg_query_admin_bookings( $f );
		$per_page = 20;
		$pages    = max( 1, (int) ceil( $result['total'] / $per_page ) );
		?>
		<div class="wrap tg-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Bookings', 'guidegrid-travel' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tg-new-booking' ) ); ?>" class="page-title-action"><?php esc_html_e( 'New Booking', 'guidegrid-travel' ); ?></a>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tg-bookings', 'tg_export' => '1' ), admin_url( 'admin.php' ) ) ); ?>" class="tg-export-link button"><?php esc_html_e( 'Export CSV', 'guidegrid-travel' ); ?></a>
			<hr class="wp-header-end" />

			<?php if ( ! empty( $_GET['tg_msg'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['tg_msg'] ) ) ); ?></p></div>
			<?php endif; ?>

			<div class="tg-stats-row">
				<div class="tg-stat"><span class="tg-stat-num"><?php echo esc_html( (string) $stats['total'] ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Total Bookings', 'guidegrid-travel' ); ?></span></div>
				<div class="tg-stat"><span class="tg-stat-num"><?php echo esc_html( (string) ( $stats['pending'] + $stats['awaiting_payment'] ) ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Pending', 'guidegrid-travel' ); ?></span></div>
				<div class="tg-stat"><span class="tg-stat-num"><?php echo esc_html( (string) $stats['confirmed'] ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Confirmed', 'guidegrid-travel' ); ?></span></div>
				<div class="tg-stat"><span class="tg-stat-num"><?php echo esc_html( (string) $stats['completed'] ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Completed', 'guidegrid-travel' ); ?></span></div>
				<div class="tg-stat"><span class="tg-stat-num"><?php echo esc_html( (string) $stats['cancelled'] ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Cancelled', 'guidegrid-travel' ); ?></span></div>
				<div class="tg-stat"><span class="tg-stat-num"><?php echo esc_html( tg_format_price( (float) $stats['revenue'] ) ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Revenue', 'guidegrid-travel' ); ?></span></div>
			</div>

			<form method="get" class="tg-filter-form">
				<input type="hidden" name="page" value="tg-bookings" />
				<select name="status">
					<option value="all"><?php esc_html_e( 'All statuses', 'guidegrid-travel' ); ?></option>
					<?php foreach ( TG_Bookings::status_labels() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="payment_status">
					<option value="all"><?php esc_html_e( 'All payments', 'guidegrid-travel' ); ?></option>
					<?php foreach ( TG_Bookings::payment_labels() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f['payment_status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="number" name="tour_id" value="<?php echo esc_attr( (string) $f['tour_id'] ); ?>" min="0" title="<?php esc_attr_e( 'Tour ID', 'guidegrid-travel' ); ?>" />
				<input type="date" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>" title="<?php esc_attr_e( 'From date', 'guidegrid-travel' ); ?>" />
				<input type="date" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>" title="<?php esc_attr_e( 'To date', 'guidegrid-travel' ); ?>" />
				<input type="search" name="search" value="<?php echo esc_attr( $f['search'] ); ?>" placeholder="<?php esc_attr_e( 'Number, name or email…', 'guidegrid-travel' ); ?>" />
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'guidegrid-travel' ); ?></button>
				<a class="button" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Reset', 'guidegrid-travel' ); ?></a>
			</form>

			<form method="post" id="tg-bookings-bulk">
				<?php wp_nonce_field( 'tg_booking_actions', 'tg_actions_nonce' ); ?>
				<input type="hidden" name="tg_action" value="" id="tg-bulk-action" />
				<input type="hidden" name="tg_redirect" value="<?php echo esc_attr( $base_url ); ?>" />
				<table class="widefat striped tg-bookings-table">
					<thead>
						<tr>
							<th class="check-col"><input type="checkbox" id="tg-check-all" aria-label="<?php esc_attr_e( 'Select all', 'guidegrid-travel' ); ?>" /></th>
							<th><?php esc_html_e( 'Booking #', 'guidegrid-travel' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'guidegrid-travel' ); ?></th>
							<th><?php esc_html_e( 'Tour', 'guidegrid-travel' ); ?></th>
							<th><?php esc_html_e( 'Date', 'guidegrid-travel' ); ?></th>
							<th><?php esc_html_e( 'Guests', 'guidegrid-travel' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'guidegrid-travel' ); ?></th>
							<th><?php esc_html_e( 'Payment', 'guidegrid-travel' ); ?></th>
							<th><?php esc_html_e( 'Status', 'guidegrid-travel' ); ?></th>
							<th><?php esc_html_e( 'Created', 'guidegrid-travel' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'guidegrid-travel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $result['items'] ) ) : ?>
							<tr><td colspan="11"><?php esc_html_e( 'No bookings found for the selected filters.', 'guidegrid-travel' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $result['items'] as $row ) : ?>
								<?php
								$c      = TG_Bookings::customer( $row );
								$tour   = get_post( (int) $row->tour_id );
								$guests = (int) $row->adult_count + (int) $row->child_count + (int) $row->infant_count;
								?>
								<tr>
									<td><input type="checkbox" name="tg_ids[]" value="<?php echo esc_attr( (string) $row->id ); ?>" class="tg-row-id" /></td>
									<td class="tg-cell-num"><a href="<?php echo esc_url( admin_url( 'admin.php?page=tg-booking-detail&booking=' . (int) $row->id ) ); ?>"><?php echo esc_html( $row->booking_number ); ?></a></td>
									<td>
										<strong><?php echo esc_html( $c['first_name'] . ' ' . $c['last_name'] ); ?></strong><br />
										<span class="tg-muted"><?php echo esc_html( $c['email'] ); ?></span>
									</td>
									<td>
										<?php echo $tour ? '<a href="' . esc_url( get_edit_post_link( $tour->ID ) ) . '">' . esc_html( get_the_title( $tour ) ) . '</a>' : esc_html( '#' . (int) $row->tour_id ); ?>
									</td>
									<td><?php echo esc_html( tg_format_date( $row->booking_date ) ); ?></td>
									<td><?php echo esc_html( (string) $guests ); ?></td>
									<td><?php echo esc_html( tg_format_price( (float) $row->total, $row->currency ) ); ?></td>
									<td><?php echo tg_status_badge( $row->payment_status, 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									<td><?php echo tg_status_badge( $row->booking_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									<td><?php echo esc_html( tg_format_date( $row->created_at ) ); ?></td>
									<td class="tg-row-actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=tg-booking-detail&booking=' . (int) $row->id ) ); ?>" title="<?php esc_attr_e( 'View', 'guidegrid-travel' ); ?>"><?php esc_html_e( 'View', 'guidegrid-travel' ); ?></a>
										<?php if ( in_array( $row->booking_status, array( 'pending', 'awaiting_payment' ), true ) ) : ?>
											| <a href="<?php echo esc_url( tg_booking_action_url( $row->id, 'confirm' ) ); ?>" title="<?php esc_attr_e( 'Confirm', 'guidegrid-travel' ); ?>"><?php esc_html_e( 'Confirm', 'guidegrid-travel' ); ?></a>
											| <a href="<?php echo esc_url( tg_booking_action_url( $row->id, 'mark_paid' ) ); ?>" title="<?php esc_attr_e( 'Mark paid', 'guidegrid-travel' ); ?>"><?php esc_html_e( 'Mark paid', 'guidegrid-travel' ); ?></a>
										<?php endif; ?>
										<?php if ( in_array( $row->booking_status, array( 'confirmed', 'paid', 'partially_paid' ), true ) ) : ?>
											| <a href="<?php echo esc_url( tg_booking_action_url( $row->id, 'cancel' ) ); ?>" class="tg-danger-link" title="<?php esc_attr_e( 'Cancel', 'guidegrid-travel' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Cancel this booking?', 'guidegrid-travel' ) ); ?>');"><?php esc_html_e( 'Cancel', 'guidegrid-travel' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="11">
								<select name="tg_bulk" id="tg-bulk-select">
									<option value=""><?php esc_html_e( 'Bulk actions', 'guidegrid-travel' ); ?></option>
									<option value="confirm"><?php esc_html_e( 'Confirm', 'guidegrid-travel' ); ?></option>
									<option value="mark_paid"><?php esc_html_e( 'Mark paid', 'guidegrid-travel' ); ?></option>
									<option value="complete"><?php esc_html_e( 'Mark completed', 'guidegrid-travel' ); ?></option>
									<option value="cancel"><?php esc_html_e( 'Cancel', 'guidegrid-travel' ); ?></option>
								</select>
								<button type="submit" class="button action"><?php esc_html_e( 'Apply', 'guidegrid-travel' ); ?></button>
							</td>
						</tr>
					</tfoot>
				</table>
			</form>

			<?php
			$paged_args = array(
				'base'    => add_query_arg( array( 'page' => 'tg-bookings' ), admin_url( 'admin.php' ) ),
				'current' => $f['page'],
				'total'   => $pages,
			);
			echo wp_kses_post( paginate_links( $paged_args ) );
			?>
		</div>
		<?php
	}
}

/**
 * Build a nonce-protected action URL.
 *
 * @param int    $id      Booking ID.
 * @param string $action  Action.
 * @return string
 */
function tg_booking_action_url( int $id, string $action ): string {
	return wp_nonce_url(
		add_query_arg(
			array(
				'page'     => 'tg-bookings',
				'tg_do'    => $action,
				'tg_id'    => $id,
			),
			admin_url( 'admin.php' )
		),
		'tg_booking_action_' . $id . '_' . $action
	);
}

/**
 * Handle single-row + bulk booking actions (GET row actions, POST bulk).
 *
 * @param string $redirect Redirect base.
 * @return void
 */
function tg_process_booking_actions( string $redirect ) {
	if ( ! current_user_can( 'manage_tg_bookings' ) ) {
		return;
	}

	// Single row actions via GET (nonce-protected URLs).
	if ( isset( $_GET['tg_do'], $_GET['tg_id'] ) ) {
		$action = sanitize_key( $_GET['tg_do'] );
		$id     = absint( $_GET['tg_id'] );
		$check  = 'tg_booking_action_' . $id . '_' . $action;
		if ( wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '', $check ) ) {
			tg_do_booking_action( $action, array( $id ), $redirect );
		}
		return;
	}

	// Bulk actions via POST.
	if ( isset( $_POST['tg_actions_nonce'], $_POST['tg_bulk'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		if ( wp_verify_nonce( sanitize_key( $_POST['tg_actions_nonce'] ), 'tg_booking_actions' ) ) {
			$action = sanitize_key( $_POST['tg_bulk'] );
			$ids    = array_map( 'absint', (array) ( isset( $_POST['tg_ids'] ) ? wp_unslash( $_POST['tg_ids'] ) : array() ) );
			$ids    = array_filter( $ids );
			if ( $action && $ids ) {
				tg_do_booking_action( $action, array_values( $ids ), $redirect );
			}
		}
	}
}

/**
 * Execute a booking action (single or bulk) with transition control.
 *
 * @param string   $action   Action key.
 * @param int[]    $ids      Booking IDs.
 * @param string   $redirect Redirect base.
 * @return void
 */
function tg_do_booking_action( string $action, array $ids, string $redirect ) {
	$messages = array();

	foreach ( $ids as $id ) {
		$result = true;
		switch ( $action ) {
			case 'confirm':
				$result = TG_Bookings::confirm( $id );
				break;
			case 'mark_paid':
				$result = TG_Bookings::mark_paid( $id, 'manual' );
				break;
			case 'cancel':
				$result = TG_Bookings::cancel( $id, 'admin' );
				break;
			case 'complete':
				$result = TG_Bookings::complete( $id );
				break;
			case 'refund_requested':
				$result = TG_Bookings::request_refund( $id, 'admin' );
				break;
		}

		$messages[] = is_wp_error( $result )
			? $result->get_error_message()
			: sprintf( /* translators: %s: action */ __( 'Booking #%d: %s', 'guidegrid-travel' ), $id, ucfirst( $action ) );
	}

	$first = $messages ? $messages[0] : __( 'Action complete.', 'guidegrid-travel' );
	wp_safe_redirect( add_query_arg( 'tg_msg', rawurlencode( $first ), $redirect ) );
	exit;
}

/**
 * Export bookings to CSV (streamed download).
 *
 * @return void
 */
function tg_export_bookings_csv() {
	// This callback runs on every wp-admin request. Do not perform capability
	// checks (or terminate the request) unless an export was actually requested.
	if ( ! isset( $_GET['tg_export'] ) || '1' !== $_GET['tg_export'] ) {
		return;
	}
	if ( ! current_user_can( 'manage_tg_bookings' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'guidegrid-travel' ) );
	}

	// Reuse the current filters.
	$f = array(
		'status'         => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all',
		'payment_status' => isset( $_GET['payment_status'] ) ? sanitize_key( $_GET['payment_status'] ) : 'all',
		'tour_id'        => isset( $_GET['tour_id'] ) ? absint( $_GET['tour_id'] ) : 0,
		'search'         => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
		'per_page'       => 1000,
	);
	$result = tg_query_admin_bookings( $f );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=tg-bookings-' . gmdate( 'Ymd-His' ) . '.csv' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'booking_number', 'tour_id', 'tour', 'customer_name', 'customer_email', 'phone', 'date', 'adults', 'children', 'infants', 'subtotal', 'discount', 'tax', 'service_fee', 'total', 'currency', 'payment_status', 'booking_status', 'created_at' ) );

	foreach ( $result['items'] as $row ) {
		$c    = TG_Bookings::customer( $row );
		$tour = get_post( (int) $row->tour_id );
		fputcsv(
			$out,
			array(
				$row->booking_number,
				$row->tour_id,
				$tour ? $tour->post_title : '',
				$c['first_name'] . ' ' . $c['last_name'],
				$c['email'],
				$c['phone'],
				$row->booking_date,
				$row->adult_count,
				$row->child_count,
				$row->infant_count,
				$row->subtotal,
				$row->discount,
				$row->tax,
				$row->service_fee,
				$row->total,
				$row->currency,
				$row->payment_status,
				$row->booking_status,
				$row->created_at,
			)
		);
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}
add_action( 'admin_init', 'tg_export_bookings_csv' );

/* =========================================================================
 * Booking detail page
 * ========================================================================= */

if ( ! function_exists( 'tg_render_booking_detail_page' ) ) {
	/**
	 * Booking detail admin page.
	 *
	 * @return void
	 */
	function tg_render_booking_detail_page() {
		if ( ! current_user_can( 'manage_tg_bookings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'guidegrid-travel' ) );
		}

		$id      = isset( $_GET['booking'] ) ? absint( $_GET['booking'] ) : 0;
		$booking = TG_Bookings::get( $id );

		if ( ! $booking ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Booking not found.', 'guidegrid-travel' ) . '</p></div></div>';
			return;
		}

		$tour       = get_post( (int) $booking->tour_id );
		$c          = TG_Bookings::customer( $booking );
		$snapshot   = TG_Bookings::price_snapshot( $booking );
		$addons     = TG_Bookings::addons( $booking );
		$payments   = TG_Payments::get_for_booking( $id );
		$notes      = TG_Bookings::notes( $id );
		$base_url   = admin_url( 'admin.php?page=tg-booking-detail&booking=' . $id );
		?>
		<div class="wrap tg-admin">
			<h1><?php esc_html_e( 'Booking Details', 'guidegrid-travel' ); ?> <span class="tg-cell-num"><?php echo esc_html( $booking->booking_number ); ?></span></h1>
			<?php if ( ! empty( $_GET['tg_msg'] ) ) : ?>
				<div class="notice <?php echo ! empty( $_GET['tg_error'] ) ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['tg_msg'] ) ) ); ?></p></div>
			<?php endif; ?>
			<p>
				<?php echo tg_status_badge( $booking->booking_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo tg_status_badge( $booking->payment_status, 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tg-bookings' ) ); ?>">← <?php esc_html_e( 'All bookings', 'guidegrid-travel' ); ?></a>
			</p>

			<div class="tg-detail-grid">
				<div class="tg-detail-card">
					<h2><?php esc_html_e( 'Customer', 'guidegrid-travel' ); ?></h2>
					<p><strong><?php echo esc_html( $c['first_name'] . ' ' . $c['last_name'] ); ?></strong><br />
					<?php echo esc_html( $c['email'] ); ?><?php echo ! empty( $c['phone'] ) ? ' / ' . esc_html( $c['phone'] ) : ''; ?><?php echo ! empty( $c['country'] ) ? ' / ' . esc_html( $c['country'] ) : ''; ?></p>
					<?php if ( ! empty( $c['address'] ) ) : ?>
						<p><?php echo esc_html( $c['address'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $c['special_request'] ) ) : ?>
						<p><em><?php esc_html_e( 'Special request:', 'guidegrid-travel' ); ?> <?php echo esc_html( $c['special_request'] ); ?></em></p>
					<?php endif; ?>
				</div>

				<div class="tg-detail-card">
					<h2><?php esc_html_e( 'Tour', 'guidegrid-travel' ); ?></h2>
					<p>
						<strong><?php echo $tour ? esc_html( get_the_title( $tour ) ) : esc_html( '#' . (int) $booking->tour_id ); ?></strong><br />
						<?php echo esc_html( tg_format_date( $booking->booking_date ) ); ?><?php echo $booking->tour_end_date ? ' → ' . esc_html( tg_format_date( $booking->tour_end_date ) ) : ''; ?>
						<?php if ( $tour ) : ?>
							<br /><a href="<?php echo esc_url( get_permalink( $tour ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View tour page', 'guidegrid-travel' ); ?></a>
						<?php endif; ?>
					</p>
					<p>
						<?php esc_html_e( 'Guests:', 'guidegrid-travel' ) ?>
						<?php
						printf(
							/* translators: 1: adults, 2: children, 3: infants */
							esc_html( _n( '%1$d adult, %2$d child, %3$d infant', '%1$d adults, %2$d children, %3$d infants', (int) $booking->adult_count + (int) $booking->child_count + (int) $booking->infant_count, 'guidegrid-travel' ) ),
							(int) $booking->adult_count,
							(int) $booking->child_count,
							(int) $booking->infant_count
						);
						?>
					</p>
					<?php if ( $tour && get_post_meta( (int) $booking->tour_id, '_tg_guide', true ) ) : ?>
						<p><?php esc_html_e( 'Guide:', 'guidegrid-travel' ) ?> <?php echo esc_html( get_post_meta( (int) $booking->tour_id, '_tg_guide', true ) ); ?></p>
					<?php endif; ?>
				</div>

				<div class="tg-detail-card">
					<h2><?php esc_html_e( 'Pricing (price snapshot)', 'guidegrid-travel' ); ?></h2>
					<table class="tg-table">
						<tr><td><?php esc_html_e( 'Adult', 'guidegrid-travel' ); ?> <?php echo esc_html( tg_format_price( (float) $booking->adult_unit_price, $booking->currency ) ); ?> × <?php echo esc_html( (string) $booking->adult_count ); ?></td><td><?php echo esc_html( tg_format_price( round( (float) $booking->adult_unit_price * (int) $booking->adult_count, 2 ), $booking->currency ) ); ?></td></tr>
						<?php if ( (int) $booking->child_count ) : ?>
							<tr><td><?php esc_html_e( 'Child', 'guidegrid-travel' ); ?> <?php echo esc_html( tg_format_price( (float) $booking->child_unit_price, $booking->currency ) ); ?> × <?php echo esc_html( (string) $booking->child_count ); ?></td><td><?php echo esc_html( tg_format_price( round( (float) $booking->child_unit_price * (int) $booking->child_count, 2 ), $booking->currency ) ); ?></td></tr>
						<?php endif; ?>
						<?php if ( (int) $booking->infant_count ) : ?>
							<tr><td><?php esc_html_e( 'Infant', 'guidegrid-travel' ); ?> <?php echo esc_html( tg_format_price( (float) $booking->infant_unit_price, $booking->currency ) ); ?> × <?php echo esc_html( (string) $booking->infant_count ); ?></td><td><?php echo esc_html( tg_format_price( round( (float) $booking->infant_unit_price * (int) $booking->infant_count, 2 ), $booking->currency ) ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $addons as $line ) : ?>
							<tr><td><?php echo esc_html( $line['name'] ); ?> × <?php echo esc_html( (string) $line['qty'] ); ?></td><td><?php echo esc_html( tg_format_price( (float) $line['amount'], $booking->currency ) ); ?></td></tr>
						<?php endforeach; ?>
						<?php if ( (float) $booking->discount > 0 ) : ?>
							<tr class="tg-row-discount"><td><?php esc_html_e( 'Discount', 'guidegrid-travel' ); ?><?php echo $booking->coupon_code ? ' (' . esc_html( $booking->coupon_code ) . ')' : ''; ?></td><td>−<?php echo esc_html( tg_format_price( (float) $booking->discount, $booking->currency ) ); ?></td></tr>
						<?php endif; ?>
						<tr><td><?php esc_html_e( 'Tax', 'guidegrid-travel' ); ?></td><td><?php echo esc_html( tg_format_price( (float) $booking->tax, $booking->currency ) ); ?></td></tr>
						<?php if ( (float) $booking->service_fee > 0 ) : ?>
							<tr><td><?php esc_html_e( 'Service fee', 'guidegrid-travel' ); ?></td><td><?php echo esc_html( tg_format_price( (float) $booking->service_fee, $booking->currency ) ); ?></td></tr>
						<?php endif; ?>
						<tr class="tg-row-total"><td><?php esc_html_e( 'Total', 'guidegrid-travel' ); ?></td><td><?php echo esc_html( tg_format_price( (float) $booking->total, $booking->currency ) ); ?></td></tr>
						<?php if ( (float) $booking->deposit > 0 ) : ?>
							<tr><td><?php esc_html_e( 'Deposit', 'guidegrid-travel' ); ?></td><td><?php echo esc_html( tg_format_price( (float) $booking->deposit, $booking->currency ) ); ?></td></tr>
						<?php endif; ?>
					</table>
				</div>

				<div class="tg-detail-card">
					<h2><?php esc_html_e( 'Payment', 'guidegrid-travel' ); ?></h2>
					<table class="tg-table">
						<?php if ( empty( $payments ) ) : ?>
							<tr><td><?php esc_html_e( 'No payment records yet.', 'guidegrid-travel' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $payments as $pay ) : ?>
								<tr>
									<td>
										<?php echo esc_html( tg_format_date( $pay->created_at ) ); ?><br />
										<span class="tg-muted"><?php echo esc_html( $pay->gateway . ( $pay->payment_method ? ' / ' . $pay->payment_method : '' ) . ( $pay->transaction_id ? ' / ' . $pay->transaction_id : '' ) ); ?></span>
									</td>
									<td>
										<?php echo tg_status_badge( $pay->status, 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br />
										<?php echo esc_html( tg_format_price( (float) $pay->amount, $pay->currency ) ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</table>
				</div>
			</div>

			<div class="tg-actions-bar">
				<?php echo wp_nonce_field( 'tg_booking_detail', 'tg_detail_nonce', false, false ); ?>

				<?php if ( in_array( $booking->booking_status, array( 'pending', 'awaiting_payment' ), true ) ) : ?>
					<button type="submit" form="tg-act-confirm" class="button button-primary"><?php esc_html_e( 'Confirm', 'guidegrid-travel' ); ?></button>
					<button type="submit" form="tg-act-paid" class="button button-primary"><?php esc_html_e( 'Mark Paid', 'guidegrid-travel' ); ?></button>
				<?php endif; ?>
				<?php if ( in_array( $booking->booking_status, array( 'confirmed', 'paid', 'partially_paid' ), true ) ) : ?>
					<button type="submit" form="tg-act-complete" class="button"><?php esc_html_e( 'Mark Completed', 'guidegrid-travel' ); ?></button>
					<button type="submit" form="tg-act-refundreq" class="button"><?php esc_html_e( 'Request Refund', 'guidegrid-travel' ); ?></button>
					<button type="submit" form="tg-act-cancel" class="button tg-danger-btn"><?php esc_html_e( 'Cancel Booking', 'guidegrid-travel' ); ?></button>
				<?php endif; ?>
				<?php if ( in_array( $booking->booking_status, array( 'refund_requested' ), true ) ) : ?>
					<button type="submit" form="tg-act-refund" class="button tg-danger-btn"><?php esc_html_e( 'Record Full Refund', 'guidegrid-travel' ); ?></button>
				<?php endif; ?>
				<button type="button" class="button" onclick="window.print()"><?php esc_html_e( 'Print', 'guidegrid-travel' ); ?></button>
			</div>

			<!-- Hidden action forms -->
			<form id="tg-act-confirm" method="post" action="<?php echo esc_url( $base_url ); ?>"><?php echo wp_nonce_field( 'tg_booking_detail', 'tg_detail_nonce', false, false ); ?><input type="hidden" name="tg_detail_action" value="confirm" /><input type="hidden" name="tg_id" value="<?php echo esc_attr( (string) $id ); ?>" /></form>
			<form id="tg-act-paid" method="post" action="<?php echo esc_url( $base_url ); ?>"><?php echo wp_nonce_field( 'tg_booking_detail', 'tg_detail_nonce', false, false ); ?><input type="hidden" name="tg_detail_action" value="mark_paid" /><input type="hidden" name="tg_id" value="<?php echo esc_attr( (string) $id ); ?>" /></form>
			<form id="tg-act-complete" method="post" action="<?php echo esc_url( $base_url ); ?>"><?php echo wp_nonce_field( 'tg_booking_detail', 'tg_detail_nonce', false, false ); ?><input type="hidden" name="tg_detail_action" value="complete" /><input type="hidden" name="tg_id" value="<?php echo esc_attr( (string) $id ); ?>" /></form>
			<form id="tg-act-refundreq" method="post" action="<?php echo esc_url( $base_url ); ?>"><?php echo wp_nonce_field( 'tg_booking_detail', 'tg_detail_nonce', false, false ); ?><input type="hidden" name="tg_detail_action" value="refund_requested" /><input type="hidden" name="tg_id" value="<?php echo esc_attr( (string) $id ); ?>" /></form>
			<form id="tg-act-refund" method="post" action="<?php echo esc_url( $base_url ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Confirm the funds were refunded outside GuideGrid, then record the full refund?', 'guidegrid-travel' ) ); ?>');"><?php echo wp_nonce_field( 'tg_booking_detail', 'tg_detail_nonce', false, false ); ?><input type="hidden" name="tg_detail_action" value="refund" /><input type="hidden" name="tg_id" value="<?php echo esc_attr( (string) $id ); ?>" /></form>
			<form id="tg-act-cancel" method="post" action="<?php echo esc_url( $base_url ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Cancel this booking?', 'guidegrid-travel' ) ); ?>');"><?php echo wp_nonce_field( 'tg_booking_detail', 'tg_detail_nonce', false, false ); ?><input type="hidden" name="tg_detail_action" value="cancel" /><input type="hidden" name="tg_id" value="<?php echo esc_attr( (string) $id ); ?>" /></form>

			<div class="tg-detail-card tg-notes-card">
				<h2><?php esc_html_e( 'Internal Notes', 'guidegrid-travel' ); ?> <span class="tg-muted">(<?php esc_html_e( 'private — never shown to customers', 'guidegrid-travel' ); ?>)</span></h2>
				<form method="post" class="tg-note-form">
					<?php echo wp_nonce_field( 'tg_booking_detail', 'tg_detail_nonce', false, false ); ?>
					<input type="hidden" name="tg_detail_action" value="add_note" />
					<input type="hidden" name="tg_id" value="<?php echo esc_attr( (string) $id ); ?>" />
					<textarea name="tg_note" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'Add an internal note…', 'guidegrid-travel' ); ?>" required></textarea>
					<p><button type="submit" class="button"><?php esc_html_e( 'Add Note', 'guidegrid-travel' ); ?></button></p>
				</form>
				<ul class="tg-notes-list">
					<?php if ( empty( $notes ) ) : ?>
						<li><?php esc_html_e( 'No internal notes yet.', 'guidegrid-travel' ); ?></li>
					<?php else : ?>
						<?php foreach ( $notes as $note ) : ?>
							<li>
								<strong><?php echo esc_html( tg_format_date( $note->created_at ) ); ?></strong>
								<?php
								$note_user = get_userdata( (int) $note->user_id );
								if ( $note_user ) :
									echo ' — ' . esc_html( $note_user->display_name );
								endif;
								?>
								<p><?php echo esc_html( $note->note ); ?></p>
							</li>
						<?php endforeach; ?>
					<?php endif; ?>
				</ul>
			</div>
		</div>
		<?php
	}
}

/**
 * Process detail page actions.
 *
 * @return void
 */
function tg_process_detail_actions() {
	$action = isset( $_POST['tg_detail_action'] ) ? sanitize_key( wp_unslash( $_POST['tg_detail_action'] ) ) : '';
	$id     = isset( $_POST['tg_id'] ) ? absint( $_POST['tg_id'] ) : 0;

	if ( ! $id || ! $action ) {
		return;
	}

	$result = true;
	if ( ! TG_Bookings::get( $id ) ) {
		$result = new WP_Error( 'tg_booking_missing', __( 'Booking not found.', 'guidegrid-travel' ) );
	} else {
		switch ( $action ) {
			case 'add_note':
				$note = isset( $_POST['tg_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tg_note'] ) ) : '';
				$result = '' !== $note && TG_Bookings::add_note( $id, $note, get_current_user_id() )
					? true
					: new WP_Error( 'tg_note_empty', __( 'The note could not be added.', 'guidegrid-travel' ) );
				break;
			case 'confirm':
				$result = TG_Bookings::confirm( $id );
				break;
			case 'mark_paid':
				$result = TG_Bookings::mark_paid( $id, 'manual' );
				break;
			case 'cancel':
				$result = TG_Bookings::cancel( $id, 'admin' );
				break;
			case 'complete':
				$result = TG_Bookings::complete( $id );
				break;
			case 'refund_requested':
				$result = TG_Bookings::request_refund( $id, 'admin' );
				break;
			case 'refund':
				$result = TG_Bookings::refund( $id, 0, 'admin' );
				break;
			default:
				$result = new WP_Error( 'tg_booking_action_invalid', __( 'That booking action is not available.', 'guidegrid-travel' ) );
		}
	}

	$is_error = is_wp_error( $result );
	$message  = $is_error ? $result->get_error_message() : __( 'Action processed.', 'guidegrid-travel' );
	$args     = array(
		'page'    => 'tg-booking-detail',
		'booking' => $id,
		'tg_msg'  => $message,
	);
	if ( $is_error ) {
		$args['tg_error'] = '1';
	}
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
	exit;
}

/**
 * Process booking admin actions before WordPress outputs admin headers.
 *
 * Handling redirects inside an admin page-render callback is too late because
 * admin-header.php has already started the response. That produced “headers
 * already sent” warnings when confirming, cancelling, or updating a booking.
 *
 * @return void
 */
function tg_handle_booking_admin_requests() {
	$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! in_array( $page, array( 'tg-bookings', 'tg-booking-detail' ), true ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_tg_bookings' ) ) {
		return;
	}

	if ( 'tg-bookings' === $page ) {
		tg_process_booking_actions( admin_url( 'admin.php?page=tg-bookings' ) );
		return;
	}

	if ( 'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) && isset( $_POST['tg_detail_nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_POST['tg_detail_nonce'] ) );
		if ( wp_verify_nonce( $nonce, 'tg_booking_detail' ) ) {
			tg_process_detail_actions();
		}
	}
}
add_action( 'admin_init', 'tg_handle_booking_admin_requests', 20 );

/* =========================================================================
 * Manual booking (admin)
 * ========================================================================= */

if ( ! function_exists( 'tg_render_manual_booking_page' ) ) {
	/**
	 * Manual booking page — uses the same server-side engine.
	 *
	 * @return void
	 */
	function tg_render_manual_booking_page() {
		if ( ! current_user_can( 'manage_tg_bookings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'guidegrid-travel' ) );
		}

		$errors   = array();
		$created  = null;

		if ( isset( $_POST['tg_manual_nonce'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			if ( wp_verify_nonce( sanitize_key( $_POST['tg_manual_nonce'] ), 'tg_manual_booking' ) ) {
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

				$result = TG_Bookings::create(
					array(
						'tour_id'        => isset( $_POST['tour_id'] ) ? absint( $_POST['tour_id'] ) : 0,
						'date'           => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
						'adults'         => isset( $_POST['adults'] ) ? absint( $_POST['adults'] ) : 0,
						'children'       => isset( $_POST['children'] ) ? absint( $_POST['children'] ) : 0,
						'infants'        => isset( $_POST['infants'] ) ? absint( $_POST['infants'] ) : 0,
						'addons'         => $addons,
						'coupon'         => isset( $_POST['coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon'] ) ) : '',
						'payment_method' => isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : 'bank',
						'customer'       => $customer,
						'user_id'        => ( isset( $_POST['user_id'] ) && absint( $_POST['user_id'] ) ) ? absint( $_POST['user_id'] ) : 0,
						'admin_paid'     => isset( $_POST['admin_paid'] ),
						'source'         => 'admin',
					)
				);

				if ( is_wp_error( $result ) ) {
					$errors[] = $result->get_error_message();
				} else {
					$created = $result;
				}
			}
		}

		$settings = tg_settings();
		?>
		<div class="wrap tg-admin">
			<h1><?php esc_html_e( 'New Manual Booking', 'guidegrid-travel' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Uses the same price/availability engine as the frontend. Prices are recalculated server-side.', 'guidegrid-travel' ); ?></p>

			<?php foreach ( $errors as $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endforeach; ?>

			<?php if ( $created ) : ?>
				<div class="notice notice-success"><p>
					<?php
					printf(
						/* translators: %s: booking number */
						esc_html__( 'Booking %s created.', 'guidegrid-travel' ),
						'<strong>' . esc_html( $created->booking_number ) . '</strong>'
					);
					?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tg-booking-detail&booking=' . (int) $created->id ) ); ?>"><?php esc_html_e( 'View', 'guidegrid-travel' ); ?></a>
				</p></div>
			<?php endif; ?>

			<form method="post" class="tg-manual-form">
				<?php wp_nonce_field( 'tg_manual_booking', 'tg_manual_nonce' ); ?>
				<div class="tg-detail-grid">
					<div class="tg-detail-card">
						<h2><?php esc_html_e( 'Trip', 'guidegrid-travel' ); ?></h2>
						<p>
							<label class="tg-label" for="tg-m-tour"><?php esc_html_e( 'Tour', 'guidegrid-travel' ); ?></label>
							<select name="tour_id" id="tg-m-tour" class="widefat" required>
								<option value=""><?php esc_html_e( '— Select tour —', 'guidegrid-travel' ); ?></option>
								<?php echo tg_get_tour_options_for_select( 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</select>
						</p>
						<p>
							<label class="tg-label" for="tg-m-date"><?php esc_html_e( 'Date', 'guidegrid-travel' ); ?></label>
							<input type="date" name="date" id="tg-m-date" class="widefat" required />
						</p>
						<div class="tg-mb-grid">
							<p>
								<label class="tg-label" for="tg-m-adults"><?php esc_html_e( 'Adults', 'guidegrid-travel' ); ?></label>
								<input type="number" name="adults" id="tg-m-adults" min="1" value="2" class="widefat" />
							</p>
							<p>
								<label class="tg-label" for="tg-m-children"><?php esc_html_e( 'Children', 'guidegrid-travel' ); ?></label>
								<input type="number" name="children" id="tg-m-children" min="0" value="0" class="widefat" />
							</p>
							<p>
								<label class="tg-label" for="tg-m-infants"><?php esc_html_e( 'Infants', 'guidegrid-travel' ); ?></label>
								<input type="number" name="infants" id="tg-m-infants" min="0" value="0" class="widefat" />
							</p>
						</div>
						<p>
							<label class="tg-label" for="tg-m-coupon"><?php esc_html_e( 'Coupon code (optional)', 'guidegrid-travel' ); ?></label>
							<input type="text" name="coupon" id="tg-m-coupon" class="widefat" />
						</p>
					</div>

					<div class="tg-detail-card">
						<h2><?php esc_html_e( 'Customer', 'guidegrid-travel' ); ?></h2>
						<p>
							<label class="tg-label" for="tg-m-fname"><?php esc_html_e( 'First name', 'guidegrid-travel' ); ?></label>
							<input type="text" name="customer[first_name]" id="tg-m-fname" class="widefat" required />
						</p>
						<p>
							<label class="tg-label" for="tg-m-lname"><?php esc_html_e( 'Last name', 'guidegrid-travel' ); ?></label>
							<input type="text" name="customer[last_name]" id="tg-m-lname" class="widefat" />
						</p>
						<p>
							<label class="tg-label" for="tg-m-email"><?php esc_html_e( 'Email', 'guidegrid-travel' ); ?></label>
							<input type="email" name="customer[email]" id="tg-m-email" class="widefat" required />
						</p>
						<div class="tg-mb-grid">
							<p>
								<label class="tg-label" for="tg-m-phone"><?php esc_html_e( 'Phone', 'guidegrid-travel' ); ?></label>
								<input type="text" name="customer[phone]" id="tg-m-phone" class="widefat" />
							</p>
							<p>
								<label class="tg-label" for="tg-m-country"><?php esc_html_e( 'Country', 'guidegrid-travel' ); ?></label>
								<input type="text" name="customer[country]" id="tg-m-country" class="widefat" />
							</p>
						</div>
						<p>
							<label class="tg-label" for="tg-m-notes"><?php esc_html_e( 'Special request / notes', 'guidegrid-travel' ); ?></label>
							<textarea name="customer[special_request]" id="tg-m-notes" rows="2" class="widefat"></textarea>
						</p>
					</div>

					<div class="tg-detail-card">
						<h2><?php esc_html_e( 'Payment', 'guidegrid-travel' ); ?></h2>
						<p>
							<label class="tg-label" for="tg-m-method"><?php esc_html_e( 'Payment method', 'guidegrid-travel' ); ?></label>
							<select name="payment_method" id="tg-m-method" class="widefat">
								<?php foreach ( TG_Payments::manual_methods() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p>
							<label><input type="checkbox" name="admin_paid" value="1" /> <?php esc_html_e( 'Already paid — create as paid + confirmed', 'guidegrid-travel' ); ?></label>
						</p>
					</div>
				</div>
				<p class="submit"><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Create Booking', 'guidegrid-travel' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
