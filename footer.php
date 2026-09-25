<?php
/**
 * Site footer.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

$settings       = tg_settings();
$footer_logo_id = absint( $settings['footer_logo_id'] ?? 0 );
?>
</main><!-- #tg-main -->

<footer class="tg-site-footer">
	<div class="tg-container">
		<div class="tg-footer-grid">
			<div class="tg-footer-about">
				<span class="tg-footer-logo">
					<?php if ( $footer_logo_id && wp_attachment_is_image( $footer_logo_id ) ) : ?>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
							<?php
							echo wp_get_attachment_image(
								$footer_logo_id,
								'full',
								false,
								array(
									'class'   => 'tg-footer-logo-image',
									'alt'     => get_bloginfo( 'name' ),
									'loading' => 'lazy',
								)
							); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core attachment markup.
							?>
						</a>
					<?php elseif ( has_custom_logo() ) : ?>
						<?php the_custom_logo(); ?>
					<?php else : ?>
						Guide<span>Grid</span>
					<?php endif; ?>
				</span>
				<p><?php echo esc_html( $settings['footer_about'] ? $settings['footer_about'] : get_bloginfo( 'description' ) ); ?></p>
				<div class="tg-footer-social">
					<?php if ( $settings['social_facebook'] ) : ?>
						<a href="<?php echo esc_url( $settings['social_facebook'] ); ?>" target="_blank" rel="noopener" aria-label="Facebook">f</a>
					<?php endif; ?>
					<?php if ( $settings['social_instagram'] ) : ?>
						<a href="<?php echo esc_url( $settings['social_instagram'] ); ?>" target="_blank" rel="noopener" aria-label="Instagram">IG</a>
					<?php endif; ?>
					<?php if ( $settings['social_x'] ) : ?>
						<a href="<?php echo esc_url( $settings['social_x'] ); ?>" target="_blank" rel="noopener" aria-label="X">X</a>
					<?php endif; ?>
					<?php if ( $settings['social_youtube'] ) : ?>
						<a href="<?php echo esc_url( $settings['social_youtube'] ); ?>" target="_blank" rel="noopener" aria-label="YouTube">▶</a>
					<?php endif; ?>
				</div>
			</div>

			<div>
				<h4><?php esc_html_e( 'Explore', 'guidegrid-travel' ); ?></h4>
				<?php
				if ( has_nav_menu( 'footer' ) ) {
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'container'      => false,
							'menu_class'     => 'tg-footer-list',
							'depth'          => 1,
						)
					);
				} else {
					echo '<ul class="tg-footer-list">';
					foreach (
						array(
							__( 'Tours', 'guidegrid-travel' )          => get_post_type_archive_link( 'tour' ),
							__( 'Destinations', 'guidegrid-travel' )   => get_post_type_archive_link( 'destination' ),
							__( 'Travel Guides', 'guidegrid-travel' )  => get_post_type_archive_link( 'travel_guide' ),
							__( 'About', 'guidegrid-travel' )          => get_permalink( tg_page_id_by_path( 'about' ) ),
							__( 'Contact', 'guidegrid-travel' )        => get_permalink( tg_page_id_by_path( 'contact' ) ),
						) as $label => $url
					) {
						if ( $url ) {
							echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
						}
					}
					echo '</ul>';
				}
				?>
			</div>

			<div>
				<h4><?php esc_html_e( 'Top Destinations', 'guidegrid-travel' ); ?></h4>
				<ul class="tg-footer-list">
					<?php
					$footer_dests = get_posts(
						array(
							'post_type'      => 'destination',
							'posts_per_page' => 5,
							'orderby'        => 'title',
							'order'          => 'ASC',
						)
					);
					foreach ( $footer_dests as $dest ) :
						?>
						<li><a href="<?php echo esc_url( get_permalink( $dest ) ); ?>"><?php echo esc_html( get_the_title( $dest ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="tg-footer-newsletter">
				<h4><?php esc_html_e( 'Get Travel Ideas', 'guidegrid-travel' ); ?></h4>
				<p><?php esc_html_e( 'Monthly deals, new tours and packing tips. No spam.', 'guidegrid-travel' ); ?></p>
				<form data-tg-ajax-form data-action="tg_subscribe_newsletter" novalidate>
					<label class="screen-reader-text" for="tg-footer-newsletter"><?php esc_html_e( 'Email address', 'guidegrid-travel' ); ?></label>
					<input type="email" id="tg-footer-newsletter" name="email" class="tg-input" placeholder="<?php esc_attr_e( 'Email address', 'guidegrid-travel' ); ?>" required autocomplete="email" />
					<p class="screen-reader-text" aria-hidden="true">
						<label for="tg-newsletter-website"><?php esc_html_e( 'Leave this field empty', 'guidegrid-travel' ); ?></label>
						<input type="text" id="tg-newsletter-website" name="tg_newsletter_website" value="" tabindex="-1" autocomplete="off" />
					</p>
					<button type="submit" class="tg-btn tg-btn--accent"><?php esc_html_e( 'Join', 'guidegrid-travel' ); ?></button>
				</form>
				<ul class="tg-footer-list tg-footer-contact" style="margin-top:18px;">
					<?php if ( $settings['contact_address'] ) : ?>
						<li><?php echo tg_svg( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php echo esc_html( $settings['contact_address'] ); ?></span></li>
					<?php endif; ?>
					<?php if ( $settings['contact_phone'] ) : ?>
						<li><?php echo tg_svg( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $settings['contact_phone'] ) ); ?>"><?php echo esc_html( $settings['contact_phone'] ); ?></a></li>
					<?php endif; ?>
					<?php if ( $settings['contact_email'] ) : ?>
						<li><?php echo tg_svg( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="mailto:<?php echo esc_attr( $settings['contact_email'] ); ?>"><?php echo esc_html( $settings['contact_email'] ); ?></a></li>
					<?php endif; ?>
				</ul>
			</div>
		</div>

		<div class="tg-footer-legal">
			<span>
				<?php
				echo esc_html(
					$settings['footer_copyright'] ? $settings['footer_copyright'] : sprintf(
						/* translators: 1: year, 2: site name */
						__( '© %1$s %2$s. All rights reserved.', 'guidegrid-travel' ),
						gmdate( 'Y' ),
						get_bloginfo( 'name' )
					)
				);
				?>
			</span>
			<ul>
				<?php foreach ( array( 'terms', 'privacy', 'cookie-policy', 'cancellation-refund-policy' ) as $legal_slug ) : ?>
					<?php
					$legal_id = tg_page_id_by_path( $legal_slug );
					if ( $legal_id ) :
						?>
						<li><a href="<?php echo esc_url( get_permalink( $legal_id ) ); ?>"><?php echo esc_html( get_the_title( $legal_id ) ); ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</footer>

<div class="tg-toast-region" data-tg-toast-region aria-live="polite" aria-atomic="true"></div>
<div class="tg-lightbox" data-tg-lightbox role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Image viewer', 'guidegrid-travel' ); ?>">
	<button type="button" class="tg-lightbox-close" data-tg-lightbox-close aria-label="<?php esc_attr_e( 'Close image viewer', 'guidegrid-travel' ); ?>">×</button>
	<img src="" alt="" />
</div>

<?php wp_footer(); ?>
</body>
</html>
