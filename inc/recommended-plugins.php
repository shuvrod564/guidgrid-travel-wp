<?php
/**
 * Recommended plugin notices.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'TG_FLUENT_SMTP_SLUG' ) ) {
	define( 'TG_FLUENT_SMTP_SLUG', 'fluent-smtp' );
}

if ( ! defined( 'TG_FLUENT_SMTP_FILE' ) ) {
	define( 'TG_FLUENT_SMTP_FILE', 'fluent-smtp/fluent-smtp.php' );
}

if ( ! defined( 'TG_FLUENT_SMTP_DISMISSED_META' ) ) {
	define(
		'TG_FLUENT_SMTP_DISMISSED_META',
		'tg_fluent_smtp_notice_dismissed'
	);
}

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

	return is_multisite()
		&& function_exists( 'is_plugin_active_for_network' )
		&& is_plugin_active_for_network( TG_FLUENT_SMTP_FILE );
}

/**
 * Reset the recommendation for the administrator who activates the theme.
 *
 * @return void
 */
function tg_reset_fluent_smtp_recommendation(): void {
	$user_id = get_current_user_id();

	if ( $user_id ) {
		delete_user_meta(
			$user_id,
			TG_FLUENT_SMTP_DISMISSED_META
		);
	}
}
add_action(
	'after_switch_theme',
	'tg_reset_fluent_smtp_recommendation',
	20
);

/**
 * Process the Dismiss action.
 *
 * @return void
 */
function tg_dismiss_fluent_smtp_recommendation(): void {
	if ( ! isset( $_GET['tg_dismiss_fluent_smtp'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die(
			esc_html__(
				'You do not have permission to dismiss this notice.',
				'guidegrid-travel'
			),
			esc_html__( 'Forbidden', 'guidegrid-travel' ),
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'tg_dismiss_fluent_smtp' );

	update_user_meta(
		get_current_user_id(),
		TG_FLUENT_SMTP_DISMISSED_META,
		1
	);

	wp_safe_redirect( admin_url( 'index.php' ) );
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
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( tg_is_fluent_smtp_active() ) {
		return;
	}

	$dismissed = get_user_meta(
		get_current_user_id(),
		TG_FLUENT_SMTP_DISMISSED_META,
		true
	);

	if ( $dismissed ) {
		return;
	}

	$plugin_installed = file_exists(
		WP_PLUGIN_DIR . '/' . TG_FLUENT_SMTP_FILE
	);

	$action_url   = '';
	$action_label = '';

	if ( version_compare( get_bloginfo( 'version' ), '6.5', '<' ) ) {
		if ( current_user_can( 'update_core' ) ) {
			$action_url   = admin_url( 'update-core.php' );
			$action_label = __(
				'Update WordPress',
				'guidegrid-travel'
			);
		}
	} elseif (
		$plugin_installed &&
		current_user_can( 'activate_plugins' )
	) {
		$action_url = wp_nonce_url(
			self_admin_url(
				'plugins.php?action=activate&plugin=' .
				rawurlencode( TG_FLUENT_SMTP_FILE )
			),
			'activate-plugin_' . TG_FLUENT_SMTP_FILE
		);

		$action_label = __(
			'Activate FluentSMTP',
			'guidegrid-travel'
		);
	} elseif ( current_user_can( 'install_plugins' ) ) {
		$action_url = wp_nonce_url(
			self_admin_url(
				'update.php?action=install-plugin&plugin=' .
				TG_FLUENT_SMTP_SLUG
			),
			'install-plugin_' . TG_FLUENT_SMTP_SLUG
		);

		$action_label = __(
			'Install FluentSMTP',
			'guidegrid-travel'
		);
	}

	$dismiss_url = wp_nonce_url(
		add_query_arg(
			'tg_dismiss_fluent_smtp',
			'1',
			admin_url( 'index.php' )
		),
		'tg_dismiss_fluent_smtp'
	);

	?>
	<div class="notice notice-info">
		<p>
			<strong>
				<?php
				esc_html_e(
					'Recommended plugin: FluentSMTP',
					'guidegrid-travel'
				);
				?>
			</strong>
		</p>

		<p>
			<?php
			esc_html_e(
				'GuideGrid Travel uses WordPress email for contact forms, enquiries, bookings and payment notifications. Install FluentSMTP to connect WordPress to a reliable email provider.',
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
				<?php
				esc_html_e(
					'Dismiss',
					'guidegrid-travel'
				);
				?>
			</a>
		</p>
	</div>
	<?php
}
add_action(
	'admin_notices',
	'tg_fluent_smtp_recommendation_notice'
);

add_action(
	'network_admin_notices',
	'tg_fluent_smtp_recommendation_notice'
);