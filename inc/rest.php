<?php
/**
 * REST API (namespace tg/v1).
 *
 * Public endpoints expose only public tour/availability data.
 * Private booking endpoints require authentication and return only
 * data belonging to the requesting user.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_rest_routes' ) ) {
	/**
	 * Register routes.
	 *
	 * @return void
	 */
	function tg_rest_routes() {
		register_rest_route(
			'tg/v1',
			'/tours',
			array(
				'methods'             => 'GET',
				'callback'            => 'tg_rest_tours',
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'       => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page'   => array(
						'type'    => 'integer',
						'default' => 12,
					),
					'category'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'destination' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

		register_rest_route(
			'tg/v1',
			'/tours/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => 'tg_rest_tour',
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $value ) {
							return is_numeric( $value );
						},
					),
				),
			)
		);

		register_rest_route(
			'tg/v1',
			'/availability',
			array(
				'methods'             => 'GET',
				'callback'            => 'tg_rest_availability',
				'permission_callback' => '__return_true',
				'args'                => array(
					'tour_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'tg/v1',
			'/bookings',
			array(
				'methods'             => 'POST',
				'callback'            => 'tg_rest_create_booking',
				'permission_callback' => '__return_true', // Full input validation inside.
			)
		);

		register_rest_route(
			'tg/v1',
			'/bookings/mine',
			array(
				'methods'             => 'GET',
				'callback'            => 'tg_rest_my_bookings',
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'tg/v1',
			'/payments/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => 'tg_rest_payment_webhook',
				'permission_callback' => '__return_true', // Signature verified in handler.
			)
		);
	}
}
add_action( 'rest_api_init', 'tg_rest_routes' );

/**
 * Build a public tour payload.
 *
 * @param WP_Post $tour Tour post.
 * @return array
 */
function tg_rest_tour_payload( WP_Post $tour ): array {
	$price  = tg_tour_price_info( $tour->ID );
	$rating = tg_get_tour_rating( $tour->ID );
	$addons = array();
	foreach ( TG_Addons::get_for_tour( $tour->ID ) as $addon ) {
		$addons[] = array(
			'id'    => $addon['id'],
			'name'  => $addon['name'],
			'price' => $addon['price'],
			'unit'  => $addon['unit'],
		);
	}

	return array(
		'id'            => $tour->ID,
		'title'         => $tour->post_title,
		'excerpt'       => wp_strip_all_tags( get_the_excerpt( $tour ) ),
		'url'           => get_permalink( $tour ),
		'image'         => get_the_post_thumbnail_url( $tour, 'full' ),
		'destination'   => tg_tour_destination_name( $tour->ID ),
		'duration'      => tg_tour_duration_text( $tour->ID ),
		'price'         => $price['base'],
		'previous'      => $price['previous'],
		'currency'      => $price['currency'],
		'rating'        => $rating['avg'],
		'review_count'  => $rating['count'],
		'featured'      => (bool) get_post_meta( $tour->ID, '_tg_featured', true ),
		'addons'        => $addons,
		'next_departure' => tg_get_next_available_date( $tour->ID ),
	);
}

/**
 * GET /tours
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function tg_rest_tours( WP_REST_Request $request ) {
	$settings = tg_settings();
	$args     = array(
		'post_type'      => 'tour',
		'posts_per_page' => min( 50, max( 1, (int) $request['per_page'] ) ),
		'paged'          => max( 1, (int) $request['page'] ),
	);

	$tax_query = array();
	if ( $request['category'] ) {
		$tax_query[] = array(
			'taxonomy' => 'tour_category',
			'field'    => 'slug',
			'terms'    => sanitize_title( $request['category'] ),
		);
	}
	$meta_query = array();
	if ( (int) $request['destination'] ) {
		$meta_query[] = array(
			'key'   => '_tg_destination_id',
			'value' => (int) $request['destination'],
		);
	}
	if ( $tax_query ) {
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	if ( $meta_query ) {
		$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	$query = new WP_Query( $args );
	$items = array();
	foreach ( $query->posts as $tour ) {
		$items[] = tg_rest_tour_payload( $tour );
	}

	return new WP_REST_Response(
		array(
			'items'     => $items,
			'total'     => (int) $query->found_posts,
			'pages'     => (int) $query->max_num_pages,
			'page'      => max( 1, (int) $request['page'] ),
			'per_page'  => (int) $args['posts_per_page'],
		),
		200
	);
}

/**
 * GET /tours/{id}
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function tg_rest_tour( WP_REST_Request $request ) {
	$tour = get_post( (int) $request['id'] );
	if ( ! $tour || 'tour' !== $tour->post_type || 'publish' !== $tour->post_status ) {
		return new WP_REST_Response( array( 'message' => 'Tour not found.' ), 404 );
	}

	$payload        = tg_rest_tour_payload( $tour );
	$payload['faq'] = (array) tg_get_meta( $tour->ID, '_tg_faq', array() );
	$payload['itinerary'] = (array) tg_get_meta( $tour->ID, '_tg_itinerary', array() );

	return new WP_REST_Response( $payload, 200 );
}

/**
 * GET /availability?tour_id=123
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function tg_rest_availability( WP_REST_Request $request ) {
	$tour_id = (int) $request['tour_id'];
	$tour    = get_post( $tour_id );
	if ( ! $tour || 'tour' !== $tour->post_type || 'publish' !== $tour->post_status ) {
		return new WP_REST_Response( array( 'message' => 'Tour not found.' ), 404 );
	}

	return new WP_REST_Response(
		array(
			'tour_id' => $tour_id,
			'dates'   => TG_Availability::get_dates( $tour_id ),
		),
		200
	);
}

/**
 * POST /bookings
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function tg_rest_create_booking( WP_REST_Request $request ) {
	$params   = $request->get_params();
	$customer = array();
	if ( isset( $params['customer'] ) && is_array( $params['customer'] ) ) {
		$customer = $params['customer'];
	}

	$result = TG_Bookings::create(
		array(
			'tour_id'        => isset( $params['tour_id'] ) ? absint( $params['tour_id'] ) : 0,
			'date'           => isset( $params['date'] ) ? sanitize_text_field( $params['date'] ) : '',
			'adults'         => isset( $params['adults'] ) ? absint( $params['adults'] ) : 0,
			'children'       => isset( $params['children'] ) ? absint( $params['children'] ) : 0,
			'infants'        => isset( $params['infants'] ) ? absint( $params['infants'] ) : 0,
			'addons'         => isset( $params['addons'] ) ? array_map( 'absint', (array) $params['addons'] ) : array(),
			'coupon'         => isset( $params['coupon'] ) ? sanitize_text_field( $params['coupon'] ) : '',
			'payment_method' => isset( $params['payment_method'] ) ? sanitize_key( $params['payment_method'] ) : 'bank',
			'customer'       => $customer,
			'user_id'        => get_current_user_id(),
			'source'         => 'rest',
		)
	);

	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
	}

	return new WP_REST_Response(
		array(
			'booking_number'   => $result->booking_number,
			'total'            => (float) $result->total,
			'currency'         => $result->currency,
			'booking_status'   => $result->booking_status,
			'payment_status'   => $result->payment_status,
			'confirmation_url' => tg_confirmation_page_url( $result->booking_number ),
		),
		201
	);
}

/**
 * GET /bookings/mine (authenticated users only).
 *
 * @return WP_REST_Response
 */
function tg_rest_my_bookings() {
	$user = wp_get_current_user();
	$rows = TG_Bookings::get_for_user( (int) $user->ID, $user->user_email );

	$items = array();
	foreach ( $rows as $row ) {
		$items[] = array(
			'booking_number' => $row->booking_number,
			'tour_id'        => (int) $row->tour_id,
			'date'           => $row->booking_date,
			'total'          => (float) $row->total,
			'currency'       => $row->currency,
			'booking_status' => $row->booking_status,
			'payment_status' => $row->payment_status,
			'created_at'     => $row->created_at,
		);
	}

	return new WP_REST_Response( array( 'items' => $items ), 200 );
}

/**
 * POST /payments/webhook
 *
 * @return WP_REST_Response
 */
function tg_rest_payment_webhook() {
	$raw     = file_get_contents( 'php://input' );
	$headers = array_change_key_case( $GLOBALS['server']->headers(), CASE_LOWER );

	$result = TG_Payments::handle_webhook( (string) $raw, $headers );

	return new WP_REST_Response( $result, $result['success'] ? 200 : 400 );
}
