<?php
/**
 * Structured data (JSON-LD) for tour pages.
 *
 * Emits TouristTrip + Offer + AggregateRating (only from real, approved
 * reviews) + BreadcrumbList. Compatible with SEO plugins — this only
 * adds what core does not provide.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_structured_data' ) ) {
	/**
	 * Output JSON-LD on tour single pages.
	 *
	 * @return void
	 */
	function tg_structured_data() {
		if ( ! is_singular( 'tour' ) ) {
			return;
		}

		$tour_id  = get_queried_object_id();
		$settings = tg_settings();
		$price    = tg_tour_price_info( $tour_id );
		$rating   = TG_Reviews::summary( $tour_id );
		$dest     = tg_tour_destination( $tour_id );
		$days     = tg_tour_duration_days( $tour_id );

		$trip = array(
			'@context' => 'https://schema.org',
			'@type'    => 'TouristTrip',
			'name'     => get_the_title( $tour_id ),
			'description' => wp_strip_all_tags( get_the_excerpt( $tour_id ) ),
			'url'      => get_permalink( $tour_id ),
			'duration' => 'P' . max( 1, $days ) . 'D',
		);

		if ( has_post_thumbnail( $tour_id ) ) {
			$trip['image'] = get_the_post_thumbnail_url( $tour_id, 'full' );
		}

		if ( $dest ) {
			$trip['touristType'] = array( 'attraction' );
			$trip['location']    = array(
				'@type' => 'Place',
				'name'  => get_the_title( $dest ),
			);
			$lat = tg_get_meta( $tour_id, '_tg_lat', '' ) ? tg_get_meta( $tour_id, '_tg_lat', '' ) : tg_get_meta( $dest->ID, '_tg_lat', '' );
			$lng = tg_get_meta( $tour_id, '_tg_lng', '' ) ? tg_get_meta( $tour_id, '_tg_lng', '' ) : tg_get_meta( $dest->ID, '_tg_lng', '' );
			if ( $lat && $lng ) {
				$trip['location']['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => (string) floatval( $lat ),
					'longitude' => (string) floatval( $lng ),
				);
			}
		}

		if ( $price['base'] > 0 ) {
			$offer = array(
				'@type'         => 'Offer',
				'price'         => (string) $price['base'],
				'priceCurrency' => $price['currency'],
				'availability'  => tg_get_next_available_date( $tour_id ) ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
				'url'           => get_permalink( $tour_id ),
			);
			$trip['offers'] = $offer;
		}

		if ( $rating['count'] > 0 ) {
			$aggregate = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $rating['avg'],
				'reviewCount' => (string) $rating['count'],
				'bestRating'  => '5',
				'worstRating' => '1',
			);
			$trip['aggregateRating'] = $aggregate;
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $trip ) . '</script>' . "\n";

		// Breadcrumb.
		$crumbs = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'Home', 'guidegrid-travel' ),
				'item'     => home_url( '/' ),
			),
			array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => __( 'Tours', 'guidegrid-travel' ),
				'item'     => get_post_type_archive_link( 'tour' ),
			),
			array(
				'@type'    => 'ListItem',
				'position' => 3,
				'name'     => get_the_title( $tour_id ),
			),
		);
		$breadcrumb = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $crumbs,
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $breadcrumb ) . '</script>' . "\n";

		// FAQPage when the tour has FAQs.
		$faq = (array) tg_get_meta( $tour_id, '_tg_faq', array() );
		if ( $faq ) {
			$entities = array();
			foreach ( $faq as $item ) {
				if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
					continue;
				}
				$entities[] = array(
					'@type'          => 'Question',
					'name'           => $item['question'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $item['answer'],
					),
				);
			}
			if ( $entities ) {
				$faq_schema = array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => $entities,
				);
				echo '<script type="application/ld+json">' . wp_json_encode( $faq_schema ) . '</script>' . "\n";
			}
		}
	}
}
add_action( 'wp_head', 'tg_structured_data' );
