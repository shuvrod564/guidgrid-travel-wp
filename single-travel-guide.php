<?php
/**
 * Single travel guide.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$guide_id    = get_the_ID();
	$dest_id     = (int) tg_get_meta( $guide_id, '_tg_guide_destination', 0 );
	$dest        = $dest_id ? get_post( $dest_id ) : null;
	$type        = tg_get_meta( $guide_id, '_tg_guide_type', '' );
	$type_labels = array(
		'planning'  => __( 'Trip Planning', 'guidegrid-travel' ),
		'packing'   => __( 'Packing Guide', 'guidegrid-travel' ),
		'food'      => __( 'Food Guide', 'guidegrid-travel' ),
		'tips'      => __( 'Travel Tips', 'guidegrid-travel' ),
		'itinerary' => __( 'Itinerary', 'guidegrid-travel' ),
	);
	?>
	<section class="tg-section" style="padding-top:24px;">
		<div class="tg-container" style="max-width:900px;">
			<?php tg_breadcrumbs(); ?>
			<header class="tg-page-head">
				<?php if ( $type && isset( $type_labels[ $type ] ) ) : ?>
					<span class="tg-badge tg-badge--primary"><?php echo esc_html( $type_labels[ $type ] ); ?></span>
				<?php endif; ?>
				<h1><?php the_title(); ?></h1>
				<?php if ( $dest ) : ?>
					<p class="tg-lead"><?php echo tg_svg( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="<?php echo esc_url( get_permalink( $dest ) ); ?>"><?php echo esc_html( get_the_title( $dest ) ); ?></a></p>
				<?php endif; ?>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<p style="margin:0 0 28px;"><img src="<?php echo esc_url( get_the_post_thumbnail_url( null, 'tg-hero' ) ); ?>" alt="<?php the_title_attribute(); ?>" /></p>
			<?php endif; ?>

			<div class="tg-entry-content">
				<?php the_content(); ?>
			</div>
		</div>
	</section>
	<?php
endwhile;

get_footer();
