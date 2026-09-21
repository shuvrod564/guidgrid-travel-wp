<?php
/**
 * Destination archive.
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
			<h1><?php esc_html_e( 'Destinations', 'guidegrid-travel' ); ?></h1>
			<p class="tg-lead"><?php esc_html_e( 'Explore the places we know best.', 'guidegrid-travel' ); ?></p>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="tg-row">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<div class="tg-col"><?php tg_destination_card( get_post() ); ?></div>
				<?php endwhile; ?>
			</div>
			<?php tg_the_pagination( $GLOBALS['wp_query'] ); ?>
		<?php else : ?>
			<?php
			tg_empty_state(
				'pin',
				__( 'No destinations yet', 'guidegrid-travel' ),
				__( 'Destinations added in the dashboard will show here.', 'guidegrid-travel' )
			);
			?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
