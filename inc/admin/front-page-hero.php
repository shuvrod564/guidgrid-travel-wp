<?php
/**
 * Front-page hero editor.
 *
 * Adds a media-enabled meta box to the page selected under
 * Settings > Reading as the site's static front page.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the hero meta box only on the assigned static front page.
 *
 * @param WP_Post $post Current page.
 * @return void
 */
function tg_register_front_page_hero_meta_box( WP_Post $post ): void {
	if ( 'page' !== $post->post_type || (int) get_option( 'page_on_front' ) !== (int) $post->ID ) {
		return;
	}

	add_meta_box(
		'tg_front_page_hero',
		__( 'Front Page Hero', 'guidegrid-travel' ),
		'tg_render_front_page_hero_meta_box',
		'page',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_page', 'tg_register_front_page_hero_meta_box' );

/**
 * Load the WordPress media modal on the assigned front-page editor.
 *
 * @param string $hook_suffix Current admin page hook.
 * @return void
 */
function tg_enqueue_front_page_hero_media( string $hook_suffix ): void {
	if ( 'post.php' !== $hook_suffix ) {
		return;
	}
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $post_id && (int) get_option( 'page_on_front' ) === $post_id ) {
		wp_enqueue_media();
	}
}
add_action( 'admin_enqueue_scripts', 'tg_enqueue_front_page_hero_media', 5 );

/**
 * Render the front-page hero controls.
 *
 * @param WP_Post $post Current page.
 * @return void
 */
function tg_render_front_page_hero_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'tg_save_front_page_hero', 'tg_front_page_hero_nonce' );

	$settings = tg_settings();
	$title    = metadata_exists( 'post', $post->ID, '_tg_front_hero_title' )
		? (string) get_post_meta( $post->ID, '_tg_front_hero_title', true )
		: ( ! empty( $settings['hero_title'] ) ? (string) $settings['hero_title'] : __( 'Explore the World, One Tour at a Time', 'guidegrid-travel' ) );
	$text     = metadata_exists( 'post', $post->ID, '_tg_front_hero_text' )
		? (string) get_post_meta( $post->ID, '_tg_front_hero_text', true )
		: ( ! empty( $settings['hero_text'] ) ? (string) $settings['hero_text'] : __( 'Handcrafted tours, local guides and transparent pricing — from island escapes to Himalayan treks.', 'guidegrid-travel' ) );
	$image_id = absint( get_post_meta( $post->ID, '_tg_front_hero_image_id', true ) );
	$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'medium_large' ) : '';
	if ( ! $image ) {
		$image_id = 0;
	}
	?>
	<div class="tg-front-hero-fields">
		<p class="description">
			<?php esc_html_e( 'These fields control the large hero section at the top of the public front page. Empty text fields use the theme defaults.', 'guidegrid-travel' ); ?>
		</p>

		<p>
			<label class="tg-label" for="tg-front-hero-title"><?php esc_html_e( 'Hero title', 'guidegrid-travel' ); ?></label>
			<input type="text" id="tg-front-hero-title" name="tg_front_hero_title" class="widefat" maxlength="180" value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<p>
			<label class="tg-label" for="tg-front-hero-text"><?php esc_html_e( 'Hero description', 'guidegrid-travel' ); ?></label>
			<textarea id="tg-front-hero-text" name="tg_front_hero_text" class="widefat" rows="4" maxlength="500"><?php echo esc_textarea( $text ); ?></textarea>
		</p>

		<div
			class="tg-media-field"
			data-tg-media-field
			data-frame-title="<?php esc_attr_e( 'Select front-page hero image', 'guidegrid-travel' ); ?>"
			data-button-label="<?php esc_attr_e( 'Use as hero image', 'guidegrid-travel' ); ?>"
		>
			<label class="tg-label"><?php esc_html_e( 'Hero image', 'guidegrid-travel' ); ?></label>
			<input type="hidden" name="tg_front_hero_image_id" value="<?php echo esc_attr( (string) $image_id ); ?>" data-tg-media-id />
			<div class="tg-media-preview<?php echo $image ? '' : ' is-empty'; ?>" data-tg-media-preview>
				<?php if ( $image ) : ?>
					<img src="<?php echo esc_url( $image ); ?>" alt="" />
				<?php endif; ?>
				<span class="tg-media-empty" data-tg-media-empty<?php echo $image ? ' hidden' : ''; ?>><?php esc_html_e( 'No custom hero image selected. The theme fallback image will be used.', 'guidegrid-travel' ); ?></span>
			</div>
			<p class="tg-media-actions">
				<button type="button" class="button button-secondary" data-tg-media-select><?php esc_html_e( 'Select image', 'guidegrid-travel' ); ?></button>
				<button type="button" class="button button-link-delete" data-tg-media-remove<?php echo $image ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove image', 'guidegrid-travel' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Recommended size: 1600 × 900 pixels or larger. Use an optimized JPEG or WebP image.', 'guidegrid-travel' ); ?></p>
		</div>

		<p>
			<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View front page', 'guidegrid-travel' ); ?></a>
		</p>
	</div>
	<?php
}

/**
 * Save front-page hero fields.
 *
 * @param int $post_id Page ID.
 * @return void
 */
function tg_save_front_page_hero_meta( int $post_id ): void {
	if ( ! isset( $_POST['tg_front_page_hero_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['tg_front_page_hero_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'tg_save_front_page_hero' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || 'page' !== get_post_type( $post_id ) ) {
		return;
	}
	if ( (int) get_option( 'page_on_front' ) !== $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$title = isset( $_POST['tg_front_hero_title'] ) ? sanitize_text_field( wp_unslash( $_POST['tg_front_hero_title'] ) ) : '';
	$text  = isset( $_POST['tg_front_hero_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tg_front_hero_text'] ) ) : '';
	$title = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 180 ) : substr( $title, 0, 180 );
	$text  = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 500 ) : substr( $text, 0, 500 );

	if ( '' !== $title ) {
		update_post_meta( $post_id, '_tg_front_hero_title', $title );
	} else {
		delete_post_meta( $post_id, '_tg_front_hero_title' );
	}
	if ( '' !== $text ) {
		update_post_meta( $post_id, '_tg_front_hero_text', $text );
	} else {
		delete_post_meta( $post_id, '_tg_front_hero_text' );
	}

	$image_id = isset( $_POST['tg_front_hero_image_id'] ) ? absint( $_POST['tg_front_hero_image_id'] ) : 0;
	if ( $image_id && wp_attachment_is_image( $image_id ) ) {
		update_post_meta( $post_id, '_tg_front_hero_image_id', $image_id );
	} elseif ( 0 === $image_id ) {
		delete_post_meta( $post_id, '_tg_front_hero_image_id' );
	}
}
add_action( 'save_post_page', 'tg_save_front_page_hero_meta' );
