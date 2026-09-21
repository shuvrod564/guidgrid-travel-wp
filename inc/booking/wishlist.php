<?php
/**
 * Wishlist (logged-in users).
 *
 * Stored as user meta (list of tour IDs). Guests use browser local
 * storage handled client-side — no private data is exposed through
 * AJAX for guests.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TG_Wishlist
 */
final class TG_Wishlist {

	const META_KEY = '_tg_wishlist';

	/**
	 * Get wishlist tour IDs for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	public static function get( int $user_id ): array {
		if ( ! $user_id ) {
			return array();
		}
		$items = get_user_meta( $user_id, self::META_KEY, true );
		return array_map( 'absint', (array) $items );
	}

	/**
	 * Whether a tour is in the user's wishlist.
	 *
	 * @param int $user_id  User ID.
	 * @param int $tour_id  Tour post ID.
	 * @return bool
	 */
	public static function has( int $user_id, int $tour_id ): bool {
		return in_array( $tour_id, self::get( $user_id ), true );
	}

	/**
	 * Toggle a tour in the wishlist.
	 *
	 * @param int $user_id  User ID.
	 * @param int $tour_id  Tour post ID.
	 * @return array{added:bool,count:int}
	 */
	public static function toggle( int $user_id, int $tour_id ): array {
		$items = self::get( $user_id );

		if ( in_array( $tour_id, $items, true ) ) {
			$items = array_values( array_diff( $items, array( $tour_id ) ) );
			$added = false;
		} else {
			$items[] = $tour_id;
			$added   = true;
		}

		update_user_meta( $user_id, self::META_KEY, $items );

		return array(
			'added' => $added,
			'count' => count( $items ),
		);
	}

	/**
	 * Count items in a user's wishlist.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function count( int $user_id ): int {
		return count( self::get( $user_id ) );
	}
}
