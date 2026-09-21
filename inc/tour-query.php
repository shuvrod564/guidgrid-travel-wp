<?php
/**
 * Tour archive query: filters and sorting via query args.
 *
 * Supported GET args (whitelisted):
 *   tg_destination, tg_category, tg_activity, tg_difficulty,
 *   tg_min_price, tg_max_price, tg_rating, tg_date, tg_type,
 *   tg_featured, tg_sort
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook the tour archive query builder.
 *
 * @return void
 */
function tg_init_tour_query() {
	add_action( 'pre_get_posts', 'tg_tour_archive_query' );
}
add_action( 'after_setup_theme', 'tg_init_tour_query' );

/**
 * Build meta/tax queries for the tour archive.
 *
 * @param WP_Query $q Query.
 * @return void
 */
function tg_tour_archive_query( WP_Query $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( ! ( $q->is_post_type_archive( 'tour' ) || $q->is_tax( 'tour_category' ) || $q->is_tax( 'activity' ) ) ) {
		return;
	}

	$settings = tg_settings();
	$q->set( 'posts_per_page', max( 1, absint( $settings['search_per_page'] ) ) );

	$meta_query = array();
	$tax_query  = array();
	$args       = $q->query_vars;

	// Destination.
	$destination = isset( $_GET['tg_destination'] ) ? absint( $_GET['tg_destination'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $destination ) {
		$meta_query[] = array(
			'key'   => '_tg_destination_id',
			'value' => $destination,
		);
	}

	// Category.
	$category = isset( $_GET['tg_category'] ) ? sanitize_title( wp_unslash( $_GET['tg_category'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $category ) {
		$tax_query[] = array(
			'taxonomy' => 'tour_category',
			'field'    => 'slug',
			'terms'    => $category,
		);
	}

	// Activity.
	$activity = isset( $_GET['tg_activity'] ) ? sanitize_title( wp_unslash( $_GET['tg_activity'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $activity ) {
		$tax_query[] = array(
			'taxonomy' => 'activity',
			'field'    => 'slug',
			'terms'    => $activity,
		);
	}

	if ( $tax_query ) {
		$q->set( 'tax_query', $tax_query ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	// Difficulty.
	$difficulty = isset( $_GET['tg_difficulty'] ) ? sanitize_key( $_GET['tg_difficulty'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( in_array( $difficulty, array( 'easy', 'moderate', 'challenging' ), true ) ) {
		$meta_query[] = array(
			'key'   => '_tg_difficulty',
			'value' => $difficulty,
		);
	}

	// Price range.
	$min_price = isset( $_GET['tg_min_price'] ) ? (float) $_GET['tg_min_price'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$max_price = isset( $_GET['tg_max_price'] ) ? (float) $_GET['tg_max_price'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $min_price > 0 ) {
		$meta_query[] = array(
			'key'     => '_tg_adult_price',
			'value'   => $min_price,
			'compare' => '>=',
			'type'    => 'DECIMAL(10,2)',
		);
	}
	if ( $max_price > 0 ) {
		$meta_query[] = array(
			'key'     => '_tg_adult_price',
			'value'   => $max_price,
			'compare' => '<=',
			'type'    => 'DECIMAL(10,2)',
		);
	}

	// Minimum rating.
	$rating = isset( $_GET['tg_rating'] ) ? (float) $_GET['tg_rating'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $rating > 0 ) {
		$meta_query[] = array(
			'key'     => '_tg_rating_avg',
			'value'   => $rating,
			'compare' => '>=',
			'type'    => 'DECIMAL(3,1)',
		);
	}

	// Type.
	$type = isset( $_GET['tg_type'] ) ? sanitize_key( $_GET['tg_type'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( in_array( $type, array( 'day_tour', 'multi_day', 'private', 'group', 'custom' ), true ) ) {
		$meta_query[] = array(
			'key'   => '_tg_tour_type',
			'value' => $type,
		);
	}

	// Featured only.
	$featured = isset( $_GET['tg_featured'] ) ? absint( $_GET['tg_featured'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $featured ) {
		$meta_query[] = array(
			'key'   => '_tg_featured',
			'value' => 1,
		);
	}

	// Date availability (approximate: mode-aware, not capacity-aware).
	$travel_date = isset( $_GET['tg_date'] ) ? TG_Availability::normalize_date( (string) sanitize_text_field( wp_unslash( $_GET['tg_date'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $travel_date ) {
		$dow = (string) (int) gmdate( 'w', strtotime( $travel_date ) );
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => '_tg_availability_mode',
				'value'   => 'any',
			),
			array(
				'relation' => 'AND',
				array(
					'key'   => '_tg_availability_mode',
					'value' => 'range',
				),
				array(
					'key'     => '_tg_range_start',
					'value'   => $travel_date,
					'compare' => '<=',
				),
				array(
					'key'     => '_tg_range_end',
					'value'   => $travel_date,
					'compare' => '>=',
				),
			),
			array(
				'relation' => 'AND',
				array(
					'key'   => '_tg_availability_mode',
					'value' => 'weekly',
				),
				array(
					'key'     => '_tg_weekly_days',
					'value'   => $dow,
					'compare' => 'LIKE',
				),
			),
			array(
				'relation' => 'AND',
				array(
					'key'   => '_tg_availability_mode',
					'value' => 'fixed',
				),
				array(
					'key'     => '_tg_availability_dates',
					'value'   => $travel_date,
					'compare' => 'LIKE',
				),
			),
		);
	}

	if ( $meta_query ) {
		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}
		$q->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	// Sorting.
	$sort = isset( $_GET['tg_sort'] ) ? sanitize_key( $_GET['tg_sort'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	switch ( $sort ) {
		case 'popular':
			$q->set( 'orderby', array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ) );
			$q->set( 'meta_key', '_tg_booking_count' ); // phpcs:ignore WordPress.DB.SlowDBQuery
			break;
		case 'newest':
			$q->set( 'orderby', 'date' );
			$q->set( 'order', 'DESC' );
			break;
		case 'price_low':
			$q->set( 'orderby', 'meta_value_num' );
			$q->set( 'order', 'ASC' );
			$q->set( 'meta_key', '_tg_adult_price' ); // phpcs:ignore WordPress.DB.SlowDBQuery
			break;
		case 'price_high':
			$q->set( 'orderby', 'meta_value_num' );
			$q->set( 'order', 'DESC' );
			$q->set( 'meta_key', '_tg_adult_price' ); // phpcs:ignore WordPress.DB.SlowDBQuery
			break;
		case 'rating':
			$q->set( 'orderby', 'meta_value_num' );
			$q->set( 'order', 'DESC' );
			$q->set( 'meta_key', '_tg_rating_avg' ); // phpcs:ignore WordPress.DB.SlowDBQuery
			break;
		case 'recommended':
		default:
			$q->set( 'orderby', array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ) );
			$q->set( 'meta_key', '_tg_featured' ); // phpcs:ignore WordPress.DB.SlowDBQuery
			break;
	}
}

/**
 * Tour filter options helper: destination select values.
 *
 * @return WP_Post[]
 */
function tg_get_all_destinations(): array {
	static $cached = null;
	if ( null === $cached ) {
		$cached = get_posts(
			array(
				'post_type'      => 'destination',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}
	return (array) $cached;
}
