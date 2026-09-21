<?php
/**
 * Single post.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<section class="tg-section">
		<div class="tg-container" style="max-width:860px;">
			<?php tg_breadcrumbs(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<header class="tg-page-head">
					<h1><?php the_title(); ?></h1>
					<div class="tg-entry-meta">
						<span><?php the_author(); ?></span>
						<span><?php echo esc_html( get_the_date() ); ?></span>
						<span><?php the_category( ', ' ); ?></span>
					</div>
				</header>
				<?php if ( has_post_thumbnail() ) : ?>
					<p style="margin:0 0 28px;"><img src="<?php echo esc_url( get_the_post_thumbnail_url( null, 'tg-hero' ) ); ?>" alt="<?php the_title_attribute(); ?>" /></p>
				<?php endif; ?>
				<div class="tg-entry-content">
					<?php the_content(); ?>
				</div>
			</article>

			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
