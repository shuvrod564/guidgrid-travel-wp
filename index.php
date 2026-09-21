<?php
/**
 * Blog index / fallback template.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="tg-section">
	<div class="tg-container">
		<header class="tg-page-head">
			<?php tg_breadcrumbs(); ?>
			<h1>
				<?php
				if ( is_home() && ! is_front_page() ) {
					echo esc_html( get_the_title( (int) get_option( 'blog_page_id' ) ) ? get_the_title( (int) get_option( 'blog_page_id' ) ) : __( 'Blog', 'guidegrid-travel' ) );
				} elseif ( is_archive() ) {
					the_archive_title();
				} elseif ( is_search() ) {
					printf( /* translators: %s: search query */ esc_html__( 'Search results for “%s”', 'guidegrid-travel' ), esc_html( get_search_query() ) );
				} else {
					esc_html_e( 'Latest', 'guidegrid-travel' );
				}
				?>
			</h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="tg-card-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					tg_blog_card( get_the_ID() );
				endwhile;
				?>
			</div>
			<?php tg_the_pagination( $GLOBALS['wp_query'] ); ?>
		<?php else : ?>
			<?php
			tg_empty_state(
				'compass',
				__( 'Nothing here yet', 'guidegrid-travel' ),
				__( 'No content matches. Try a different search or check back soon.', 'guidegrid-travel' )
			);
			?>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
