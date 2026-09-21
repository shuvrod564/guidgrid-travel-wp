<?php
/**
 * Travel guide archive.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="tg-section" style="padding-top:24px;">
	<div class="tg-container">
		<?php tg_breadcrumbs(); ?>
		<header class="tg-page-head">
			<h1><?php esc_html_e( 'Travel Guides', 'guidegrid-travel' ); ?></h1>
			<p class="tg-lead"><?php esc_html_e( 'Planning tips, packing lists and local insights — written by people who went.', 'guidegrid-travel' ); ?></p>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="tg-card-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article class="tg-blog-card">
						<a class="tg-blog-card-media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
							<?php
							if ( has_post_thumbnail() ) {
								the_post_thumbnail( 'tg-card', array( 'loading' => 'lazy' ) );
							} else {
								echo '<span class="tg-skeleton" style="display:block;width:100%;height:100%;"></span>';
							}
							?>
						</a>
						<div class="tg-blog-card-body">
							<h2 class="tg-blog-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p class="tg-blog-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
							<a class="tg-read-more" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read guide', 'guidegrid-travel' ); ?> →</a>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
			<?php tg_the_pagination( $GLOBALS['wp_query'] ); ?>
		<?php else : ?>
			<?php
			tg_empty_state( 'book', __( 'No guides yet', 'guidegrid-travel' ), __( 'Travel guides published in the dashboard will appear here.' ) );
			?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
