<?php
/**
 * Reviews engine.
 *
 * Reviews are stored in tg_reviews. Only customers with an eligible
 * booking (confirmed / paid / completed) for the tour receive the
 * verified badge. All reviews pass through moderation (pending,
 * approved, rejected).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TG_Reviews
 */
final class TG_Reviews {

	/**
	 * Table name (prefixed).
	 *
	 * @return string
	 */
	private static function tbl(): string {
		return TG_Database::table( 'reviews' );
	}

	/**
	 * Submit a review.
	 *
	 * @param array $args {
	 *   @type int    $tour_id Required.
	 *   @type int    $user_id Required (must be logged in).
	 *   @type string $name    Display name.
	 *   @type int    $rating  1-5.
	 *   @type string $title   Optional.
	 *   @type string $content Required.
	 * }
	 * @return object|WP_Error The created review row.
	 */
	public static function submit( array $args ) {
		$tour_id = isset( $args['tour_id'] ) ? absint( $args['tour_id'] ) : 0;
		$user_id = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;

		if ( ! $user_id || ! is_user_logged_in() || (int) get_current_user_id() !== $user_id ) {
			return new WP_Error( 'tg_review_login', __( 'Please log in to submit a review.', 'guidegrid-travel' ) );
		}

		$tour = $tour_id ? get_post( $tour_id ) : null;
		if ( ! $tour || 'tour' !== $tour->post_type || 'publish' !== $tour->post_status ) {
			return new WP_Error( 'tg_review_tour', __( 'The selected tour was not found.', 'guidegrid-travel' ) );
		}

		$rating = isset( $args['rating'] ) ? min( 5, max( 1, absint( $args['rating'] ) ) ) : 5;
		$title  = isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '';
		$content = isset( $args['content'] ) ? sanitize_textarea_field( $args['content'] ) : '';

		if ( '' === $content ) {
			return new WP_Error( 'tg_review_empty', __( 'Please write a short review before submitting.', 'guidegrid-travel' ) );
		}

		$global  = $GLOBALS['wpdb'];
		$user    = wp_get_current_user();
		$email   = $user->user_email;

		// One review per user per tour.
		$existing = $global->get_var(
			$global->prepare( 'SELECT id FROM ' . self::tbl() . ' WHERE tour_id = %d AND user_id = %d LIMIT 1', $tour_id, $user_id )
		);
		if ( $existing ) {
			return new WP_Error( 'tg_review_exists', __( 'You have already reviewed this tour.', 'guidegrid-travel' ) );
		}

		$eligibility  = self::eligible_booking( $user_id, $tour_id, $email );
		$booking_id   = $eligibility ? (int) $eligibility->id : 0;
		$verified     = $booking_id ? 1 : 0;
		$auto_approve = current_user_can( 'manage_tg' );

		$now = current_time( 'mysql', true );
		$global->insert(
			self::tbl(),
			array(
				'tour_id'        => $tour_id,
				'booking_id'     => $booking_id,
				'user_id'        => $user_id,
				'customer_email' => $email,
				'name'           => $args['name'] ? sanitize_text_field( $args['name'] ) : $user->display_name,
				'rating'         => $rating,
				'title'          => $title,
				'content'        => $content,
				'verified'       => $verified,
				'status'         => $auto_approve ? 'approved' : 'pending',
				'created_at'     => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		$review_id = (int) $global->insert_id;
		self::recalc_tour_meta( $tour_id );

		if ( ! $auto_approve ) {
			TG_Emails::new_review_admin( self::get_by_id( $review_id ) );
		}

		do_action( 'tg_review_submitted', $review_id, $tour_id, $user_id );

		return self::get_by_id( $review_id );
	}

	/**
	 * Find an eligible booking for a user + tour.
	 *
	 * Eligible statuses: confirmed, paid, completed.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $tour_id Tour post ID.
	 * @param string $email   User email (guest-checkout bookings).
	 * @return object|null
	 */
	public static function eligible_booking( int $user_id, int $tour_id, string $email = '' ) {
		$global = $GLOBALS['wpdb'];
		$table  = TG_Database::table( 'bookings' );
		$now    = current_time( 'mysql', true );

		$rows = $global->get_results(
			$global->prepare(
				"SELECT b.* FROM {$table} b
				 LEFT JOIN " . TG_Database::table( 'customers' ) . ' c ON c.id = b.customer_id
				 WHERE b.tour_id = %d AND b.booking_status IN ("confirmed","paid","completed") AND b.updated_at <= %s
				 AND (c.user_id = %d OR c.email = %s)
				 ORDER BY b.id DESC LIMIT 5', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$tour_id,
				$now,
				$user_id,
				$email
			) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		return $rows ? $rows[0] : null;
	}

	/**
	 * Fetch a review by ID.
	 *
	 * @param int $id Review ID.
	 * @return object|null
	 */
	public static function get_by_id( int $id ) {
		$global = $GLOBALS['wpdb'];
		return $global->get_row( $global->prepare( 'SELECT * FROM ' . self::tbl() . ' WHERE id = %d', $id ) );
	}

	/**
	 * Approved reviews for a tour.
	 *
	 * @param int $tour_id   Tour post ID.
	 * @param int $per_page  Page size.
	 * @param int $page      Page number.
	 * @return array{items:object[],total:int}
	 */
	public static function get_for_tour( int $tour_id, int $per_page = 10, int $page = 1 ): array {
		$global   = $GLOBALS['wpdb'];
		$per_page = max( 1, min( 100, $per_page ) );
		$page     = max( 1, $page );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $global->get_var(
			$global->prepare( 'SELECT COUNT(*) FROM ' . self::tbl() . ' WHERE tour_id = %d AND status = %s', $tour_id, 'approved' )
		);
		$items = $global->get_results(
			$global->prepare(
				'SELECT * FROM ' . self::tbl() . ' WHERE tour_id = %d AND status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d',
				$tour_id,
				'approved',
				$per_page,
				$offset
			)
		);

		return array(
			'items' => (array) $items,
			'total' => $total,
		);
	}

	/**
	 * Rating summary for a tour (approved reviews only).
	 *
	 * @param int $tour_id Tour post ID.
	 * @return array{avg:float,count:int,distribution:array}
	 */
	public static function summary( int $tour_id ): array {
		$global   = $GLOBALS['wpdb'];
		$avg      = (float) $global->get_var( $global->prepare( 'SELECT AVG(rating) FROM ' . self::tbl() . ' WHERE tour_id = %d AND status = %s', $tour_id, 'approved' ) );
		$count    = (int) $global->get_var( $global->prepare( 'SELECT COUNT(*) FROM ' . self::tbl() . ' WHERE tour_id = %d AND status = %s', $tour_id, 'approved' ) );
		$rows     = $global->get_results( $global->prepare( 'SELECT rating, COUNT(*) AS total FROM ' . self::tbl() . ' WHERE tour_id = %d AND status = %s GROUP BY rating', $tour_id, 'approved' ) );
		$dist     = array( 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 );
		foreach ( (array) $rows as $row ) {
			$dist[ (int) $row->rating ] = (int) $row->total;
		}

		return array(
			'avg'          => round( $avg, 1 ),
			'count'        => $count,
			'distribution' => $dist,
		);
	}

	/**
	 * Reviews submitted by a user (account page).
	 *
	 * @param int $user_id User ID.
	 * @return object[]
	 */
	public static function mine( int $user_id ): array {
		$global = $GLOBALS['wpdb'];
		$rows   = $global->get_results( $global->prepare( 'SELECT * FROM ' . self::tbl() . ' WHERE user_id = %d ORDER BY created_at DESC', $user_id ) );
		return (array) $rows;
	}

	/**
	 * Change moderation status.
	 *
	 * @param int    $id     Review ID.
	 * @param string $status approved|pending|rejected.
	 * @return bool
	 */
	public static function set_status( int $id, string $status ): bool {
		if ( ! in_array( $status, array( 'approved', 'pending', 'rejected' ), true ) ) {
			return false;
		}
		$global = $GLOBALS['wpdb'];
		$row    = self::get_by_id( $id );
		if ( ! $row ) {
			return false;
		}
		$updated = $global->update( self::tbl(), array( 'status' => $status ), array( 'id' => $id ) );
		self::recalc_tour_meta( (int) $row->tour_id );
		return (bool) $updated;
	}

	/**
	 * Delete a review.
	 *
	 * @param int $id Review ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		$global = $GLOBALS['wpdb'];
		$row    = self::get_by_id( $id );
		if ( ! $row ) {
			return false;
		}
		$deleted = $global->delete( self::tbl(), array( 'id' => $id ) );
		self::recalc_tour_meta( (int) $row->tour_id );
		return (bool) $deleted;
	}

	/**
	 * Recalculate cached rating meta on a tour (for sorting/display).
	 *
	 * @param int $tour_id Tour post ID.
	 * @return void
	 */
	public static function recalc_tour_meta( int $tour_id ): void {
		$summary = self::summary( $tour_id );
		update_post_meta( $tour_id, '_tg_rating_avg', $summary['avg'] );
		update_post_meta( $tour_id, '_tg_rating_count', $summary['count'] );
	}

	/**
	 * Pending review queue (admin).
	 *
	 * @param int $per_page Page size.
	 * @param int $page     Page number.
	 * @return array{items:object[],total:int}
	 */
	public static function list_pending( int $per_page = 20, int $page = 1 ): array {
		$global   = $GLOBALS['wpdb'];
		$per_page = max( 1, min( 100, $per_page ) );
		$page     = max( 1, $page );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $global->get_var( 'SELECT COUNT(*) FROM ' . self::tbl() . ' WHERE status = %s', 'pending' );
		$items = $global->get_results(
			$global->prepare( 'SELECT * FROM ' . self::tbl() . ' WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d', 'pending', $per_page, $offset )
		);

		return array(
			'items' => (array) $items,
			'total' => $total,
		);
	}
}
