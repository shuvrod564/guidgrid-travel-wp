<?php
/**
 * Coupons admin (list + add/edit form + delete).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_render_coupons_page' ) ) {
	/**
	 * Render the coupons page.
	 *
	 * @return void
	 */
	function tg_render_coupons_page() {
		if ( ! current_user_can( 'manage_tg' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'guidegrid-travel' ) );
		}

		$notice = '';

		// Handle save/delete.
		if ( isset( $_POST['tg_coupon_nonce'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			if ( wp_verify_nonce( sanitize_key( $_POST['tg_coupon_nonce'] ), 'tg_coupon_save' ) ) {
				$action = isset( $_POST['tg_coupon_action'] ) ? sanitize_key( $_POST['tg_coupon_action'] ) : '';
				if ( 'delete' === $action && isset( $_POST['id'] ) ) {
					TG_Coupons::delete( absint( $_POST['id'] ) );
					$notice = __( 'Coupon deleted.', 'guidegrid-travel' );
				} elseif ( 'save' === $action && isset( $_POST['code'] ) ) {
					$result = TG_Coupons::save(
						array(
							'id'                    => isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0,
							'code'                  => sanitize_text_field( wp_unslash( $_POST['code'] ) ),
							'description'           => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
							'discount_type'         => isset( $_POST['discount_type'] ) ? sanitize_key( $_POST['discount_type'] ) : 'percent',
							'discount_value'        => isset( $_POST['discount_value'] ) ? (float) $_POST['discount_value'] : 0,
							'min_amount'            => isset( $_POST['min_amount'] ) ? (float) $_POST['min_amount'] : 0,
							'max_discount'          => isset( $_POST['max_discount'] ) ? (float) $_POST['max_discount'] : 0,
							'starts_at'             => isset( $_POST['starts_at'] ) ? sanitize_text_field( wp_unslash( $_POST['starts_at'] ) ) : '',
							'expires_at'            => isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '',
							'usage_limit'           => isset( $_POST['usage_limit'] ) ? absint( $_POST['usage_limit'] ) : 0,
							'per_customer_limit'    => isset( $_POST['per_customer_limit'] ) ? absint( $_POST['per_customer_limit'] ) : 0,
							'applicable_tours'      => isset( $_POST['applicable_tours'] ) ? sanitize_text_field( wp_unslash( $_POST['applicable_tours'] ) ) : '',
							'applicable_categories' => isset( $_POST['applicable_categories'] ) ? sanitize_text_field( wp_unslash( $_POST['applicable_categories'] ) ) : '',
							'applicable_destinations' => isset( $_POST['applicable_destinations'] ) ? sanitize_text_field( wp_unslash( $_POST['applicable_destinations'] ) ) : '',
							'active'                => isset( $_POST['active'] ),
						)
					);
					$notice = is_wp_error( $result ) ? $result->get_error_message() : __( 'Coupon saved.', 'guidegrid-travel' );
				}
			}
		}

		$editing = null;
		if ( isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$global  = $GLOBALS['wpdb'];
			$editing = $global->get_row( $global->prepare( 'SELECT * FROM ' . TG_Database::table( 'coupons' ) . ' WHERE id = %d', absint( $_GET['edit'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$list = TG_Coupons::list_coupons( array( 'per_page' => 50 ) );
		?>
		<div class="wrap tg-admin">
			<h1><?php esc_html_e( 'Coupons', 'guidegrid-travel' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice <?php echo ( false === strpos( $notice, 'saved.' ) && false === strpos( $notice, 'deleted.' ) ) ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<form method="post" class="tg-coupon-form">
				<?php wp_nonce_field( 'tg_coupon_save', 'tg_coupon_nonce' ); ?>
				<input type="hidden" name="tg_coupon_action" value="save" />
				<input type="hidden" name="id" value="<?php echo $editing ? esc_attr( (string) $editing->id ) : '0'; ?>" />
				<h2><?php echo $editing ? esc_html__( 'Edit Coupon', 'guidegrid-travel' ) : esc_html__( 'Add Coupon', 'guidegrid-travel' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="tg-c-code"><?php esc_html_e( 'Code', 'guidegrid-travel' ); ?></label></th>
						<td><input type="text" id="tg-c-code" name="code" class="regular-text" value="<?php echo $editing ? esc_attr( $editing->code ) : ''; ?>" required /></td>
					</tr>
					<tr>
						<th><label for="tg-c-desc"><?php esc_html_e( 'Description', 'guidegrid-travel' ); ?></label></th>
						<td><input type="text" id="tg-c-desc" name="description" class="regular-text" value="<?php echo $editing ? esc_attr( $editing->description ) : ''; ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Discount', 'guidegrid-travel' ); ?></th>
						<td>
							<select name="discount_type">
								<option value="percent" <?php selected( $editing ? $editing->discount_type : 'percent', 'percent' ); ?>><?php esc_html_e( 'Percentage (%)', 'guidegrid-travel' ); ?></option>
								<option value="fixed" <?php selected( $editing ? $editing->discount_type : '', 'fixed' ); ?>><?php esc_html_e( 'Fixed amount', 'guidegrid-travel' ); ?></option>
							</select>
							<input type="number" step="0.01" min="0" name="discount_value" value="<?php echo $editing ? esc_attr( (string) $editing->discount_value ) : '10'; ?>" style="width:100px;margin-left:8px;" />
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Limits', 'guidegrid-travel' ); ?></th>
						<td>
							<?php esc_html_e( 'Min booking amount:', 'guidegrid-travel' ); ?> <input type="number" step="0.01" min="0" name="min_amount" value="<?php echo $editing ? esc_attr( (string) $editing->min_amount ) : '0'; ?>" style="width:100px;" />
							<?php esc_html_e( 'Max discount:', 'guidegrid-travel' ); ?> <input type="number" step="0.01" min="0" name="max_discount" value="<?php echo $editing ? esc_attr( (string) $editing->max_discount ) : '0'; ?>" style="width:100px;" />
							<?php esc_html_e( 'Usage limit:', 'guidegrid-travel' ); ?> <input type="number" min="0" name="usage_limit" value="<?php echo $editing ? esc_attr( (string) $editing->usage_limit ) : '0'; ?>" style="width:80px;" />
							<?php esc_html_e( 'Per customer:', 'guidegrid-travel' ); ?> <input type="number" min="0" name="per_customer_limit" value="<?php echo $editing ? esc_attr( (string) $editing->per_customer_limit ) : '0'; ?>" style="width:80px;" />
							<p class="description"><?php esc_html_e( '0 = unlimited.', 'guidegrid-travel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Validity', 'guidegrid-travel' ); ?></th>
						<td>
							<?php esc_html_e( 'Starts:', 'guidegrid-travel' ); ?> <input type="date" name="starts_at" value="<?php echo $editing ? esc_attr( (string) $editing->starts_at ) : ''; ?>" />
							<?php esc_html_e( 'Expires:', 'guidegrid-travel' ); ?> <input type="date" name="expires_at" value="<?php echo $editing ? esc_attr( (string) $editing->expires_at ) : ''; ?>" />
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Eligibility (IDs, comma separated; empty = all)', 'guidegrid-travel' ); ?></th>
						<td>
							<p><?php esc_html_e( 'Tours:', 'guidegrid-travel' ); ?> <input type="text" name="applicable_tours" class="regular-text" value="<?php echo $editing ? esc_attr( (string) $editing->applicable_tours ) : ''; ?>" /></p>
							<p><?php esc_html_e( 'Category IDs:', 'guidegrid-travel' ); ?> <input type="text" name="applicable_categories" class="regular-text" value="<?php echo $editing ? esc_attr( (string) $editing->applicable_categories ) : ''; ?>" /></p>
							<p><?php esc_html_e( 'Destination IDs:', 'guidegrid-travel' ); ?> <input type="text" name="applicable_destinations" class="regular-text" value="<?php echo $editing ? esc_attr( (string) $editing->applicable_destinations ) : ''; ?>" /></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status', 'guidegrid-travel' ); ?></th>
						<td><label><input type="checkbox" name="active" value="1" <?php checked( $editing ? (bool) $editing->active : true ); ?> /> <?php esc_html_e( 'Active', 'guidegrid-travel' ); ?></label></td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Coupon', 'guidegrid-travel' ); ?></button>
					<?php if ( $editing ) : ?>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tg-coupons' ) ); ?>"><?php esc_html_e( 'Cancel', 'guidegrid-travel' ); ?></a>
					<?php endif; ?>
				</p>
			</form>

			<h2><?php esc_html_e( 'All Coupons', 'guidegrid-travel' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Code', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Discount', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Valid', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Usage', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Status', 'guidegrid-travel' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'guidegrid-travel' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $list['items'] ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No coupons yet.', 'guidegrid-travel' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $list['items'] as $coupon ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $coupon->code ); ?></strong></td>
								<td>
									<?php
									if ( 'fixed' === $coupon->discount_type ) {
										echo esc_html( tg_format_price( (float) $coupon->discount_value ) );
									} else {
										echo esc_html( $coupon->discount_value . '%' );
									}
									?>
								</td>
								<td><?php echo esc_html( ( $coupon->starts_at ? tg_format_date( $coupon->starts_at ) : '—' ) . ' → ' . ( $coupon->expires_at ? tg_format_date( $coupon->expires_at ) : '—' ) ); ?></td>
								<td><?php echo esc_html( (string) $coupon->usage_count ); ?><?php echo (int) $coupon->usage_limit ? ' / ' . esc_html( (string) $coupon->usage_limit ) : ''; ?></td>
								<td><?php echo $coupon->active ? '<span class="tg-badge tg-badge--success">' . esc_html__( 'Active', 'guidegrid-travel' ) . '</span>' : '<span class="tg-badge tg-badge--neutral">' . esc_html__( 'Inactive', 'guidegrid-travel' ) . '</span>'; ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=tg-coupons&edit=' . (int) $coupon->id ) ); ?>"><?php esc_html_e( 'Edit', 'guidegrid-travel' ); ?></a>
									|
									<form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this coupon?', 'guidegrid-travel' ) ); ?>');">
										<?php wp_nonce_field( 'tg_coupon_save', 'tg_coupon_nonce' ); ?>
										<input type="hidden" name="tg_coupon_action" value="delete" />
										<input type="hidden" name="id" value="<?php echo esc_attr( (string) $coupon->id ); ?>" />
										<button type="submit" class="button-link tg-danger-link"><?php esc_html_e( 'Delete', 'guidegrid-travel' ); ?></button>
									</form>
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
