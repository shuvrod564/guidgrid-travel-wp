<?php
/**
 * Tour archive with filters, sorting and pagination.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();

$tg_destinations = tg_get_all_destinations();
$tg_categories   = get_terms(
	array(
		'taxonomy'   => 'tour_category',
		'hide_empty' => false,
	)
);
$tg_activities   = get_terms(
	array(
		'taxonomy'   => 'activity',
		'hide_empty' => false,
	)
);

// Current filter values.
$gf = static function ( $key, $default = '' ) {
	$value = isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return is_scalar( $value ) ? $value : $default;
};
?>

<section class="tg-section" style="padding-top:24px;">
	<div class="tg-container">
		<header class="tg-page-head">
			<?php tg_breadcrumbs(); ?>
			<h1><?php echo is_tax() ? esc_html( single_term_title( '', false ) ) : esc_html__( 'All Tours', 'guidegrid-travel' ); ?></h1>
			<p class="tg-lead"><?php echo esc_html__( 'Every package we run — filter by destination, price, dates and more.', 'guidegrid-travel' ); ?></p>
		</header>

		<div class="tg-listing-layout">
			<!-- ============ Filters ============ -->
			<aside class="tg-filters" id="tg-filters" aria-label="<?php esc_attr_e( 'Tour filters', 'guidegrid-travel' ); ?>">
				<div class="tg-filters-head">
					<h3><?php esc_html_e( 'Filters', 'guidegrid-travel' ); ?></h3>
					<button type="button" class="tg-icon-btn tg-filters-close" data-tg-close-filters aria-label="<?php esc_attr_e( 'Close filters', 'guidegrid-travel' ); ?>"><?php echo tg_svg( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				</div>

				<form method="get" action="<?php echo esc_url( get_post_type_archive_link( 'tour' ) ); ?>" data-tg-filter-form>
					<div class="tg-filter-group">
						<label class="tg-label" for="tg-f-dest"><?php esc_html_e( 'Destination', 'guidegrid-travel' ); ?></label>
						<select class="tg-select" id="tg-f-dest" name="tg_destination">
							<option value=""><?php esc_html_e( 'Anywhere', 'guidegrid-travel' ); ?></option>
							<?php foreach ( $tg_destinations as $d ) : ?>
								<option value="<?php echo esc_attr( (string) $d->ID ); ?>" <?php selected( (string) $gf( 'tg_destination', '0' ), (string) $d->ID ); ?>><?php echo esc_html( get_the_title( $d ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="tg-filter-group">
						<label class="tg-label" for="tg-f-cat"><?php esc_html_e( 'Category', 'guidegrid-travel' ); ?></label>
						<select class="tg-select" id="tg-f-cat" name="tg_category">
							<option value=""><?php esc_html_e( 'All categories', 'guidegrid-travel' ); ?></option>
							<?php foreach ( (array) $tg_categories as $c ) : ?>
								<?php if ( is_wp_error( $c ) ) { continue; } ?>
								<option value="<?php echo esc_attr( $c->slug ); ?>" <?php selected( $gf( 'tg_category' ), $c->slug ); ?>><?php echo esc_html( $c->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="tg-filter-group">
						<label class="tg-label" for="tg-f-act"><?php esc_html_e( 'Activity', 'guidegrid-travel' ); ?></label>
						<select class="tg-select" id="tg-f-act" name="tg_activity">
							<option value=""><?php esc_html_e( 'All activities', 'guidegrid-travel' ); ?></option>
							<?php foreach ( (array) $tg_activities as $a ) : ?>
								<?php if ( is_wp_error( $a ) ) { continue; } ?>
								<option value="<?php echo esc_attr( $a->slug ); ?>" <?php selected( $gf( 'tg_activity' ), $a->slug ); ?>><?php echo esc_html( $a->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="tg-filter-group">
						<label class="tg-label" for="tg-f-date"><?php esc_html_e( 'Travel date', 'guidegrid-travel' ); ?></label>
						<input type="date" class="tg-input" id="tg-f-date" name="tg_date" value="<?php echo esc_attr( $gf( 'tg_date' ) ); ?>" min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" />
					</div>

					<div class="tg-filter-group">
						<span class="tg-label"><?php esc_html_e( 'Price (adult, USD)', 'guidegrid-travel' ); ?></span>
						<div class="tg-filter-dual">
							<label class="screen-reader-text" for="tg-f-pmin"><?php esc_html_e( 'Minimum price', 'guidegrid-travel' ); ?></label>
							<input type="number" min="0" class="tg-input" id="tg-f-pmin" name="tg_min_price" placeholder="Min" value="<?php echo esc_attr( $gf( 'tg_min_price' ) ); ?>" />
							<label class="screen-reader-text" for="tg-f-pmax"><?php esc_html_e( 'Maximum price', 'guidegrid-travel' ); ?></label>
							<input type="number" min="0" class="tg-input" id="tg-f-pmax" name="tg_max_price" placeholder="Max" value="<?php echo esc_attr( $gf( 'tg_max_price' ) ); ?>" />
						</div>
					</div>

					<div class="tg-filter-group">
						<label class="tg-label" for="tg-f-diff"><?php esc_html_e( 'Difficulty', 'guidegrid-travel' ); ?></label>
						<select class="tg-select" id="tg-f-diff" name="tg_difficulty">
							<option value=""><?php esc_html_e( 'Any', 'guidegrid-travel' ); ?></option>
							<option value="easy" <?php selected( $gf( 'tg_difficulty' ), 'easy' ); ?>><?php esc_html_e( 'Easy', 'guidegrid-travel' ); ?></option>
							<option value="moderate" <?php selected( $gf( 'tg_difficulty' ), 'moderate' ); ?>><?php esc_html_e( 'Moderate', 'guidegrid-travel' ); ?></option>
							<option value="challenging" <?php selected( $gf( 'tg_difficulty' ), 'challenging' ); ?>><?php esc_html_e( 'Challenging', 'guidegrid-travel' ); ?></option>
						</select>
					</div>

					<div class="tg-filter-group">
						<label class="tg-label" for="tg-f-type"><?php esc_html_e( 'Tour type', 'guidegrid-travel' ); ?></label>
						<select class="tg-select" id="tg-f-type" name="tg_type">
							<option value=""><?php esc_html_e( 'Any type', 'guidegrid-travel' ); ?></option>
							<option value="day_tour" <?php selected( $gf( 'tg_type' ), 'day_tour' ); ?>><?php esc_html_e( 'Day Tour', 'guidegrid-travel' ); ?></option>
							<option value="multi_day" <?php selected( $gf( 'tg_type' ), 'multi_day' ); ?>><?php esc_html_e( 'Multi-Day', 'guidegrid-travel' ); ?></option>
							<option value="private" <?php selected( $gf( 'tg_type' ), 'private' ); ?>><?php esc_html_e( 'Private', 'guidegrid-travel' ); ?></option>
							<option value="group" <?php selected( $gf( 'tg_type' ), 'group' ); ?>><?php esc_html_e( 'Group', 'guidegrid-travel' ); ?></option>
							<option value="custom" <?php selected( $gf( 'tg_type' ), 'custom' ); ?>><?php esc_html_e( 'Custom', 'guidegrid-travel' ); ?></option>
						</select>
					</div>

					<div class="tg-filter-group">
						<label class="tg-label" for="tg-f-rating"><?php esc_html_e( 'Minimum rating', 'guidegrid-travel' ); ?></label>
						<select class="tg-select" id="tg-f-rating" name="tg_rating">
							<option value=""><?php esc_html_e( 'Any rating', 'guidegrid-travel' ); ?></option>
							<option value="4.5" <?php selected( $gf( 'tg_rating' ), '4.5' ); ?>>4.5+</option>
							<option value="4" <?php selected( $gf( 'tg_rating' ), '4' ); ?>>4+</option>
							<option value="3.5" <?php selected( $gf( 'tg_rating' ), '3.5' ); ?>>3.5+</option>
						</select>
					</div>

					<div class="tg-filter-group">
						<div class="tg-filter-checks">
							<label class="tg-check-row"><input type="checkbox" name="tg_featured" value="1" <?php checked( (string) $gf( 'tg_featured', '0' ), '1' ); ?> /> <span><?php esc_html_e( 'Featured tours only', 'guidegrid-travel' ); ?></span></label>
						</div>
					</div>

					<div class="tg-filter-group" style="padding-bottom:0;">
						<button type="submit" class="tg-btn tg-btn--primary tg-btn--block"><?php esc_html_e( 'Apply Filters', 'guidegrid-travel' ); ?></button>
						<a class="tg-btn tg-btn--ghost tg-btn--block tg-mt-4" href="<?php echo esc_url( get_post_type_archive_link( 'tour' ) ); ?>"><?php esc_html_e( 'Reset', 'guidegrid-travel' ); ?></a>
					</div>
				</form>
			</aside>

			<!-- ============ Results ============ -->
			<div>
				<div class="tg-toolbar">
					<button type="button" class="tg-btn tg-btn--ghost tg-btn-filters" data-tg-open-filters><?php echo tg_svg( 'filter' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Filters', 'guidegrid-travel' ); ?></button>
					<span class="tg-result-count">
						<?php
						printf(
							/* translators: %d: number of tours */
							esc_html( _n( '%d tour found', '%d tours found', (int) $wp_query->found_posts, 'guidegrid-travel' ) ),
							(int) $wp_query->found_posts
						);
						?>
					</span>
					<span class="tg-toolbar-spacer"></span>
					<label class="screen-reader-text" for="tg-sort"><?php esc_html_e( 'Sort tours', 'guidegrid-travel' ); ?></label>
					<select class="tg-select" id="tg-sort" onchange="window.location.search=this.value;this.blur();">
						<?php
						$sort_options = array(
							'recommended' => __( 'Recommended', 'guidegrid-travel' ),
							'popular'     => __( 'Most booked', 'guidegrid-travel' ),
							'newest'      => __( 'Newest', 'guidegrid-travel' ),
							'price_low'   => __( 'Price: low to high', 'guidegrid-travel' ),
							'price_high'  => __( 'Price: high to low', 'guidegrid-travel' ),
							'rating'      => __( 'Top rated', 'guidegrid-travel' ),
						);
						$current_sort = $gf( 'tg_sort', 'recommended' );
						foreach ( $sort_options as $key => $label ) :
							$url  = add_query_arg( 'tg_sort', $key, remove_query_arg( 'tg_sort' ) );
							$base = strtok( $url, '?' );
							$qs   = array();
							if ( false !== strpos( $url, '?' ) ) {
								parse_str( substr( $url, strpos( $url, '?' ) + 1 ), $qs );
							}
							?>
							<option value="<?php echo esc_attr( http_build_query( $qs ) ); ?>" <?php selected( $current_sort, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<?php if ( have_posts() ) : ?>
					<div class="tg-card-grid">
						<?php
						while ( have_posts() ) :
							the_post();
							tg_tour_card( get_the_ID() );
						endwhile;
						?>
					</div>
					<?php tg_the_pagination( $wp_query ); ?>
				<?php else : ?>
					<?php
					tg_empty_state(
						'filter',
						__( 'No tours match your filters', 'guidegrid-travel' ),
						__( 'Try widening the price range or removing the date filter.', 'guidegrid-travel' ),
						get_post_type_archive_link( 'tour' ),
						__( 'Reset filters', 'guidegrid-travel' )
					);
					?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
