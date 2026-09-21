<?php
/**
 * Meta boxes for tour, destination, travel guide and add-on.
 *
 * All inputs are sanitized on save; repeaters (itinerary, FAQ) support
 * dynamic rows on the frontend of the edit screen (assets/js/admin.js).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * Registration
 * ========================================================================= */

if ( ! function_exists( 'tg_register_meta_boxes' ) ) {
	/**
	 * Register all meta boxes.
	 *
	 * @return void
	 */
	function tg_register_meta_boxes() {
		$fields_meta = array(
			'tour'          => array( 'tg_tour_details_box', 'tg_tour_pricing_box', 'tg_tour_availability_box', 'tg_tour_itinerary_box', 'tg_tour_content_box', 'tg_tour_addons_box' ),
			'destination'   => array( 'tg_destination_box' ),
			'travel_guide'  => array( 'tg_guide_box' ),
			'tg_addon'      => array( 'tg_addon_box' ),
		);

		foreach ( $fields_meta as $post_type => $boxes ) {
			$titles = array(
				'tg_tour_details_box'      => __( 'Tour Details', 'guidegrid-travel' ),
				'tg_tour_pricing_box'      => __( 'Pricing', 'guidegrid-travel' ),
				'tg_tour_availability_box' => __( 'Availability', 'guidegrid-travel' ),
				'tg_tour_itinerary_box'    => __( 'Itinerary', 'guidegrid-travel' ),
				'tg_tour_content_box'      => __( 'Highlights, Included & Policies', 'guidegrid-travel' ),
				'tg_tour_addons_box'       => __( 'Add-ons', 'guidegrid-travel' ),
				'tg_destination_box'       => __( 'Destination Details', 'guidegrid-travel' ),
				'tg_guide_box'             => __( 'Guide Details', 'guidegrid-travel' ),
				'tg_addon_box'             => __( 'Add-on Details', 'guidegrid-travel' ),
			);
			$defaults = array(
				'tg_tour_details_box'      => 'normal',
				'tg_tour_pricing_box'      => 'normal',
				'tg_tour_availability_box' => 'normal',
				'tg_tour_itinerary_box'    => 'normal',
				'tg_tour_content_box'      => 'normal',
				'tg_tour_addons_box'       => 'side',
				'tg_destination_box'       => 'normal',
				'tg_guide_box'             => 'side',
				'tg_addon_box'             => 'side',
			);

			foreach ( $boxes as $callback ) {
				add_meta_box(
					'tg_' . $post_type . '_' . $callback,
					$titles[ $callback ] ?? $callback,
					$callback,
					$post_type,
					$defaults[ $callback ] ?? 'normal',
					'default'
				);
			}
		}
	}
}
add_action( 'add_meta_boxes', 'tg_register_meta_boxes' );

/* =========================================================================
 * Small render helpers
 * ========================================================================= */

if ( ! function_exists( 'tg_mb_text' ) ) {
	/**
	 * Render a text input row.
	 *
	 * @param array $args { key, label, value, placeholder, type }.
	 * @return void
	 */
	function tg_mb_text( array $args ) {
		$key   = $args['key'];
		$value = isset( $args['value'] ) ? $args['value'] : '';
		$type  = isset( $args['type'] ) && in_array( $args['type'], array( 'text', 'url', 'number', 'email', 'tel', 'date' ), true ) ? $args['type'] : 'text';
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>" class="tg-label"><?php echo esc_html( $args['label'] ); ?></label><br />
			<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat" value="<?php echo esc_attr( $value ); ?>" <?php echo ! empty( $args['placeholder'] ) ? 'placeholder="' . esc_attr( $args['placeholder'] ) . '"' : ''; ?> />
			<?php if ( ! empty( $args['hint'] ) ) : ?>
				<span class="description"><?php echo esc_html( $args['hint'] ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}
}

if ( ! function_exists( 'tg_mb_select' ) ) {
	/**
	 * Render a select row.
	 *
	 * @param array $args { key, label, value, options, hint }.
	 * @return void
	 */
	function tg_mb_select( array $args ) {
		$key   = $args['key'];
		$value = isset( $args['value'] ) ? $args['value'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>" class="tg-label"><?php echo esc_html( $args['label'] ); ?></label><br />
			<select id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat">
				<?php foreach ( $args['options'] as $opt_value => $opt_label ) : ?>
					<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $value, $opt_value ); ?>><?php echo esc_html( $opt_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( ! empty( $args['hint'] ) ) : ?>
				<span class="description"><?php echo esc_html( $args['hint'] ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}
}

if ( ! function_exists( 'tg_mb_textarea' ) ) {
	/**
	 * Render a textarea row.
	 *
	 * @param array $args { key, label, value, rows, hint, lines }.
	 * @return void
	 */
	function tg_mb_textarea( array $args ) {
		$key   = $args['key'];
		$value = isset( $args['value'] ) ? $args['value'] : '';
		$rows  = isset( $args['rows'] ) ? (int) $args['rows'] : 3;
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>" class="tg-label"><?php echo esc_html( $args['label'] ); ?></label><br />
			<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat" rows="<?php echo esc_attr( $rows ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
			<?php if ( ! empty( $args['hint'] ) ) : ?>
				<span class="description"><?php echo esc_html( $args['hint'] ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}
}

if ( ! function_exists( 'tg_mb_lines_field' ) ) {
	/**
	 * Render a one-item-per-line list field.
	 *
	 * @param array $args { key, label, lines (string[]), hint, rows }.
	 * @return void
	 */
	function tg_mb_lines_field( array $args ) {
		$key   = $args['key'];
		$value = isset( $args['lines'] ) ? implode( "\n", $args['lines'] ) : '';
		$rows  = isset( $args['rows'] ) ? (int) $args['rows'] : 4;
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>" class="tg-label"><?php echo esc_html( $args['label'] ); ?></label><br />
			<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat" rows="<?php echo esc_attr( $rows ); ?>" placeholder="<?php esc_attr_e( 'One item per line', 'guidegrid-travel' ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
			<?php if ( ! empty( $args['hint'] ) ) : ?>
				<span class="description"><?php echo esc_html( $args['hint'] ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}
}

if ( ! function_exists( 'tg_mb_lines' ) ) {
	/**
	 * Read a one-item-per-line meta field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string[]
	 */
	function tg_mb_lines( int $post_id, string $key ): array {
		$raw = (string) get_post_meta( $post_id, $key, true );
		if ( '' === $raw ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
	}
}

if ( ! function_exists( 'tg_mb_save_lines' ) ) {
	/**
	 * Save a one-item-per-line meta field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param string $raw     Raw textarea value.
	 * @return void
	 */
	function tg_mb_save_lines( int $post_id, string $key, string $raw ): void {
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
		if ( $lines ) {
			update_post_meta( $post_id, $key, implode( "\n", $lines ) );
		} else {
			delete_post_meta( $post_id, $key );
		}
	}
}

/* =========================================================================
 * Tour meta boxes
 * ========================================================================= */

if ( ! function_exists( 'tg_tour_details_box' ) ) {
	/**
	 * Tour details: location, basics.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	function tg_tour_details_box( WP_Post $post ) {
		wp_nonce_field( 'tg_tour_meta', 'tg_tour_meta_nonce' );
		$destinations = get_posts(
			array(
				'post_type'      => 'destination',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$dest_options = array( '' => __( '— Select destination —', 'guidegrid-travel' ) );
		foreach ( $destinations as $dest ) {
			$dest_options[ $dest->ID ] = $dest->post_title;
		}

		$days = array();
		for ( $i = 1; $i <= 30; $i++ ) {
			$days[ $i ] = $i;
		}

		$settings = tg_settings();
		$currencies = array( 'USD', 'EUR', 'GBP', 'BDT', 'AED', 'INR', 'JPY', 'CAD', 'AUD', 'CHF' );

		echo '<div class="tg-mb-grid">';

		tg_mb_text( array( 'key' => '_tg_subtitle', 'label' => __( 'Subtitle', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_subtitle', true ) ) );
		tg_mb_select( array(
			'key'     => '_tg_destination_id',
			'label'   => __( 'Destination', 'guidegrid-travel' ),
			'value'   => (string) get_post_meta( $post->ID, '_tg_destination_id', true ),
			'options' => $dest_options,
		) );
		tg_mb_text( array( 'key' => '_tg_country', 'label' => __( 'Country', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_country', true ) ) );
		tg_mb_text( array( 'key' => '_tg_region', 'label' => __( 'Region', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_region', true ) ) );
		tg_mb_text( array( 'key' => '_tg_pickup_point', 'label' => __( 'Pickup Point', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_pickup_point', true ) ) );
		tg_mb_text( array( 'key' => '_tg_dropoff_point', 'label' => __( 'Drop-off Point', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_dropoff_point', true ) ) );
		tg_mb_text( array( 'key' => '_tg_meeting_point', 'label' => __( 'Meeting Point', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_meeting_point', true ) ) );
		tg_mb_text( array( 'key' => '_tg_lat', 'label' => __( 'Latitude', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_lat', true ), 'type' => 'text' ) );
		tg_mb_text( array( 'key' => '_tg_lng', 'label' => __( 'Longitude', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_lng', true ), 'type' => 'text' ) );
		tg_mb_text( array( 'key' => '_tg_video_url', 'label' => __( 'Gallery Video URL (optional)', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_video_url', true ), 'type' => 'url' ) );

		tg_mb_text( array( 'key' => '_tg_duration', 'label' => __( 'Duration', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_duration', true ), 'type' => 'number' ) );
		tg_mb_select( array(
			'key'     => '_tg_duration_unit',
			'label'   => __( 'Duration Unit', 'guidegrid-travel' ),
			'value'   => get_post_meta( $post->ID, '_tg_duration_unit', true ) ? get_post_meta( $post->ID, '_tg_duration_unit', true ) : 'days',
			'options' => array(
				'days'  => __( 'Days', 'guidegrid-travel' ),
				'hours' => __( 'Hours', 'guidegrid-travel' ),
			),
		) );
		tg_mb_select( array(
			'key'     => '_tg_tour_type',
			'label'   => __( 'Tour Type', 'guidegrid-travel' ),
			'value'   => get_post_meta( $post->ID, '_tg_tour_type', true ) ? get_post_meta( $post->ID, '_tg_tour_type', true ) : 'multi_day',
			'options' => array(
				'day_tour'    => __( 'Day Tour', 'guidegrid-travel' ),
				'multi_day'   => __( 'Multi-Day Tour', 'guidegrid-travel' ),
				'private'     => __( 'Private Tour', 'guidegrid-travel' ),
				'group'       => __( 'Group Tour', 'guidegrid-travel' ),
				'custom'      => __( 'Custom', 'guidegrid-travel' ),
			),
		) );
		tg_mb_select( array(
			'key'     => '_tg_tour_mode',
			'label'   => __( 'Group / Private', 'guidegrid-travel' ),
			'value'   => get_post_meta( $post->ID, '_tg_tour_mode', true ) ? get_post_meta( $post->ID, '_tg_tour_mode', true ) : 'group',
			'options' => array(
				'group'   => __( 'Group', 'guidegrid-travel' ),
				'private' => __( 'Private', 'guidegrid-travel' ),
				'both'    => __( 'Group & Private', 'guidegrid-travel' ),
			),
		) );
		tg_mb_text( array( 'key' => '_tg_min_group', 'label' => __( 'Minimum Group Size', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_min_group', true ), 'type' => 'number' ) );
		tg_mb_text( array( 'key' => '_tg_max_group', 'label' => __( 'Maximum Group Size', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_max_group', true ), 'type' => 'number' ) );
		tg_mb_text( array( 'key' => '_tg_min_age', 'label' => __( 'Minimum Age', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_min_age', true ), 'type' => 'number' ) );
		tg_mb_select( array(
			'key'     => '_tg_difficulty',
			'label'   => __( 'Difficulty', 'guidegrid-travel' ),
			'value'   => get_post_meta( $post->ID, '_tg_difficulty', true ) ? get_post_meta( $post->ID, '_tg_difficulty', true ) : 'easy',
			'options' => array(
				'easy'        => __( 'Easy', 'guidegrid-travel' ),
				'moderate'    => __( 'Moderate', 'guidegrid-travel' ),
				'challenging' => __( 'Challenging', 'guidegrid-travel' ),
			),
		) );
		tg_mb_text( array( 'key' => '_tg_language', 'label' => __( 'Language(s)', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_language', true ), 'placeholder' => 'English, Bangla' ) );
		tg_mb_text( array( 'key' => '_tg_guide', 'label' => __( 'Lead Guide (optional)', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_guide', true ) ) );
		tg_mb_select( array(
			'key'     => '_tg_featured',
			'label'   => __( 'Featured Tour', 'guidegrid-travel' ),
			'value'   => get_post_meta( $post->ID, '_tg_featured', true ) ? '1' : '0',
			'options' => array(
				'0' => __( 'No', 'guidegrid-travel' ),
				'1' => __( 'Yes', 'guidegrid-travel' ),
			),
		) );

		echo '</div>';
	}
}

if ( ! function_exists( 'tg_tour_pricing_box' ) ) {
	/**
	 * Tour pricing box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	function tg_tour_pricing_box( WP_Post $post ) {
		$settings   = tg_settings();
		$currencies = array( 'USD', 'EUR', 'GBP', 'BDT', 'AED', 'INR', 'JPY', 'CAD', 'AUD', 'CHF' );
		$currency   = get_post_meta( $post->ID, '_tg_currency', true ) ? get_post_meta( $post->ID, '_tg_currency', true ) : $settings['currency'];

		echo '<div class="tg-mb-grid">';

		tg_mb_text( array( 'key' => '_tg_adult_price', 'label' => __( 'Adult Price (base)', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_adult_price', true ), 'type' => 'number', 'hint' => __( 'Used when no pricing rule matches.', 'guidegrid-travel' ) ) );
		tg_mb_text( array( 'key' => '_tg_child_price', 'label' => __( 'Child Price', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_child_price', true ), 'type' => 'number' ) );
		tg_mb_text( array( 'key' => '_tg_infant_price', 'label' => __( 'Infant Price', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_infant_price', true ), 'type' => 'number' ) );
		tg_mb_select( array(
			'key'     => '_tg_currency',
			'label'   => __( 'Currency', 'guidegrid-travel' ),
			'value'   => $currency,
			'options' => array_combine( $currencies, $currencies ),
		) );
		tg_mb_text( array( 'key' => '_tg_previous_price', 'label' => __( 'Previous Price (strikethrough)', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_previous_price', true ), 'type' => 'number' ) );
		tg_mb_text( array( 'key' => '_tg_deposit_percent', 'label' => __( 'Deposit % (0 = none)', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_deposit_percent', true ), 'type' => 'number' ) );
		tg_mb_text( array( 'key' => '_tg_tax_percent', 'label' => __( 'Tax % (blank = theme default)', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_tax_percent', true ), 'type' => 'number' ) );
		tg_mb_text( array( 'key' => '_tg_service_fee', 'label' => __( 'Service Fee (flat, blank = theme default)', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_service_fee', true ), 'type' => 'number' ) );

		echo '</div>';
	}
}

if ( ! function_exists( 'tg_tour_availability_box' ) ) {
	/**
	 * Tour availability box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	function tg_tour_availability_box( WP_Post $post ) {
		$mode = get_post_meta( $post->ID, '_tg_availability_mode', true ) ? get_post_meta( $post->ID, '_tg_availability_mode', true ) : 'any';
		$days = array(
			'1' => __( 'Monday', 'guidegrid-travel' ),
			'2' => __( 'Tuesday', 'guidegrid-travel' ),
			'3' => __( 'Wednesday', 'guidegrid-travel' ),
			'4' => __( 'Thursday', 'guidegrid-travel' ),
			'5' => __( 'Friday', 'guidegrid-travel' ),
			'6' => __( 'Saturday', 'guidegrid-travel' ),
			'0' => __( 'Sunday', 'guidegrid-travel' ),
		);
		$weekly = (array) get_post_meta( $post->ID, '_tg_weekly_days', true );

		tg_mb_select( array(
			'key'     => '_tg_availability_mode',
			'label'   => __( 'Availability Mode', 'guidegrid-travel' ),
			'value'   => $mode,
			'options' => array(
				'fixed' => __( 'Fixed dates only', 'guidegrid-travel' ),
				'range' => __( 'Date range', 'guidegrid-travel' ),
				'weekly' => __( 'Weekly (specific days)', 'guidegrid-travel' ),
				'any'   => __( 'Every day', 'guidegrid-travel' ),
			),
			'hint'    => __( 'Fixed: list exact dates. Range: start–end window. Weekly: repeat on selected days.', 'guidegrid-travel' ),
		) );

		tg_mb_lines_field( array(
			'key'   => '_tg_availability_dates',
			'label' => __( 'Fixed Dates (YYYY-MM-DD, one per line)', 'guidegrid-travel' ),
			'lines' => tg_mb_lines( $post->ID, '_tg_availability_dates' ),
			'rows'  => 5,
		) );

		echo '<div class="tg-mb-grid">';
		tg_mb_text( array( 'key' => '_tg_range_start', 'label' => __( 'Range Start (YYYY-MM-DD)', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_range_start', true ), 'type' => 'text' ) );
		tg_mb_text( array( 'key' => '_tg_range_end', 'label' => __( 'Range End (YYYY-MM-DD)', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_range_end', true ), 'type' => 'text' ) );
		echo '</div>';

		echo '<p><label class="tg-label">' . esc_html__( 'Weekly Days', 'guidegrid-travel' ) . '</label>';
		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Weekly Days', 'guidegrid-travel' ) . '</legend>';
		foreach ( $days as $day_key => $day_label ) {
			printf(
				'<label style="display:inline-block;margin-right:14px;margin-bottom:6px;"><input type="checkbox" name="tg_weekly_days[]" value="%s" %s /> %s</label>',
				esc_attr( $day_key ),
				checked( in_array( $day_key, array_map( 'strval', $weekly ), true ), true, false ),
				esc_html( $day_label )
			);
		}
		echo '</fieldset></p>';

		echo '<div class="tg-mb-grid">';
		tg_mb_text( array( 'key' => '_tg_capacity', 'label' => __( 'Capacity per Date (0 = unlimited)', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_capacity', true ), 'type' => 'number' ) );
		tg_mb_text( array( 'key' => '_tg_min_booking', 'label' => __( 'Min Booking Quantity', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_min_booking', true ), 'type' => 'number' ) );
		tg_mb_text( array( 'key' => '_tg_max_booking', 'label' => __( 'Max Booking Quantity (0 = capacity)', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_max_booking', true ), 'type' => 'number' ) );
		echo '</div>';

		tg_mb_lines_field( array(
			'key'   => '_tg_blackout_dates',
			'label' => __( 'Blackout Dates (YYYY-MM-DD, one per line)', 'guidegrid-travel' ),
			'lines' => tg_mb_lines( $post->ID, '_tg_blackout_dates' ),
			'rows'  => 4,
		) );
	}
}

if ( ! function_exists( 'tg_repeater' ) ) {
	/**
	 * Render a repeater field.
	 *
	 * @param array $args {
	 *   name, rows (array of row arrays), fields (key => label/type),
	 *   add_label, default_row
	 * }
	 * @return void
	 */
	function tg_repeater( array $args ) {
		$name  = $args['name'];
		$rows  = isset( $args['rows'] ) ? $args['rows'] : array();
		$fields = $args['fields'];

		echo '<div class="tg-repeater" data-tg-repeater="' . esc_attr( $name ) . '">';

		foreach ( $fields as $field_key => $field_def ) {
			echo '<span class="tg-rep-label">' . esc_html( $field_def['label'] ) . ' — </span>';
		}
		echo '<span class="description" style="display:block;margin-bottom:8px;">' . esc_html__( 'Click a row to edit. Use “Add row” below.', 'guidegrid-travel' ) . '</span>';

		if ( empty( $rows ) ) {
			$rows = array( $args['default_row'] );
		}

		$i = 0;
		foreach ( $rows as $row ) {
			$i++;
			echo '<div class="tg-rep-row" data-index="' . esc_attr( (string) $i ) . '">';
			echo '<div class="tg-rep-row-head"><strong>' . esc_html( sprintf( /* translators: %d: row number */ __( 'Row %d', 'guidegrid-travel' ), $i ) ) . '</strong><button type="button" class="button button-small tg-rep-remove" aria-label="' . esc_attr__( 'Remove row', 'guidegrid-travel' ) . '">×</button></div>';
			echo '<div class="tg-rep-fields">';
			foreach ( $fields as $field_key => $field_def ) {
				$value = isset( $row[ $field_key ] ) ? $row[ $field_key ] : '';
				$input_name = $name . '[' . $i . '][' . $field_key . ']';
				if ( 'textarea' === $field_def['type'] ) {
					echo '<p><label class="tg-label">' . esc_html( $field_def['label'] ) . '</label><textarea class="widefat tg-rep-value" name="' . esc_attr( $input_name ) . '" rows="3">' . esc_textarea( $value ) . '</textarea></p>';
				} else {
					echo '<p><label class="tg-label">' . esc_html( $field_def['label'] ) . '</label><input type="text" class="widefat tg-rep-value" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '" /></p>';
				}
			}
			echo '</div></div>';
		}

		$default_json = wp_json_encode( $args['default_row'] );
		echo '<p><button type="button" class="button button-primary tg-rep-add" data-defaults="' . esc_attr( $default_json ) . '">' . esc_html( $args['add_label'] ) . '</button></p>';
		echo '</div>';
	}
}

if ( ! function_exists( 'tg_tour_itinerary_box' ) ) {
	/**
	 * Tour itinerary repeater.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	function tg_tour_itinerary_box( WP_Post $post ) {
		$itinerary = (array) get_post_meta( $post->ID, '_tg_itinerary', true );
		$fields    = array(
			'title'         => array( 'label' => __( 'Day Title', 'guidegrid-travel' ), 'type' => 'text' ),
			'description'   => array( 'label' => __( 'Description', 'guidegrid-travel' ), 'type' => 'textarea' ),
			'meals'         => array( 'label' => __( 'Meals', 'guidegrid-travel' ), 'type' => 'text' ),
			'accommodation' => array( 'label' => __( 'Accommodation', 'guidegrid-travel' ), 'type' => 'text' ),
			'notes'         => array( 'label' => __( 'Notes / Travel distance', 'guidegrid-travel' ), 'type' => 'text' ),
		);
		$default = array(
			'title'         => '',
			'description'   => '',
			'meals'         => '',
			'accommodation' => '',
			'notes'         => '',
		);

		tg_repeater(
			array(
				'name'        => 'tg_itinerary',
				'rows'        => $itinerary,
				'fields'      => $fields,
				'default_row' => $default,
				'add_label'   => __( 'Add Day', 'guidegrid-travel' ),
			)
		);
	}
}

if ( ! function_exists( 'tg_tour_content_box' ) ) {
	/**
	 * Tour content lists + FAQ.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	function tg_tour_content_box( WP_Post $post ) {
		tg_mb_lines_field( array(
			'key'   => '_tg_highlights',
			'label' => __( 'Highlights', 'guidegrid-travel' ),
			'lines' => tg_mb_lines( $post->ID, '_tg_highlights' ),
			'rows'  => 5,
		) );
		tg_mb_lines_field( array(
			'key'   => '_tg_included',
			'label' => __( 'Included', 'guidegrid-travel' ),
			'lines' => tg_mb_lines( $post->ID, '_tg_included' ),
			'rows'  => 6,
		) );
		tg_mb_lines_field( array(
			'key'   => '_tg_excluded',
			'label' => __( 'Not Included', 'guidegrid-travel' ),
			'lines' => tg_mb_lines( $post->ID, '_tg_excluded' ),
			'rows'  => 5,
		) );
		tg_mb_lines_field( array(
			'key'   => '_tg_what_to_bring',
			'label' => __( 'What to Bring', 'guidegrid-travel' ),
			'lines' => tg_mb_lines( $post->ID, '_tg_what_to_bring' ),
			'rows'  => 5,
		) );
		tg_mb_textarea( array(
			'key'   => '_tg_important_info',
			'label' => __( 'Important Information', 'guidegrid-travel' ),
			'value' => get_post_meta( $post->ID, '_tg_important_info', true ),
			'rows'  => 5,
		) );
		tg_mb_textarea( array(
			'key'   => '_tg_cancellation_policy',
			'label' => __( 'Cancellation Policy (package-specific)', 'guidegrid-travel' ),
			'value' => get_post_meta( $post->ID, '_tg_cancellation_policy', true ),
			'rows'  => 5,
			'hint'  => __( 'Shown to customers at checkout and on the tour page.', 'guidegrid-travel' ),
		) );

		$faq = (array) get_post_meta( $post->ID, '_tg_faq', true );
		tg_repeater(
			array(
				'name'        => 'tg_faq',
				'rows'        => $faq,
				'fields'      => array(
					'question' => array( 'label' => __( 'Question', 'guidegrid-travel' ), 'type' => 'text' ),
					'answer'   => array( 'label' => __( 'Answer', 'guidegrid-travel' ), 'type' => 'textarea' ),
				),
				'default_row' => array(
					'question' => '',
					'answer'   => '',
				),
				'add_label'   => __( 'Add FAQ Item', 'guidegrid-travel' ),
			)
		);
	}
}

if ( ! function_exists( 'tg_tour_addons_box' ) ) {
	/**
	 * Add-ons assignment box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	function tg_tour_addons_box( WP_Post $post ) {
		$addons   = TG_Addons::all_published( 200 );
		$selected = (array) get_post_meta( $post->ID, '_tg_addons', true );

		if ( empty( $addons ) ) {
			echo '<p>' . esc_html__( 'No add-ons yet. Create them under “Add-ons” in the menu.', 'guidegrid-travel' ) . '</p>';
			return;
		}

		echo '<p>';
		foreach ( $addons as $addon ) {
			printf(
				'<label style="display:block;margin-bottom:8px;"><input type="checkbox" name="tg_tour_addons[]" value="%1$d" %2$s /> %3$s <span class="description">(%4$s)</span></label>',
				esc_attr( (string) $addon['id'] ),
				checked( in_array( $addon['id'], array_map( 'absint', $selected ), true ), true, false ),
				esc_html( $addon['name'] ),
				esc_html( tg_format_price( $addon['price'] ) . ' ' . TG_Addons::unit_label( $addon['unit'] ) )
			);
		}
		echo '</p>';
	}
}

/* =========================================================================
 * Destination / guide / add-on boxes
 * ========================================================================= */

if ( ! function_exists( 'tg_destination_box' ) ) {
	/**
	 * Destination meta box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	function tg_destination_box( WP_Post $post ) {
		wp_nonce_field( 'tg_destination_meta', 'tg_destination_meta_nonce' );
		echo '<div class="tg-mb-grid">';
		tg_mb_text( array( 'key' => '_tg_country', 'label' => __( 'Country', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_country', true ) ) );
		tg_mb_text( array( 'key' => '_tg_region', 'label' => __( 'Region', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_region', true ) ) );
		tg_mb_text( array( 'key' => '_tg_lat', 'label' => __( 'Latitude', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_lat', true ) ) );
		tg_mb_text( array( 'key' => '_tg_lng', 'label' => __( 'Longitude', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_lng', true ) ) );
		tg_mb_text( array( 'key' => '_tg_best_time', 'label' => __( 'Best Time to Visit', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_best_time', true ) ) );
		tg_mb_text( array( 'key' => '_tg_avg_temp', 'label' => __( 'Average Temperature', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_avg_temp', true ), 'placeholder' => '28°C' ) );
		tg_mb_text( array( 'key' => '_tg_currency', 'label' => __( 'Local Currency', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_currency', true ), 'placeholder' => 'USD' ) );
		tg_mb_text( array( 'key' => '_tg_language', 'label' => __( 'Local Language', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_language', true ) ) );
		tg_mb_text( array( 'key' => '_tg_timezone', 'label' => __( 'Time Zone', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_timezone', true ) ) );
		echo '</div>';
		tg_mb_textarea( array( 'key' => '_tg_travel_info', 'label' => __( 'Travel Information', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_travel_info', true ), 'rows' => 5 ) );
		tg_mb_textarea( array( 'key' => '_tg_safety_info', 'label' => __( 'Safety Information', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_safety_info', true ), 'rows' => 4 ) );
	}
}

if ( ! function_exists( 'tg_guide_box' ) ) {
	/**
	 * Travel guide meta box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	function tg_guide_box( WP_Post $post ) {
		wp_nonce_field( 'tg_guide_meta', 'tg_guide_meta_nonce' );
		$destinations = get_posts(
			array(
				'post_type'      => 'destination',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$options = array( '' => __( '— None —', 'guidegrid-travel' ) );
		foreach ( $destinations as $dest ) {
			$options[ $dest->ID ] = $dest->post_title;
		}

		tg_mb_select( array(
			'key'     => '_tg_guide_destination',
			'label'   => __( 'Destination', 'guidegrid-travel' ),
			'value'   => (string) get_post_meta( $post->ID, '_tg_guide_destination', true ),
			'options' => $options,
		) );
		tg_mb_select( array(
			'key'     => '_tg_guide_type',
			'label'   => __( 'Guide Type', 'guidegrid-travel' ),
			'value'   => get_post_meta( $post->ID, '_tg_guide_type', true ) ? get_post_meta( $post->ID, '_tg_guide_type', true ) : 'planning',
			'options' => array(
				'planning'   => __( 'Trip Planning', 'guidegrid-travel' ),
				'packing'    => __( 'Packing Guide', 'guidegrid-travel' ),
				'food'       => __( 'Food Guide', 'guidegrid-travel' ),
				'tips'       => __( 'Travel Tips', 'guidegrid-travel' ),
				'itinerary'  => __( 'Itinerary', 'guidegrid-travel' ),
			),
		) );
	}
}

if ( ! function_exists( 'tg_addon_box' ) ) {
	/**
	 * Add-on meta box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	function tg_addon_box( WP_Post $post ) {
		wp_nonce_field( 'tg_addon_meta', 'tg_addon_meta_nonce' );
		$settings = tg_settings();

		tg_mb_text( array( 'key' => '_tg_addon_price', 'label' => __( 'Price', 'guidegrid-travel' ), 'value' => get_post_meta( $post->ID, '_tg_addon_price', true ), 'type' => 'number' ) );
		tg_mb_select( array(
			'key'     => '_tg_addon_unit',
			'label'   => __( 'Pricing Unit', 'guidegrid-travel' ),
			'value'   => get_post_meta( $post->ID, '_tg_addon_unit', true ) ? get_post_meta( $post->ID, '_tg_addon_unit', true ) : 'per_person',
			'options' => array(
				'per_person'  => __( 'Per Person', 'guidegrid-travel' ),
				'per_booking' => __( 'Per Booking', 'guidegrid-travel' ),
				'per_day'     => __( 'Per Day', 'guidegrid-travel' ),
				'per_night'   => __( 'Per Night', 'guidegrid-travel' ),
				'per_vehicle' => __( 'Per Vehicle', 'guidegrid-travel' ),
			),
		) );
		$taxable = get_post_meta( $post->ID, '_tg_addon_taxable', true );
		echo '<p><label><input type="checkbox" name="_tg_addon_taxable" value="1" ' . checked( $taxable, '1', false ) . ' /> ' . esc_html__( 'Subject to tax', 'guidegrid-travel' ) . '</label></p>';
	}
}

/* =========================================================================
 * Save handlers
 * ========================================================================= */

if ( ! function_exists( 'tg_save_tour_meta' ) ) {
	/**
	 * Save tour meta.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	function tg_save_tour_meta( int $post_id ) {
		if ( ! isset( $_POST['tg_tour_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tg_tour_meta_nonce'] ), 'tg_tour_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$numeric = array(
			'_tg_duration', '_tg_min_group', '_tg_max_group', '_tg_min_age',
			'_tg_adult_price', '_tg_child_price', '_tg_infant_price',
			'_tg_previous_price', '_tg_deposit_percent', '_tg_tax_percent',
			'_tg_service_fee', '_tg_capacity', '_tg_min_booking', '_tg_max_booking',
		);
		$text    = array(
			'_tg_subtitle', '_tg_country', '_tg_region', '_tg_pickup_point',
			'_tg_dropoff_point', '_tg_meeting_point', '_tg_lat', '_tg_lng',
			'_tg_video_url', '_tg_language', '_tg_guide',
		);
		$selects = array(
			'_tg_duration_unit', '_tg_tour_type', '_tg_tour_mode', '_tg_difficulty',
			'_tg_availability_mode', '_tg_currency',
		);

		foreach ( $numeric as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, absint( $_POST[ $key ] ) );
			}
		}

		foreach ( $text as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$clean = ( '_tg_video_url' === $key || '_tg_lat' === $key || '_tg_lng' === $key )
					? ( '_tg_video_url' === $key ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) )
					: sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				if ( '' === $clean ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $clean );
				}
			}
		}

		foreach ( $selects as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = sanitize_key( wp_unslash( $_POST[ $key ] ) );
				update_post_meta( $post_id, $key, $value );
			}
		}

		if ( isset( $_POST['_tg_featured'] ) ) {
			update_post_meta( $post_id, '_tg_featured', '1' === $_POST['_tg_featured'] ? 1 : 0 );
		}
		if ( isset( $_POST['_tg_destination_id'] ) ) {
			update_post_meta( $post_id, '_tg_destination_id', absint( $_POST['_tg_destination_id'] ) );
		}
		if ( isset( $_POST['_tg_range_start'] ) ) {
			update_post_meta( $post_id, '_tg_range_start', sanitize_text_field( wp_unslash( $_POST['_tg_range_start'] ) ) );
		}
		if ( isset( $_POST['_tg_range_end'] ) ) {
			update_post_meta( $post_id, '_tg_range_end', sanitize_text_field( wp_unslash( $_POST['_tg_range_end'] ) ) );
		}

		// Weekly days.
		$weekly = array();
		if ( isset( $_POST['tg_weekly_days'] ) && is_array( $_POST['tg_weekly_days'] ) ) {
			$weekly = array_values( array_intersect( array_map( 'absint', (array) wp_unslash( $_POST['tg_weekly_days'] ) ), range( 0, 6 ) ) );
		}
		if ( $weekly ) {
			update_post_meta( $post_id, '_tg_weekly_days', $weekly );
		} else {
			delete_post_meta( $post_id, '_tg_weekly_days' );
		}

		// Line fields.
		$line_fields = array(
			'_tg_availability_dates',
			'_tg_blackout_dates',
			'_tg_highlights',
			'_tg_included',
			'_tg_excluded',
			'_tg_what_to_bring',
		);
		foreach ( $line_fields as $key ) {
			$raw = isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : '';
			tg_mb_save_lines( $post_id, $key, $raw );
		}

		// Textareas.
		foreach ( array( '_tg_important_info', '_tg_cancellation_policy' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$clean = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
				if ( '' === $clean ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $clean );
				}
			}
		}

		// Gallery (attachments from the media frame).
		if ( isset( $_POST['tg_gallery_ids'] ) ) {
			$ids = array_map( 'absint', (array) wp_unslash( $_POST['tg_gallery_ids'] ) );
			$ids = array_values( array_filter( $ids ) );
			if ( $ids ) {
				update_post_meta( $post_id, '_tg_gallery', $ids );
			} else {
				delete_post_meta( $post_id, '_tg_gallery' );
			}
		}

		// Add-ons.
		$addons = array();
		if ( isset( $_POST['tg_tour_addons'] ) && is_array( $_POST['tg_tour_addons'] ) ) {
			$addons = array_map( 'absint', (array) wp_unslash( $_POST['tg_tour_addons'] ) );
		}
		if ( $addons ) {
			update_post_meta( $post_id, '_tg_addons', array_values( array_filter( $addons ) ) );
		} else {
			delete_post_meta( $post_id, '_tg_addons' );
		}

		// Itinerary repeater.
		$itinerary_raw = isset( $_POST['tg_itinerary'] ) && is_array( $_POST['tg_itinerary'] ) ? (array) wp_unslash( $_POST['tg_itinerary'] ) : array();
		$itinerary     = array();
		foreach ( $itinerary_raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean_row = array(
				'title'         => sanitize_text_field( $row['title'] ?? '' ),
				'description'   => sanitize_textarea_field( $row['description'] ?? '' ),
				'meals'         => sanitize_text_field( $row['meals'] ?? '' ),
				'accommodation' => sanitize_text_field( $row['accommodation'] ?? '' ),
				'notes'         => sanitize_text_field( $row['notes'] ?? '' ),
			);
			if ( trim( wp_json_encode( $clean_row ) ) !== trim( wp_json_encode( array_fill_keys( array_keys( $clean_row ), '' ) ) ) ) {
				$itinerary[] = $clean_row;
			}
		}
		if ( $itinerary ) {
			update_post_meta( $post_id, '_tg_itinerary', $itinerary );
		} else {
			delete_post_meta( $post_id, '_tg_itinerary' );
		}

		// FAQ repeater.
		$faq_raw = isset( $_POST['tg_faq'] ) && is_array( $_POST['tg_faq'] ) ? (array) wp_unslash( $_POST['tg_faq'] ) : array();
		$faq     = array();
		foreach ( $faq_raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean_row = array(
				'question' => sanitize_text_field( $row['question'] ?? '' ),
				'answer'   => sanitize_textarea_field( $row['answer'] ?? '' ),
			);
			if ( '' !== $clean_row['question'] || '' !== $clean_row['answer'] ) {
				$faq[] = $clean_row;
			}
		}
		if ( $faq ) {
			update_post_meta( $post_id, '_tg_faq', $faq );
		} else {
			delete_post_meta( $post_id, '_tg_faq' );
		}
	}
}
add_action( 'save_post_tour', 'tg_save_tour_meta' );

if ( ! function_exists( 'tg_save_destination_meta' ) ) {
	/**
	 * Save destination meta.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	function tg_save_destination_meta( int $post_id ) {
		if ( ! isset( $_POST['tg_destination_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tg_destination_meta_nonce'] ), 'tg_destination_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( array( '_tg_country', '_tg_region', '_tg_lat', '_tg_lng', '_tg_best_time', '_tg_avg_temp', '_tg_currency', '_tg_language', '_tg_timezone' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
		foreach ( array( '_tg_travel_info', '_tg_safety_info' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
	}
}
add_action( 'save_post_destination', 'tg_save_destination_meta' );

if ( ! function_exists( 'tg_save_guide_meta' ) ) {
	/**
	 * Save travel guide meta.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	function tg_save_guide_meta( int $post_id ) {
		if ( ! isset( $_POST['tg_guide_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tg_guide_meta_nonce'] ), 'tg_guide_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['_tg_guide_destination'] ) ) {
			update_post_meta( $post_id, '_tg_guide_destination', absint( $_POST['_tg_guide_destination'] ) );
		}
		if ( isset( $_POST['_tg_guide_type'] ) ) {
			update_post_meta( $post_id, '_tg_guide_type', sanitize_key( wp_unslash( $_POST['_tg_guide_type'] ) ) );
		}
	}
}
add_action( 'save_post_travel_guide', 'tg_save_guide_meta' );

if ( ! function_exists( 'tg_save_addon_meta' ) ) {
	/**
	 * Save add-on meta.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	function tg_save_addon_meta( int $post_id ) {
		if ( ! isset( $_POST['tg_addon_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tg_addon_meta_nonce'] ), 'tg_addon_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['_tg_addon_price'] ) ) {
			update_post_meta( $post_id, '_tg_addon_price', floatval( $_POST['_tg_addon_price'] ) );
		}
		if ( isset( $_POST['_tg_addon_unit'] ) ) {
			$unit = sanitize_key( wp_unslash( $_POST['_tg_addon_unit'] ) );
			$allowed = array( 'per_person', 'per_booking', 'per_day', 'per_night', 'per_vehicle' );
			update_post_meta( $post_id, '_tg_addon_unit', in_array( $unit, $allowed, true ) ? $unit : 'per_person' );
		}
		update_post_meta( $post_id, '_tg_addon_taxable', isset( $_POST['_tg_addon_taxable'] ) ? 1 : 0 );
	}
}
add_action( 'save_post_tg_addon', 'tg_save_addon_meta' );
