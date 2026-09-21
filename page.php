<?php
/**
 * Static page.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<section class="tg-section">
		<div class="tg-container" style="max-width:900px;">
			<?php tg_breadcrumbs(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<header class="tg-page-head">
					<h1><?php the_title(); ?></h1>
				</header>
				<div class="tg-entry-content">
					<?php
					if ( has_post_thumbnail() ) {
						echo '<p style="margin:0 0 28px;"><img src="' . esc_url( get_the_post_thumbnail_url( null, 'tg-hero' ) ) . '" alt="' . esc_attr( get_the_title() ) . '" /></p>';
					}
					the_content();
					?>
				</div>
			</article>
		</div>
	</section>
	<?php
endwhile;

get_footer();
