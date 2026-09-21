<?php
/**
 * Add-on helpers.
 *
 * Add-ons are a private CPT (tg_addon). Each add-on stores price, pricing
 * unit, taxability and status in post meta and is attached to tours via the
 * tour's `_tg_addons` meta (list of add-on post IDs).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TG_Addons
 */
final class TG_Addons {

	/**
	 * Get a single add-on with its meta.
	 *
	 * @param int $addon_id Add-on post ID.
	 * @return array|null { id, name, description, image, price, unit, taxable, status }
	 */
	public static function get( int $addon_id ): ?array {
		$post = $addon_id ? get_post( $addon_id ) : null;
		if ( ! $post || 'tg_addon' !== $post->post_type ) {
			return null;
		}

		return array(
			'id'          => $addon_id,
			'name'        => $post->post_title,
			'description' => wp_strip_all_tags( (string) $post->post_content ),
			'image'       => get_the_post_thumbnail_url( $post, 'thumbnail' ),
			'price'       => (float) get_post_meta( $addon_id, '_tg_addon_price', true ),
			'unit'        => get_post_meta( $addon_id, '_tg_addon_unit', true ) ? get_post_meta( $addon_id, '_tg_addon_unit', true ) : 'per_person',
			'taxable'     => (bool) get_post_meta( $addon_id, '_tg_addon_taxable', true ),
			'status'      => $post->post_status,
		);
	}

	/**
	 * Add-ons available for a tour.
	 *
	 * @param int $tour_id Tour post ID.
	 * @return array[]
	 */
	public static function get_for_tour( int $tour_id ): array {
		$ids   = array_map( 'absint', (array) tg_get_meta( $tour_id, '_tg_addons', array() ) );
		$items = array();
		foreach ( $ids as $id ) {
			$addon = self::get( $id );
			if ( $addon && 'publish' === $addon['status'] ) {
				$items[] = $addon;
			}
		}
		return $items;
	}

	/**
	 * All published add-ons (admin selects).
	 *
	 * @param int $limit Max results.
	 * @return array[]
	 */
	public static function all_published( int $limit = 100 ): array {
		$query = new WP_Query(
			array(
				'post_type'      => 'tg_addon',
				'posts_per_page' => $limit,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = self::get( $post->ID );
		}
		return $items;
	}

	/**
	 * Localized label for a pricing unit.
	 *
	 * @param string $unit Unit key.
	 * @return string
	 */
	public static function unit_label( string $unit ): string {
		$labels = array(
			'per_person'  => __( 'Per person', 'guidegrid-travel' ),
			'per_booking' => __( 'Per booking', 'guidegrid-travel' ),
			'per_day'     => __( 'Per day', 'guidegrid-travel' ),
			'per_night'   => __( 'Per night', 'guidegrid-travel' ),
			'per_vehicle' => __( 'Per vehicle', 'guidegrid-travel' ),
		);
		return $labels[ $unit ] ?? $unit;
	}
}
