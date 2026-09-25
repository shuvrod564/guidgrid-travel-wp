<?php
/**
 * Site header.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

$settings  = tg_settings();
$wishlist  = tg_wishlist_state();
$cta_url   = $settings['header_cta_url'] ? $settings['header_cta_url'] : ( get_post_type_archive_link( 'tour' ) ? get_post_type_archive_link( 'tour' ) : home_url( '/' ) );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="description" content="<?php echo esc_attr( wp_strip_all_tags( is_singular() ? get_the_excerpt() : get_bloginfo( 'description' ) ) ); ?>" />
	<link rel="profile" href="https://gmpg.org/xfn/11" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#tg-main"><?php esc_html_e( 'Skip to content', 'guidegrid-travel' ); ?></a>

<header class="tg-site-header" id="tg-header">
	<div class="tg-container">
		<?php if ( has_custom_logo() ) : ?>
			<?php the_custom_logo(); ?>
		<?php else : ?>
			<a class="tg-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<span class="tg-logo-text">Guide<span>Grid</span></span>
			</a>
		<?php endif; ?>

		<nav class="tg-main-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'guidegrid-travel' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'fallback_cb'    => 'tg_default_menu',
					'depth'          => 1,
				)
			);
			?>
		</nav>

		<span class="tg-header-actions">
			<?php if ( $settings['show_header_search'] ) : ?>
				<button type="button" class="tg-icon-btn" data-tg-toggle-search aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle search', 'guidegrid-travel' ); ?>">
					<?php echo tg_lucide( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
			<?php endif; ?>
			<?php if ( $settings['show_header_wishlist'] ) : ?>
				<a class="tg-icon-btn" href="<?php echo esc_url( tg_account_url( 'wishlist' ) ); ?>" aria-label="<?php esc_attr_e( 'Wishlist', 'guidegrid-travel' ); ?>">
					<?php echo tg_lucide( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php 
					$count = isset( $wishlist['count'] ) ? (int) $wishlist['count'] : 0;
					if ( $count > 0 ) : 
					?>
						<span class="tg-count" data-tg-wishlist-count><?php echo esc_html( (string) $count ); ?></span>
					<?php endif; ?>
				</a>
			<?php endif; ?>
			<?php if ( $settings['show_header_account'] ) : ?>
				<?php if ( is_user_logged_in() ) : ?>
					<a class="tg-icon-btn" href="<?php echo esc_url( tg_account_url() ); ?>" aria-label="<?php esc_attr_e( 'My account', 'guidegrid-travel' ); ?>">
						<?php echo tg_lucide( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				<?php else : ?>
					<span class="tg-header-auth">
						<a class="tg-btn tg-btn--ghost tg-btn--sm" href="<?php echo esc_url( tg_auth_url( '', 'login' ) ); ?>"><?php esc_html_e( 'Log In', 'guidegrid-travel' ); ?></a>
						<a style="display:none;" class="tg-btn tg-btn--secondary tg-btn--sm tg-header-signup" href="<?php echo esc_url( tg_auth_url( '', 'register' ) ); ?>"><?php esc_html_e( 'Create Account', 'guidegrid-travel' ); ?></a>
					</span>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $settings['show_header_cta'] ) : ?>
				<span class="tg-cta-wrap">
					<a class="tg-btn tg-btn--primary tg-btn--sm tg-btn--header-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $settings['header_cta_text'] ); ?></a>
				</span>
			<?php endif; ?>
			<button type="button" class="tg-icon-btn tg-header-toggle" data-tg-toggle-nav aria-expanded="false" aria-controls="tg-mobile-nav" aria-label="<?php esc_attr_e( 'Open menu', 'guidegrid-travel' ); ?>">
				<?php echo tg_lucide( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
		</span>
	</div>

	<?php if ( $settings['show_header_search'] ) : ?>
		<div class="tg-search-panel" id="tg-search-panel" role="search">
			<div class="tg-container">
				<form role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
					<label class="screen-reader-text" for="tg-header-search"><?php esc_html_e( 'Search', 'guidegrid-travel' ); ?></label>
					<input type="search" id="tg-header-search" class="tg-input" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search tours, destinations, guides…', 'guidegrid-travel' ); ?>" />
					<button type="submit" class="tg-btn tg-btn--primary"><?php esc_html_e( 'Search', 'guidegrid-travel' ); ?></button>
				</form>
			</div>
		</div>
	<?php endif; ?>
</header>

<div class="tg-mobile-nav" id="tg-mobile-nav">
	<button type="button" class="tg-icon-btn tg-mobile-nav-close" data-tg-close-nav aria-label="<?php esc_attr_e( 'Close menu', 'guidegrid-travel' ); ?>">
		<?php echo tg_svg( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</button>
	<nav aria-label="<?php esc_attr_e( 'Mobile navigation', 'guidegrid-travel' ); ?>">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'fallback_cb'    => 'tg_default_menu',
				'depth'          => 2,
			)
		);
		?>
	</nav>
</div>
<div class="tg-nav-backdrop" data-tg-close-nav></div>

<main id="tg-main" class="tg-main">
