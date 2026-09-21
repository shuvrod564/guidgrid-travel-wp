<?php
/**
 * Enquiries admin (list + status + delete).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_render_enquiries_page' ) ) {
	/**
	 * Render the enquiries page.
	 *
	 * @return void
	 */
	function tg_render_enquiries_page() {
		if ( ! current_user_can( 'manage_tg' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'guidegrid-travel' ) );
		}

		$global = $GLOBALS['wpdb'];
		$table  = TG_Database::table( 'enquiries' );
		$notice = '';

		if ( isset( $_GET['tg_enq_do'], $_GET['tg_enq_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$do  = sanitize_key( $_GET['tg_enq_do'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$id  = absint( $_GET['tg_enq_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$ok  = wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'tg_enq_' . $id . '_' . $do ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $ok && $id ) {
				if ( 'done' === $do ) {
					$global->update( $table, array( 'status' => 'done' ), array( 'id' => $id ) );
					$notice = __( 'Enquiry marked as done.', 'guidegrid-travel' );
				} elseif ( 'delete' === $do ) {
					$global->delete( $table, array( 'id' => $id ) );
					$notice = __( 'Enquiry deleted.', 'guidegrid-travel' );
				}
			}
		}

		$rows = $global->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<div class="wrap tg-admin">
			<h1><?php esc_html_e( 'Enquiries', 'guidegrid-travel' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name / Contact', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Destination / Tour', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Date / Travelers', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Budget', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Message', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Status', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'guidegrid-travel' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No enquiries yet.', 'guidegrid-travel' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php $tour = $row->tour_id ? get_post( (int) $row->tour_id ) : null; ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $row->name ); ?></strong><br />
									<a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a>
									<?php echo $row->phone ? ' / ' . esc_html( $row->phone ) : ''; ?>
									<br /><span class="tg-muted"><?php echo esc_html( tg_format_date( $row->created_at ) ); ?></span>
								</td>
								<td>
									<?php echo esc_html( $row->destination ); ?>
									<?php if ( $tour ) : ?>
										<br /><a href="<?php echo esc_url( get_edit_post_link( $tour->ID ) ); ?>"><?php echo esc_html( get_the_title( $tour ) ); ?></a>
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html( tg_format_date( (string) $row->travel_date ) ); ?><br />
									<?php echo esc_html( (string) $row->travelers ); ?>
								</td>
								<td><?php echo esc_html( $row->budget ); ?></td>
								<td><?php echo esc_html( wp_trim_words( (string) $row->message, 30 ) ); ?></td>
								<td>
									<?php echo 'new' === $row->status ? '<span class="tg-badge tg-badge--info">' . esc_html__( 'New', 'guidegrid-travel' ) . '</span>' : '<span class="tg-badge tg-badge--success">' . esc_html__( 'Done', 'guidegrid-travel' ) . '</span>'; ?>
								</td>
								<td class="tg-row-actions">
									<?php if ( 'new' === $row->status ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=tg-enquiries&tg_enq_do=done&tg_enq_id=' . (int) $row->id ), 'tg_enq_' . $row->id . '_done' ) ); ?>"><?php esc_html_e( 'Mark done', 'guidegrid-travel' ); ?></a> |
									<?php endif; ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=tg-enquiries&tg_enq_do=delete&tg_enq_id=' . (int) $row->id ), 'tg_enq_' . $row->id . '_delete' ) ); ?>" class="tg-danger-link" onclick="return confirm('<?php echo esc_js( __( 'Delete this enquiry?', 'guidegrid-travel' ) ); ?>');"><?php esc_html_e( 'Delete', 'guidegrid-travel' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
