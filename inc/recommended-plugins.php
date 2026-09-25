<?php
/**
 * Recommended plugin notices.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * FluentSMTP plugin details.
 */
const TG_FLUENT_SMTP_SLUG = 'fluent-smtp';
const TG_FLUENT_SMTP_FILE = 'fluent-smtp/fluent-smtp.php';
const TG_FLUENT_SMTP_NOTICE = 'tg_recommend_fluent_smtp';

/**
 * Queue the recommendation after the theme is activated.
 *
 * @return void
 */
function tg_recommend_plugins_after_theme_activation(): void {
	set_transient(
		TG_FLUENT_SMTP_NOTICE,
		1,
		30 * DAY_IN_SECONDS
	); 
}
add_action(
	'after_switch_theme',
	'tg_recommend_plugins_after_theme_activation',
	20
);

/**
 * Determine whether FluentSMTP is active.
 *
 * @return bool
 */
function tg_is_fluent_smtp_active(): bool {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( is_plugin_active( TG_FLUENT_SMTP_FILE ) ) {
		return true;
	}

	return is_multisite() &&
		function_exists( 'is_plugin_active_for_network' ) &&
		is_plugin_active_for_network( TG_FLUENT_SMTP_FILE );
}

/**
 * Dismiss the recommendation.
 *
 * @return void
 */
function tg_dismiss_fluent_smtp_recommendation(): void {
	if ( ! isset( $_GET['tg_dismiss_fluent_smtp'] ) ) {
		return;
	}

	if (
		! current_user_can( 'install_plugins' ) &&
		! current_user_can( 'activate_plugins' )
	) {
		wp_die(
			esc_html__( 'You do not have permission to dismiss this notice.', 'guidegrid-travel' ),
			esc_html__( 'Forbidden', 'guidegrid-travel' ),
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'tg_dismiss_fluent_smtp' );

	delete_transient( TG_FLUENT_SMTP_NOTICE );

	wp_safe_redirect( admin_url() );
	exit;
}
add_action(
	'admin_init',
	'tg_dismiss_fluent_smtp_recommendation'
);

/**
 * Display the FluentSMTP recommendation.
 *
 * @return void
 */
function tg_fluent_smtp_recommendation_notice(): void {
	if ( ! get_transient( TG_FLUENT_SMTP_NOTICE ) ) {
		return;
	}

	if (
		! current_user_can( 'install_plugins' ) &&
		! current_user_can( 'activate_plugins' )
	) {
		return;
	}

	if ( tg_is_fluent_smtp_active() ) {
		delete_transient( TG_FLUENT_SMTP_NOTICE );
		return;
	}

	$plugin_installed = file_exists(
		WP_PLUGIN_DIR . '/' . TG_FLUENT_SMTP_FILE
	);

	$action_url   = '';
	$action_label = '';

	if ( version_compare( get_bloginfo( 'version' ), '6.5', '<' ) ) {
		$action_url   = admin_url( 'update-core.php' );
		$action_label = __( 'Update WordPress', 'guidegrid-travel' );
	} elseif ( $plugin_installed && current_user_can( 'activate_plugins' ) ) {
		$action_url = wp_nonce_url(
			self_admin_url(
				'plugins.php?action=activate&plugin=' .
				rawurlencode( TG_FLUENT_SMTP_FILE )
			),
			'activate-plugin_' . TG_FLUENT_SMTP_FILE
		);

		$action_label = __( 'Activate FluentSMTP', 'guidegrid-travel' );
	} elseif ( current_user_can( 'install_plugins' ) ) {
		$action_url = wp_nonce_url(
			self_admin_url(
				'update.php?action=install-plugin&plugin=' .
				TG_FLUENT_SMTP_SLUG
			),
			'install-plugin_' . TG_FLUENT_SMTP_SLUG
		);

		$action_label = __( 'Install FluentSMTP', 'guidegrid-travel' );
	}

	$dismiss_url = wp_nonce_url(
		add_query_arg(
			'tg_dismiss_fluent_smtp',
			'1',
			admin_url()
		),
		'tg_dismiss_fluent_smtp'
	);

	?>
	<div class="notice notice-info">
		<p>
			<strong>
				<?php esc_html_e( 'Recommended: Configure reliable email delivery', 'guidegrid-travel' ); ?>
			</strong>
		</p>

		<p>
			<?php
			esc_html_e(
				'GuideGrid Travel sends contact, enquiry, booking, payment and account emails through WordPress. We recommend FluentSMTP to connect WordPress to an authenticated email provider.',
				'guidegrid-travel'
			);
			?>
		</p>

		<p>
			<?php if ( $action_url && $action_label ) : ?>
				<a
					class="button button-primary"
					href="<?php echo esc_url( $action_url ); ?>"
				>
					<?php echo esc_html( $action_label ); ?>
				</a>
			<?php endif; ?>

			<a
				class="button button-secondary"
				href="<?php echo esc_url( $dismiss_url ); ?>"
			>
				<?php esc_html_e( 'Dismiss', 'guidegrid-travel' ); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action(
	'admin_notices',
	'tg_fluent_smtp_recommendation_notice'
);

 