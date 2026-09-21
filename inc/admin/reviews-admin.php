<?php
/**
 * Reviews moderation (admin).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_render_reviews_admin_page' ) ) {
	/**
	 * Render the reviews admin page.
	 *
	 * @return void
	 */
	function tg_render_reviews_admin_page() {
		if ( ! current_user_can( 'manage_tg' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'guidegrid-travel' ) );
		}

		$notice = '';
		if ( isset( $_GET['tg_review_do'], $_GET['tg_review_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$do  = sanitize_key( $_GET['tg_review_do'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$rid = absint( $_GET['tg_review_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$nonce_ok = wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'tg_review_' . $rid . '_' . $do ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $nonce_ok && $rid ) {
				if ( 'approve' === $do ) {
					TG_Reviews::set_status( $rid, 'approved' );
					$notice = __( 'Review approved.', 'guidegrid-travel' );
				} elseif ( 'reject' === $do ) {
					TG_Reviews::set_status( $rid, 'rejected' );
					$notice = __( 'Review rejected.', 'guidegrid-travel' );
				} elseif ( 'delete' === $do ) {
					TG_Reviews::delete( $rid );
					$notice = __( 'Review deleted.', 'guidegrid-travel' );
				}
			}
		}

		$filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'pending'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $filter, array( 'pending', 'approved', 'rejected', 'all' ), true ) ) {
			$filter = 'pending';
		}

		$global = $GLOBALS['wpdb'];
		$table  = TG_Database::table( 'reviews' );
		$where  = ( 'all' === $filter ) ? '1=1' : 'status = %s';
		$params = ( 'all' === $filter ) ? array() : array( $filter );
		$rows   = $global->get_results(
			$params
				? $global->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT 100", $params ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				: "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT 100" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		?>
		<div class="wrap tg-admin">
			<h1><?php esc_html_e( 'Reviews', 'guidegrid-travel' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<ul class="subsubsub">
				<?php foreach ( array( 'pending' => __( 'Pending', 'guidegrid-travel' ), 'approved' => __( 'Approved', 'guidegrid-travel' ), 'rejected' => __( 'Rejected', 'guidegrid-travel' ), 'all' => __( 'All', 'guidegrid-travel' ) ) as $key => $label ) : ?>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tg-reviews&status=' . $key ) ); ?>" <?php echo $filter === $key ? 'class="current"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Customer', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Tour', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Rating', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Review', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Date', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Status', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'guidegrid-travel' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'Nothing here.', 'guidegrid-travel' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $review ) : ?>
							<?php $tour = get_post( (int) $review->tour_id ); ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $review->name ); ?></strong><br />
									<span class="tg-muted"><?php echo esc_html( $review->customer_email ); ?></span>
									<?php if ( (int) $review->verified ) : ?>
										<br /><span class="tg-badge tg-badge--success"><?php esc_html_e( 'Verified booking', 'guidegrid-travel' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo $tour ? '<a href="' . esc_url( get_edit_post_link( $tour->ID ) ) . '">' . esc_html( get_the_title( $tour ) ) . '</a>' : '#' . esc_html( (string) $review->tour_id ); ?></td>
								<td><?php echo esc_html( str_repeat( '★', (int) $review->rating ) ); ?></td>
								<td>
									<?php if ( $review->title ) : ?>
										<strong><?php echo esc_html( $review->title ); ?></strong><br />
									<?php endif; ?>
									<?php echo esc_html( wp_trim_words( (string) $review->content, 25 ) ); ?>
								</td>
								<td><?php echo esc_html( tg_format_date( $review->created_at ) ); ?></td>
								<td><?php echo tg_status_badge( $review->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td class="tg-row-actions">
									<?php if ( 'approved' !== $review->status ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=tg-reviews&tg_review_do=approve&tg_review_id=' . (int) $review->id ), 'tg_review_' . $review->id . '_approve' ) ); ?>"><?php esc_html_e( 'Approve', 'guidegrid-travel' ); ?></a> |
									<?php endif; ?>
									<?php if ( 'rejected' !== $review->status ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=tg-reviews&tg_review_do=reject&tg_review_id=' . (int) $review->id ), 'tg_review_' . $review->id . '_reject' ) ); ?>"><?php esc_html_e( 'Reject', 'guidegrid-travel' ); ?></a> |
									<?php endif; ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=tg-reviews&tg_review_do=delete&tg_review_id=' . (int) $review->id ), 'tg_review_' . $review->id . '_delete' ) ); ?>" class="tg-danger-link" onclick="return confirm('<?php echo esc_js( __( 'Delete this review?', 'guidegrid-travel' ) ); ?>');"><?php esc_html_e( 'Delete', 'guidegrid-travel' ); ?></a>
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
