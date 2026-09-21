<?php
/**
 * Hero search form (destinations, categories, date, guests).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

$destinations  = tg_get_all_destinations();
$categories    = get_terms(
	array(
		'taxonomy'   => 'tour_category',
		'hide_empty' => false,
	)
);
$tours_archive = get_post_type_archive_link( 'tour' );
?>
<div class="tg-search-card" role="search" aria-label="<?php esc_attr_e( 'Find a tour', 'guidegrid-travel' ); ?>">
	<form action="<?php echo esc_url( $tours_archive ? $tours_archive : home_url( '/' ) ); ?>" method="get">
		<div>
			<label class="tg-label" for="tg-hs-dest"><?php esc_html_e( 'Destination', 'guidegrid-travel' ); ?></label>
			<select class="tg-select" id="tg-hs-dest" name="tg_destination">
				<option value=""><?php esc_html_e( 'Anywhere', 'guidegrid-travel' ); ?></option>
				<?php foreach ( $destinations as $dest ) : ?>
					<option value="<?php echo esc_attr( (string) $dest->ID ); ?>"><?php echo esc_html( get_the_title( $dest ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label class="tg-label" for="tg-hs-cat"><?php esc_html_e( 'Category', 'guidegrid-travel' ); ?></label>
			<select class="tg-select" id="tg-hs-cat" name="tg_category">
				<option value=""><?php esc_html_e( 'All categories', 'guidegrid-travel' ); ?></option>
				<?php foreach ( (array) $categories as $cat ) : ?>
					<?php if ( is_wp_error( $cat ) ) { continue; } ?>
					<option value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label class="tg-label" for="tg-hs-date"><?php esc_html_e( 'Travel date', 'guidegrid-travel' ); ?></label>
			<input type="date" class="tg-input" id="tg-hs-date" name="tg_date" min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" />
		</div>
		<div>
			<label class="tg-label" for="tg-hs-guests"><?php esc_html_e( 'Guests', 'guidegrid-travel' ); ?></label>
			<select class="tg-select" id="tg-hs-guests" name="tg_guests">
				<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
					<option value="<?php echo esc_attr( (string) $i ); ?>"><?php echo esc_html( sprintf( /* translators: %d: guests */ _n( '%d guest', '%d guests', $i, 'guidegrid-travel' ), $i ) ); ?></option>
				<?php endfor; ?>
			</select>
		</div>
		<button type="submit" class="tg-btn tg-btn--primary"><?php echo tg_svg( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Search Tours', 'guidegrid-travel' ); ?></button>
	</form>
</div>
