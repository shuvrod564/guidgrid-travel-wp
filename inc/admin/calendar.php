<?php
/**
 * Availability calendar (admin).
 *
 * Shows, per day: which tours operate, capacity, reserved, available and
 * status. Status is always text + color (never color-only).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_render_calendar_page' ) ) {
	/**
	 * Render the calendar page.
	 *
	 * @return void
	 */
	function tg_render_calendar_page() {
		if ( ! current_user_can( 'manage_tg' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'guidegrid-travel' ) );
		}

		$month  = isset( $_GET['month'] ) && preg_match( '/^\d{4}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['month'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : gmdate( 'Y-m' );
		$ts     = strtotime( $month . '-01' );
		$first  = gmdate( 'Y-m-01', $ts );
		$last   = gmdate( 'Y-m-t', $ts );
		$offset = (int) gmdate( 'w', $ts ) - 1;
		$offset = ( $offset < 0 ) ? 7 + $offset : $offset;

		$tours = get_posts(
			array(
				'post_type'      => 'tour',
				'posts_per_page' => -1,
			)
		);

		$prev_month = gmdate( 'Y-m', strtotime( $month . '-01 -1 month' ) );
		$next_month = gmdate( 'Y-m', strtotime( $month . '-01 +1 month' ) );
		$today      = gmdate( 'Y-m-d', time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		?>
		<div class="wrap tg-admin">
			<h1><?php esc_html_e( 'Availability Calendar', 'guidegrid-travel' ); ?></h1>
			<p class="tg-cal-nav">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tg-calendar&month=' . $prev_month ) ); ?>">← <?php echo esc_html( gmdate( 'F Y', strtotime( $prev_month . '-01' ) ) ); ?></a>
				<strong style="margin:0 16px;"><?php echo esc_html( gmdate( 'F Y', $ts ) ); ?></strong>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tg-calendar&month=' . $next_month ) ); ?>"><?php echo esc_html( gmdate( 'F Y', strtotime( $next_month . '-01' ) ) ); ?> →</a>
			</p>

			<table class="widefat tg-calendar">
				<thead>
					<tr>
						<?php
						$days_week = array( __( 'Mon', 'guidegrid-travel' ), __( 'Tue', 'guidegrid-travel' ), __( 'Wed', 'guidegrid-travel' ), __( 'Thu', 'guidegrid-travel' ), __( 'Fri', 'guidegrid-travel' ), __( 'Sat', 'guidegrid-travel' ), __( 'Sun', 'guidegrid-travel' ) );
						foreach ( $days_week as $d ) :
							?>
							<th><?php echo esc_html( $d ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php
					$day_counter = 1 - $offset;
					for ( $week = 0; $week < 6; $week++ ) :
						echo '<tr>';
						for ( $dow = 0; $dow < 7; $dow++ ) :
							$date_str = gmdate( 'Y-m-d', strtotime( $first . ' +' . ( $day_counter - 1 ) . ' days' ) );
							$in_month = 0 === strpos( $date_str, $month );
							$day_counter++;
							?>
							<td class="<?php echo ( $in_month ? 'tg-cal-in' : 'tg-cal-out' ) . ( $date_str === $today ? ' tg-cal-today' : '' ); ?>">
								<?php if ( $in_month ) : ?>
									<strong><?php echo esc_html( (string) (int) gmdate( 'j', strtotime( $date_str ) ) ); ?></strong>
									<?php
									foreach ( $tours as $tour ) :
										$status = TG_Availability::date_status( $tour->ID, $date_str );
										if ( 'closed' === $status['status'] || 'past' === $status['status'] ) {
											continue;
										}
										$state_labels = array(
											'open'     => __( 'Available', 'guidegrid-travel' ),
											'full'     => __( 'Full', 'guidegrid-travel' ),
											'blackout' => __( 'Blackout', 'guidegrid-travel' ),
										);
										$cap_text = $status['capacity'] > 0
											? sprintf( /* translators: 1: reserved, 2: capacity */ __( 'res %1$d/%2$d', 'guidegrid-travel' ), $status['reserved'] + $status['hold'], $status['capacity'] )
											: __( 'unlimited', 'guidegrid-travel' );
										?>
										<div class="tg-cal-item tg-cal-<?php echo esc_attr( $status['status'] ); ?>" title="<?php echo esc_attr( $tour->post_title ); ?>">
											<a href="<?php echo esc_url( get_edit_post_link( $tour->ID ) ); ?>"><?php echo esc_html( wp_trim_words( $tour->post_title, 4 ) ); ?></a>
											<span class="tg-cal-status"><?php echo esc_html( $state_labels[ $status['status'] ] ?? $status['status'] ); ?> · <?php echo esc_html( $cap_text ); ?></span>
										</div>
										<?php
									endforeach;
									?>
								<?php endif; ?>
							</td>
						<?php endfor; ?>
						</tr>
					<?php
						if ( $day_counter - 8 > (int) gmdate( 't', $ts ) ) {
							break;
						}
					endfor;
					?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
