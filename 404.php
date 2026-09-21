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
	<div class="tg-container" style="max-width:700px;">
		<div class="tg-empty">
			<span class="tg-empty-icon"><?php echo tg_svg( 'compass' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
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
