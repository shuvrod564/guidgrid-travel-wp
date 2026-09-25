<?php
/**
 * 404 page.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="tg-section">
	<div class="tg-container" style="max-width:700px;padding-top:40px;padding-bottom:40px;">
		<div class="tg-empty">
			<span class="tg-empty-icon">
				<?php // echo tg_svg( 'compass' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
					<path d="M0 0h24v24H0z" fill="none" />
					<path fill="currentColor" d="M4 5.25C4 4.56 4.56 4 5.25 4H8a1 1 0 0 0 .991-1.134A.75.75 0 0 0 8.25 2h-3A3.25 3.25 0 0 0 2 5.25v3a.75.75 0 0 0 .866.741Q2.932 9 3 9a1 1 0 0 0 1-1zm0 13.5c0 .69.56 1.25 1.25 1.25H8a1 1 0 0 1 .991 1.134a.75.75 0 0 1-.741.866h-3A3.25 3.25 0 0 1 2 18.75v-3a.75.75 0 0 1 .866-.741Q2.932 15 3 15a1 1 0 0 1 1 1zM18.75 4c.69 0 1.25.56 1.25 1.25V8a1 1 0 0 0 1.134.991A.75.75 0 0 0 22 8.25v-3A3.25 3.25 0 0 0 18.75 2h-3a.75.75 0 0 0-.741.866A1 1 0 0 0 16 4zM20 18.75c0 .69-.56 1.25-1.25 1.25H16a1 1 0 0 0-.991 1.134a.75.75 0 0 0 .741.866h3A3.25 3.25 0 0 0 22 18.75v-3a.75.75 0 0 0-.866-.741A1 1 0 0 0 20 16zM7 8a1 1 0 0 1 1-1h8a1 1 0 1 1 0 2H8a1 1 0 0 1-1-1m1 3a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2zm-1 5a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2H8a1 1 0 0 1-1-1" />
				</svg> 
			</span>
			<h3><?php esc_html_e( 'Page not found', 'guidegrid-travel' ); ?></h3>
			<p><?php esc_html_e( 'The page you are looking for may have moved. Try searching or browse our tours.', 'guidegrid-travel' ); ?></p>
			<form role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" style="display:flex;gap:10px;max-width:420px;margin:0 auto 18px;">
				<label class="screen-reader-text" for="tg-404-search"><?php esc_html_e( 'Search', 'guidegrid-travel' ); ?></label>
				<input type="search" id="tg-404-search" name="s" class="tg-input" placeholder="<?php esc_attr_e( 'Search…', 'guidegrid-travel' ); ?>" />
				<button type="submit" class="tg-btn tg-btn--primary"><?php esc_html_e( 'Search', 'guidegrid-travel' ); ?></button>
			</form>
			<a class="tg-btn tg-btn--secondary" href="<?php echo esc_url( get_post_type_archive_link( 'tour' ) ? get_post_type_archive_link( 'tour' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Browse tours', 'guidegrid-travel' ); ?></a>
		</div>
	</div>
</section>
<?php
get_footer();
