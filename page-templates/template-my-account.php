<?php
/**
 * Template Name: My Account
 *
 * Tabs: Dashboard / My Bookings / Wishlist / Profile.
 * All booking data shown is strictly scoped to the logged-in user
 * (or their guest-checkout email).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! is_user_logged_in() ) {
	$redirect = (string) ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : home_url( '/' ) );
	wp_redirect( wp_login_url( $redirect ) );
	exit;
}

$acc_user   = wp_get_current_user();
$acc_tab    = isset( $_GET['tg_tab'] ) ? sanitize_key( wp_unslash( $_GET['tg_tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$acc_tabs   = array(
	'dashboard' => array(
		'label' => __( 'Dashboard', 'guidegrid-travel' ),
		'icon'  => 'compass',
	),
	'bookings'  => array(
		'label' => __( 'My Bookings', 'guidegrid-travel' ),
		'icon'  => 'ticket',
	),
	'wishlist'  => array(
		'label' => __( 'Wishlist', 'guidegrid-travel' ),
		'icon'  => 'heart',
	),
	'profile'   => array(
		'label' => __( 'Profile', 'guidegrid-travel' ),
		'icon'  => 'user',
	),
);
if ( ! isset( $acc_tabs[ $acc_tab ] ) ) {
	$acc_tab = 'dashboard';
}

$acc_bookings = TG_Bookings::get_for_user( (int) $acc_user->ID, $acc_user->user_email );
$acc_wish_ids = TG_Wishlist::get( (int) $acc_user->ID );

$acc_stats = array(
	'total'    => count( $acc_bookings ),
	'upcoming' => 0,
	'completed' => 0,
);
foreach ( $acc_bookings as $b ) {
	if ( in_array( $b->booking_status, array( 'confirmed', 'paid', 'partially_paid', 'on_hold', 'pending', 'awaiting_payment' ), true ) ) {
		$acc_stats['upcoming']++;
	}
	if ( 'completed' === $b->booking_status ) {
		$acc_stats['completed']++;
	}
}
?>
<section class="tg-section" style="padding-top:24px;">
	<div class="tg-container">
		<?php tg_breadcrumbs(); ?>
		<div class="tg-account-layout">
			<nav class="tg-account-nav" aria-label="<?php esc_attr_e( 'Account navigation', 'guidegrid-travel' ); ?>">
				<ul>
					<?php foreach ( $acc_tabs as $acc_key => $acc_def ) : ?>
						<li>
							<a href="<?php echo esc_url( tg_account_url( $acc_key ) ); ?>" class="<?php echo $acc_tab === $acc_key ? 'is-active' : ''; ?>" <?php echo $acc_tab === $acc_key ? 'aria-current="page"' : ''; ?>>
								<?php echo tg_svg( $acc_def['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php echo esc_html( $acc_def['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
					<li><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php echo tg_svg( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Log Out', 'guidegrid-travel' ); ?></a></li>
				</ul>
			</nav>

			<div>
				<?php if ( 'dashboard' === $acc_tab ) : ?>
					<div class="tg-account-panel">
						<h2><?php echo esc_html( sprintf( /* translators: %s: user name */ __( 'Welcome back, %s', 'guidegrid-travel' ), $acc_user->display_name ) ); ?></h2>
						<div class="tg-stat-cards">
							<div class="tg-stat-card"><span class="tg-stat-num"><?php echo esc_html( (string) $acc_stats['total'] ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Total Bookings', 'guidegrid-travel' ); ?></span></div>
							<div class="tg-stat-card"><span class="tg-stat-num"><?php echo esc_html( (string) $acc_stats['upcoming'] ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Upcoming', 'guidegrid-travel' ); ?></span></div>
							<div class="tg-stat-card"><span class="tg-stat-num"><?php echo esc_html( (string) $acc_stats['completed'] ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Completed', 'guidegrid-travel' ); ?></span></div>
							<div class="tg-stat-card"><span class="tg-stat-num"><?php echo esc_html( (string) count( $acc_wish_ids ) ); ?></span><span class="tg-stat-label"><?php esc_html_e( 'Wishlist', 'guidegrid-travel' ); ?></span></div>
						</div>
						<h3><?php esc_html_e( 'Latest bookings', 'guidegrid-travel' ); ?></h3>
						<?php
						$recent = array_slice( $acc_bookings, 0, 3 );
						if ( empty( $recent ) ) :
							?>
							<p><?php esc_html_e( 'No bookings yet — your trips will appear here.', 'guidegrid-travel' ); ?></p>
							<a class="tg-btn tg-btn--primary" href="<?php echo esc_url( get_post_type_archive_link( 'tour' ) ? get_post_type_archive_link( 'tour' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Browse tours', 'guidegrid-travel' ); ?></a>
						<?php else : ?>
							<?php
							foreach ( $recent as $b ) :
								$t = get_post( (int) $b->tour_id );
								?>
								<p style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;border-bottom:1px solid var(--tg-border);padding:10px 0;">
									<span><strong class="tg-cell-num"><?php echo esc_html( $b->booking_number ); ?></strong> — <?php echo $t ? esc_html( get_the_title( $t ) ) : ''; ?></span>
									<span>
										<?php echo tg_status_badge( $b->booking_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php echo esc_html( tg_format_date( $b->booking_date ) ); ?>
									</span>
								</p>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

				<?php elseif ( 'bookings' === $acc_tab ) : ?>
					<div class="tg-account-panel">
						<h2><?php esc_html_e( 'My Bookings', 'guidegrid-travel' ); ?></h2>
						<?php if ( empty( $acc_bookings ) ) : ?>
							<?php
							tg_empty_state(
								'ticket',
								__( 'No bookings yet', 'guidegrid-travel' ),
								__( 'When you book a tour, it will show up here with status and payment details.', 'guidegrid-travel' ),
								get_post_type_archive_link( 'tour' ),
								__( 'Find a tour', 'guidegrid-travel' )
							);
							?>
						<?php else : ?>
							<div class="tg-booking-table-wrap">
								<table class="tg-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Booking #', 'guidegrid-travel' ); ?></th>
											<th><?php esc_html_e( 'Tour', 'guidegrid-travel' ); ?></th>
											<th><?php esc_html_e( 'Date', 'guidegrid-travel' ); ?></th>
											<th><?php esc_html_e( 'Total', 'guidegrid-travel' ); ?></th>
											<th><?php esc_html_e( 'Status', 'guidegrid-travel' ); ?></th>
											<th><?php esc_html_e( '', 'guidegrid-travel' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $acc_bookings as $b ) : ?>
											<?php $t = get_post( (int) $b->tour_id ); ?>
											<tr>
												<td class="tg-cell-num"><?php echo esc_html( $b->booking_number ); ?></td>
												<td class="tg-cell-title"><?php echo $t ? '<a href="' . esc_url( get_permalink( $t ) ) . '">' . esc_html( get_the_title( $t ) ) . '</a>' : esc_html( '#' . (int) $b->tour_id ); ?></td>
												<td><?php echo esc_html( tg_format_date( $b->booking_date ) ); ?></td>
												<td><?php echo esc_html( tg_format_price( (float) $b->total, $b->currency ) ); ?></td>
												<td>
													<?php echo tg_status_badge( $b->booking_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br />
													<?php echo tg_status_badge( $b->payment_status, 'payment' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												</td>
												<td><a class="tg-btn tg-btn--ghost tg-btn--sm" href="<?php echo esc_url( tg_confirmation_page_url( $b->booking_number ) ); ?>"><?php esc_html_e( 'View', 'guidegrid-travel' ); ?></a></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>

				<?php elseif ( 'wishlist' === $acc_tab ) : ?>
					<div class="tg-account-panel">
						<h2><?php esc_html_e( 'My Wishlist', 'guidegrid-travel' ); ?></h2>
						<?php
						$wish_posts = array();
						foreach ( $acc_wish_ids as $wid ) {
							$p = get_post( $wid );
							if ( $p && 'tour' === $p->post_type && 'publish' === $p->post_status ) {
								$wish_posts[] = $p;
							}
						}
						if ( empty( $wish_posts ) ) :
							?>
							<?php
							tg_empty_state(
								'heart',
								__( 'Your wishlist is empty', 'guidegrid-travel' ),
								__( 'Tap the heart on any tour to save it here for later.', 'guidegrid-travel' ),
								get_post_type_archive_link( 'tour' ),
								__( 'Browse tours', 'guidegrid-travel' )
							);
							?>
						<?php else : ?>
							<div class="tg-card-grid">
								<?php foreach ( $wish_posts as $wp_post ) : ?>
									<?php tg_tour_card( $wp_post->ID ); ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

				<?php elseif ( 'profile' === $acc_tab ) : ?>
					<div class="tg-account-panel">
						<h2><?php esc_html_e( 'Profile', 'guidegrid-travel' ); ?></h2>
						<p class="tg-notice tg-notice--info"><?php echo esc_html( sprintf( /* translators: %s: email */ __( 'Account email: %s (change it in WordPress user settings).', 'guidegrid-travel' ), $acc_user->user_email ) ); ?></p>
						<form method="post" data-tg-ajax-form data-action="tg_save_profile">
							<div class="tg-form-grid">
								<div class="tg-field tg-form-row">
									<label class="tg-label" for="tg-pr-phone"><?php esc_html_e( 'Phone', 'guidegrid-travel' ); ?></label>
									<input type="tel" id="tg-pr-phone" name="phone" class="tg-input" value="<?php echo esc_attr( get_user_meta( $acc_user->ID, '_tg_phone', true ) ); ?>" autocomplete="tel" />
								</div>
								<div class="tg-field tg-form-row">
									<label class="tg-label" for="tg-pr-country"><?php esc_html_e( 'Country', 'guidegrid-travel' ); ?></label>
									<input type="text" id="tg-pr-country" name="country" class="tg-input" value="<?php echo esc_attr( get_user_meta( $acc_user->ID, '_tg_country', true ) ); ?>" />
								</div>
							</div>
							<div class="tg-field tg-form-row">
								<label class="tg-label" for="tg-pr-address"><?php esc_html_e( 'Address', 'guidegrid-travel' ); ?></label>
								<textarea id="tg-pr-address" name="address" class="tg-textarea" rows="2"><?php echo esc_textarea( get_user_meta( $acc_user->ID, '_tg_address', true ) ); ?></textarea>
							</div>
							<button type="submit" class="tg-btn tg-btn--primary"><?php esc_html_e( 'Save Profile', 'guidegrid-travel' ); ?><span class="tg-spinner" aria-hidden="true"></span></button>
						</form>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
<?php
get_footer();
