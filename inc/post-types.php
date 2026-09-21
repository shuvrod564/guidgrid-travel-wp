<?php
/**
 * Custom post types, taxonomies and term meta.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_register_post_types' ) ) {
	/**
	 * Register CPTs.
	 *
	 * @return void
	 */
	function tg_register_post_types() {
		register_post_type(
			'tour',
			array(
				'labels'       => array(
					'name'               => __( 'Tours', 'guidegrid-travel' ),
					'singular_name'      => __( 'Tour', 'guidegrid-travel' ),
					'add_new'            => __( 'Add New Tour', 'guidegrid-travel' ),
					'add_new_item'       => __( 'Add New Tour', 'guidegrid-travel' ),
					'edit_item'          => __( 'Edit Tour', 'guidegrid-travel' ),
					'new_item'           => __( 'New Tour', 'guidegrid-travel' ),
					'view_item'          => __( 'View Tour', 'guidegrid-travel' ),
					'search_items'       => __( 'Search Tours', 'guidegrid-travel' ),
					'not_found'          => __( 'No tours found.', 'guidegrid-travel' ),
					'not_found_in_trash' => __( 'No tours found in Trash.', 'guidegrid-travel' ),
					'menu_name'          => __( 'Tours', 'guidegrid-travel' ),
				),
				'public'       => true,
				'has_archive'  => 'tours',
				'rewrite'      => array(
					'slug'       => 'tours',
					'with_front' => false,
				),
				'menu_icon'    => 'dashicons-compass',
				'menu_position' => 25,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author' ),
				'show_in_rest' => true,
			)
		);

		register_post_type(
			'destination',
			array(
				'labels'       => array(
					'name'          => __( 'Destinations', 'guidegrid-travel' ),
					'singular_name' => __( 'Destination', 'guidegrid-travel' ),
					'add_new'       => __( 'Add New Destination', 'guidegrid-travel' ),
					'edit_item'     => __( 'Edit Destination', 'guidegrid-travel' ),
					'menu_name'     => __( 'Destinations', 'guidegrid-travel' ),
				),
				'public'        => true,
				'has_archive'   => 'destinations',
				'rewrite'       => array(
					'slug'       => 'destinations',
					'with_front' => false,
				),
				'menu_icon'     => 'dashicons-location-alt',
				'menu_position' => 26,
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'show_in_rest'  => true,
			)
		);

		register_post_type(
			'travel_guide',
			array(
				'labels'       => array(
					'name'          => __( 'Travel Guides', 'guidegrid-travel' ),
					'singular_name' => __( 'Travel Guide', 'guidegrid-travel' ),
					'add_new'       => __( 'Add New Travel Guide', 'guidegrid-travel' ),
					'edit_item'     => __( 'Edit Travel Guide', 'guidegrid-travel' ),
					'menu_name'     => __( 'Travel Guides', 'guidegrid-travel' ),
				),
				'public'        => true,
				'has_archive'   => 'travel-guides',
				'rewrite'       => array(
					'slug'       => 'travel-guides',
					'with_front' => false,
				),
				'menu_icon'     => 'dashicons-book-alt',
				'menu_position' => 27,
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author' ),
				'show_in_rest'  => true,
			)
		);

		register_post_type(
			'tg_addon',
			array(
				'labels'       => array(
					'name'          => __( 'Tour Add-ons', 'guidegrid-travel' ),
					'singular_name' => __( 'Add-on', 'guidegrid-travel' ),
					'add_new'       => __( 'Add New Add-on', 'guidegrid-travel' ),
					'edit_item'     => __( 'Edit Add-on', 'guidegrid-travel' ),
					'menu_name'     => __( 'Add-ons', 'guidegrid-travel' ),
				),
				'public'        => false,
				'show_ui'       => true,
				'show_in_menu'  => 'tg-bookings',
				'show_in_rest'  => true,
				'supports'      => array( 'title', 'editor', 'thumbnail' ),
			)
		);
	}
}
add_action( 'init', 'tg_register_post_types' );

if ( ! function_exists( 'tg_register_taxonomies' ) ) {
	/**
	 * Register taxonomies.
	 *
	 * @return void
	 */
	function tg_register_taxonomies() {
		register_taxonomy(
			'tour_category',
			'tour',
			array(
				'labels'            => array(
					'name'          => __( 'Tour Categories', 'guidegrid-travel' ),
					'singular_name' => __( 'Tour Category', 'guidegrid-travel' ),
					'search_items'  => __( 'Search Tour Categories', 'guidegrid-travel' ),
					'all_items'     => __( 'All Tour Categories', 'guidegrid-travel' ),
					'edit_item'     => __( 'Edit Tour Category', 'guidegrid-travel' ),
					'add_new_item'  => __( 'Add New Tour Category', 'guidegrid-travel' ),
					'menu_name'     => __( 'Tour Categories', 'guidegrid-travel' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'       => 'tour-category',
					'with_front' => false,
				),
			)
		);

		register_taxonomy(
			'activity',
			array( 'tour', 'travel_guide' ),
			array(
				'labels'            => array(
					'name'          => __( 'Activities', 'guidegrid-travel' ),
					'singular_name' => __( 'Activity', 'guidegrid-travel' ),
					'search_items'  => __( 'Search Activities', 'guidegrid-travel' ),
					'all_items'     => __( 'All Activities', 'guidegrid-travel' ),
					'edit_item'     => __( 'Edit Activity', 'guidegrid-travel' ),
					'add_new_item'  => __( 'Add New Activity', 'guidegrid-travel' ),
					'menu_name'     => __( 'Activities', 'guidegrid-travel' ),
				),
				'hierarchical'      => false,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'       => 'activities',
					'with_front' => false,
				),
			)
		);
	}
}
add_action( 'init', 'tg_register_taxonomies' );

if ( ! function_exists( 'tg_register_term_meta_boxes' ) ) {
	/**
	 * Term meta boxes for tour_category and activity.
	 *
	 * @return void
	 */
	function tg_register_term_meta_boxes() {
		foreach ( array( 'tour_category', 'activity' ) as $taxonomy ) {
			add_meta_box(
				'tg-term-meta',
				__( 'Additional Information', 'guidegrid-travel' ),
				'tg_render_term_meta_box',
				$taxonomy,
				'side'
			);
		}
	}
}
add_action( 'add_meta_boxes', 'tg_register_term_meta_boxes' );

if ( ! function_exists( 'tg_render_term_meta_box' ) ) {
	/**
	 * Render term meta box.
	 *
	 * @param WP_Term $term Term.
	 * @return void
	 */
	function tg_render_term_meta_box( WP_Term $term ) {
		wp_nonce_field( 'tg_term_meta', 'tg_term_meta_nonce' );
		$image = get_term_meta( $term->term_id, '_tg_term_image_url', true );
		$icon  = get_term_meta( $term->term_id, '_tg_term_icon', true );
		?>
		<p>
			<label for="tg-term-image" class="tg-label"><?php esc_html_e( 'Image URL (optional)', 'guidegrid-travel' ); ?></label>
			<input type="url" id="tg-term-image" name="tg_term_image_url" class="widefat" value="<?php echo esc_attr( $image ); ?>" placeholder="https://…" />
		</p>
		<p>
			<label for="tg-term-icon" class="tg-label"><?php esc_html_e( 'Icon (emoji or short text, optional)', 'guidegrid-travel' ); ?></label>
			<input type="text" id="tg-term-icon" name="tg_term_icon" class="widefat" value="<?php echo esc_attr( $icon ); ?>" placeholder="🏔️" />
		</p>
		<?php
	}
}

if ( ! function_exists( 'tg_save_term_meta' ) ) {
	/**
	 * Save term meta (both taxonomies).
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	function tg_save_term_meta( int $term_id, string $taxonomy ) {
		if ( ! in_array( $taxonomy, array( 'tour_category', 'activity' ), true ) ) {
			return;
		}
		if ( ! isset( $_POST['tg_term_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tg_term_meta_nonce'] ), 'tg_term_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_terms' ) ) {
			return;
		}

		$image = isset( $_POST['tg_term_image_url'] ) ? esc_url_raw( wp_unslash( $_POST['tg_term_image_url'] ) ) : '';
		$icon  = isset( $_POST['tg_term_icon'] ) ? sanitize_text_field( wp_unslash( $_POST['tg_term_icon'] ) ) : '';

		update_term_meta( $term_id, '_tg_term_image_url', $image );
		update_term_meta( $term_id, '_tg_term_icon', mb_substr( $icon, 0, 8 ) );
	}
}
add_action( 'edited_tour_category', 'tg_save_term_meta', 10, 2 );
add_action( 'create_tour_category', 'tg_save_term_meta', 10, 2 );
add_action( 'edited_activity', 'tg_save_term_meta', 10, 2 );
add_action( 'create_activity', 'tg_save_term_meta', 10, 2 );

if ( ! function_exists( 'tg_term_icon' ) ) {
	/**
	 * Term icon (meta first, default fallback).
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	function tg_term_icon( int $term_id ): string {
		$icon  = get_term_meta( $term_id, '_tg_term_icon', true );
		$fallbacks = array(
			'adventure' => '🧗',
			'beach'     => '🏖️',
			'cultural'  => '🏛️',
			'family'    => '👨‍👩‍👧',
			'honeymoon' => '💑',
			'luxury'    => '💎',
			'wildlife'  => '🦁',
			'mountain'  => '🏔️',
			'city'      => '🏙️',
			'nature'    => '🌿',
			'wellness'  => '🧘',
			'cruise'    => '🛳️',
			'food'      => '🍜',
			'photography' => '📷',
			'religious' => '🕌',
			'hiking'    => '🥾',
			'snorkeling' => '🤿',
			'scuba'     => '🐠',
			'safari'    => '🦓',
			'camping'   => '⛺',
			'boat'      => '⛵',
			'walking'   => '🚶',
		);
		if ( $icon ) {
			return $icon;
		}
		$slug = get_term( $term_id )->slug ?? '';
		foreach ( $fallbacks as $key => $emoji ) {
			if ( strpos( $slug, $key ) !== false ) {
				return $emoji;
			}
		}
		return '🌍';
	}
}
