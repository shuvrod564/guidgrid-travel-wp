<?php
/**
 * Shortcodes.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_sc_tour_card' ) ) {
	/**
	 * [tg_tour_card id="123"]
	 *
	 * @param array $atts Attribs.
	 * @return string
	 */
	function tg_sc_tour_card( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'tg_tour_card' );
		$id   = absint( $atts['id'] );
		if ( ! $id || 'tour' !== get_post_type( $id ) ) {
			return '';
		}
		ob_start();
		tg_tour_card( $id );
		return (string) ob_get_clean();
	}
}
add_shortcode( 'tg_tour_card', 'tg_sc_tour_card' );

if ( ! function_exists( 'tg_sc_tours' ) ) {
	/**
	 * [tg_tours count="6" category="adventure"]
	 *
	 * @param array $atts Attribs.
	 * @return string
	 */
	function tg_sc_tours( $atts ) {
		$atts = shortcode_atts(
			array(
				'count'    => 6,
				'category' => '',
			),
			$atts,
			'tg_tours'
		);

		$args = array(
			'post_type'      => 'tour',
			'posts_per_page' => absint( $atts['count'] ),
			'no_found_rows'  => true,
		);
		if ( $atts['category'] ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => 'tour_category',
					'field'    => 'slug',
					'terms'    => array_map( 'sanitize_title', explode( ',', $atts['category'] ) ),
				),
			);
		}

		$query = new WP_Query( $args );
		if ( ! $query->have_posts() ) {
			return '';
		}

		ob_start();
		echo '<div class="tg-card-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();
			tg_tour_card( get_the_ID() );
		}
		echo '</div>';
		wp_reset_postdata();
		return (string) ob_get_clean();
	}
}
add_shortcode( 'tg_tours', 'tg_sc_tours' );

if ( ! function_exists( 'tg_sc_destinations' ) ) {
	/**
	 * [tg_destinations count="6"]
	 *
	 * @param array $atts Attribs.
	 * @return string
	 */
	function tg_sc_destinations( $atts ) {
		$atts = shortcode_atts( array( 'count' => 6 ), $atts, 'tg_destinations' );

		$dests = get_posts(
			array(
				'post_type'      => 'destination',
				'posts_per_page' => absint( $atts['count'] ),
			)
		);
		if ( empty( $dests ) ) {
			return '';
		}

		ob_start();
		echo '<div class="tg-row">';
		foreach ( $dests as $dest ) {
			echo '<div class="tg-col">';
			tg_destination_card( $dest );
			echo '</div>';
		}
		echo '</div>';
		return (string) ob_get_clean();
	}
}
add_shortcode( 'tg_destinations', 'tg_sc_destinations' );

if ( ! function_exists( 'tg_sc_search_form' ) ) {
	/**
	 * [tg_search_form]
	 *
	 * @return string
	 */
	function tg_sc_search_form() {
		ob_start();
		get_template_part( 'template-parts/components/hero-search' );
		return (string) ob_get_clean();
	}
}
add_shortcode( 'tg_search_form', 'tg_sc_search_form' );

if ( ! function_exists( 'tg_sc_contact_form' ) ) {
	/**
	 * [tg_contact_form]
	 *
	 * @return string
	 */
	function tg_sc_contact_form() {
		ob_start();
		get_template_part( 'template-parts/components/contact-form' );
		return (string) ob_get_clean();
	}
}
add_shortcode( 'tg_contact_form', 'tg_sc_contact_form' );

if ( ! function_exists( 'tg_sc_enquiry_form' ) ) {
	/**
	 * [tg_enquiry_form tour="123"]
	 *
	 * @param array $atts Attribs.
	 * @return string
	 */
	function tg_sc_enquiry_form( $atts ) {
		$atts    = shortcode_atts( array( 'tour' => 0 ), $atts, 'tg_enquiry_form' );
		$tour_id = absint( $atts['tour'] );
		ob_start();
		?>
		<form class="tg-checkout-form" method="post" data-tg-ajax-form data-action="tg_submit_enquiry" data-tour-id="<?php echo esc_attr( (string) $tour_id ); ?>">
			<h2><?php esc_html_e( 'Tour Enquiry', 'guidegrid-travel' ); ?></h2>
			<?php if ( $tour_id ) : ?>
				<input type="hidden" name="tour_id" value="<?php echo esc_attr( (string) $tour_id ); ?>" />
			<?php endif; ?>
			<div class="tg-form-grid">
				<div class="tg-field tg-form-row">
					<label class="tg-label" for="tg-enq-name"><?php esc_html_e( 'Name', 'guidegrid-travel' ); ?> *</label>
					<input type="text" id="tg-enq-name" name="name" class="tg-input" required />
				</div>
				<div class="tg-field tg-form-row">
					<label class="tg-label" for="tg-enq-email"><?php esc_html_e( 'Email', 'guidegrid-travel' ); ?> *</label>
					<input type="email" id="tg-enq-email" name="email" class="tg-input" required />
				</div>
				<div class="tg-field tg-form-row">
					<label class="tg-label" for="tg-enq-phone"><?php esc_html_e( 'Phone', 'guidegrid-travel' ); ?></label>
					<input type="tel" id="tg-enq-phone" name="phone" class="tg-input" />
				</div>
				<div class="tg-field tg-form-row">
					<label class="tg-label" for="tg-enq-dest"><?php esc_html_e( 'Preferred destination', 'guidegrid-travel' ); ?></label>
					<input type="text" id="tg-enq-dest" name="destination" class="tg-input" />
				</div>
				<div class="tg-field tg-form-row">
					<label class="tg-label" for="tg-enq-date"><?php esc_html_e( 'Travel date', 'guidegrid-travel' ); ?></label>
					<input type="date" id="tg-enq-date" name="travel_date" class="tg-input" />
				</div>
				<div class="tg-field tg-form-row">
					<label class="tg-label" for="tg-enq-travelers"><?php esc_html_e( 'Number of travelers', 'guidegrid-travel' ); ?></label>
					<input type="number" id="tg-enq-travelers" name="travelers" class="tg-input" min="1" value="2" />
				</div>
			</div>
			<div class="tg-field tg-form-row">
				<label class="tg-label" for="tg-enq-budget"><?php esc_html_e( 'Budget (optional)', 'guidegrid-travel' ); ?></label>
				<input type="text" id="tg-enq-budget" name="budget" class="tg-input" placeholder="$500–$1,000" />
			</div>
			<div class="tg-field tg-form-row">
				<label class="tg-label" for="tg-enq-message"><?php esc_html_e( 'Message', 'guidegrid-travel' ); ?> *</label>
				<textarea id="tg-enq-message" name="message" class="tg-textarea" required></textarea>
			</div>
			<button type="submit" class="tg-btn tg-btn--primary"><?php esc_html_e( 'Send Enquiry', 'guidegrid-travel' ); ?><span class="tg-spinner" aria-hidden="true"></span></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}
}
add_shortcode( 'tg_enquiry_form', 'tg_sc_enquiry_form' );
