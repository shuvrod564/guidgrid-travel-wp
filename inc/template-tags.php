<?php
/**
 * Template tags: reusable markup functions used by templates.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_section_heading' ) ) {
	/**
	 * Section heading block.
	 *
	 * @param string $title    Title.
	 * @param string $subtitle Subtitle.
	 * @param string $align    left|center.
	 * @param string $kicker   Small label above the title.
	 * @return void
	 */
	function tg_section_heading( string $title, string $subtitle = '', string $align = 'center', string $kicker = '' ) {
		?>
		<div class="tg-section-head <?php echo 'center' === $align ? 'center' : ''; ?>">
			<?php if ( $kicker ) : ?>
				<span class="tg-section-kicker"><?php echo esc_html( $kicker ); ?></span>
			<?php endif; ?>
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( $subtitle ) : ?>
				<p><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'tg_breadcrumbs' ) ) {
	/**
	 * Breadcrumbs (simple, escaped).
	 *
	 * @return void
	 */
	function tg_breadcrumbs() {
		if ( is_front_page() ) {
			return;
		}

		$items  = array();
		$items[] = array(
			'label' => __( 'Home', 'guidegrid-travel' ),
			'url'   => home_url( '/' ),
		);

		if ( is_singular( 'tour' ) ) {
			$items[] = array(
				'label' => __( 'Tours', 'guidegrid-travel' ),
				'url'   => get_post_type_archive_link( 'tour' ),
			);
			$items[] = array(
				'label' => get_the_title(),
				'url'   => '',
			);
		} elseif ( is_post_type_archive( 'tour' ) || is_tax( 'tour_category' ) || is_tax( 'activity' ) ) {
			$items[] = array(
				'label' => __( 'Tours', 'guidegrid-travel' ),
				'url'   => '',
			);
		} elseif ( is_singular( 'destination' ) ) {
			$items[] = array(
				'label' => __( 'Destinations', 'guidegrid-travel' ),
				'url'   => get_post_type_archive_link( 'destination' ),
			);
			$items[] = array(
				'label' => get_the_title(),
				'url'   => '',
			);
		} elseif ( is_post_type_archive( 'destination' ) ) {
			$items[] = array(
				'label' => __( 'Destinations', 'guidegrid-travel' ),
				'url'   => '',
			);
		} elseif ( is_singular( 'travel_guide' ) ) {
			$items[] = array(
				'label' => __( 'Travel Guides', 'guidegrid-travel' ),
				'url'   => get_post_type_archive_link( 'travel_guide' ),
			);
			$items[] = array(
				'label' => get_the_title(),
				'url'   => '',
			);
		} elseif ( is_post_type_archive( 'travel_guide' ) ) {
			$items[] = array(
				'label' => __( 'Travel Guides', 'guidegrid-travel' ),
				'url'   => '',
			);
		} elseif ( is_tax( 'tour_category' ) || is_tax( 'activity' ) ) {
			$items[] = array(
				'label' => single_term_title( '', false ),
				'url'   => '',
			);
		} elseif ( is_singular( 'post' ) ) {
			$items[] = array(
				'label' => __( 'Blog', 'guidegrid-travel' ),
				'url'   => get_permalink( (int) get_option( 'blog_page_id' ) ) ? get_permalink( (int) get_option( 'blog_page_id' ) ) : home_url( '/blog/' ),
			);
			$items[] = array(
				'label' => get_the_title(),
				'url'   => '',
			);
		} elseif ( is_page() ) {
			$items[] = array(
				'label' => get_the_title(),
				'url'   => '',
			);
		}

		echo '<nav class="tg-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'guidegrid-travel' ) . '"><ol>';
		$last = count( $items ) - 1;
		foreach ( $items as $i => $item ) {
			if ( $i === $last ) {
				echo '<li><span aria-current="page">' . esc_html( $item['label'] ) . '</span></li>';
			} elseif ( $item['url'] ) {
				echo '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a></li>';
			} else {
				echo '<li>' . esc_html( $item['label'] ) . '</li>';
			}
		}
		echo '</ol></nav>';
	}
}

if ( ! function_exists( 'tg_tour_card' ) ) {
	/**
	 * Render a tour card.
	 *
	 * @param int   $tour_id Tour post ID.
	 * @param array $args    Reserved.
	 * @return void
	 */
	function tg_tour_card( int $tour_id, array $args = array() ) {
		$settings = tg_settings();
		$rating   = tg_get_tour_rating( $tour_id );
		$price    = tg_tour_price_info( $tour_id );
		$dest     = tg_tour_destination_name( $tour_id );
		$duration = tg_tour_duration_text( $tour_id );
		$duration_days = tg_tour_duration_days( $tour_id );

		$in_wishlist = is_user_logged_in() && TG_Wishlist::has( get_current_user_id(), $tour_id );

		/**
		 * Filter tour card data.
		 *
		 * @param array $card_data Card data.
		 * @param int   $tour_id   Tour ID.
		 */
		$card_data = apply_filters(
			'tg_tour_card_data',
			array(
				'rating'   => $rating,
				'price'    => $price,
				'dest'     => $dest,
				'duration' => $duration,
			),
			$tour_id
		);
		?>
		<article class="tg-tour-card">
			<a class="tg-tour-card-media" href="<?php echo esc_url( get_permalink( $tour_id ) ); ?>" tabindex="-1" aria-hidden="true">
				<?php if ( has_post_thumbnail( $tour_id ) ) : ?>
					<?php echo get_the_post_thumbnail( $tour_id, 'tg-card', array( 'loading' => 'lazy' ) ); ?>
				<?php else : ?>
					<img src="<?php echo esc_url( tg_tour_image_url( $tour_id ) ); ?>" alt="" loading="lazy" />
				<?php endif; ?>
			</a>

			<span class="tg-tour-card-badges">
				<?php if ( get_post_meta( $tour_id, '_tg_featured', true ) ) : ?>
					<span class="tg-badge tg-badge--primary"><?php esc_html_e( 'Featured', 'guidegrid-travel' ); ?></span>
				<?php endif; ?>
				<?php if ( $price['previous'] > 0 ) : ?>
					<span class="tg-badge tg-badge--sale">
						<?php
						printf(
							/* translators: %d: percent saved */
							esc_html__( 'Save %d%%', 'guidegrid-travel' ),
							(int) round( ( 1 - $price['base'] / $price['previous'] ) * 100 )
						);
						?>
					</span>
				<?php endif; ?>
			</span>

			<button type="button" class="tg-wishlist-btn <?php echo $in_wishlist ? 'is-active' : ''; ?>" data-tour="<?php echo esc_attr( (string) $tour_id ); ?>" aria-pressed="<?php echo $in_wishlist ? 'true' : 'false'; ?>" aria-label="<?php echo $in_wishlist ? esc_attr__( 'Remove from wishlist', 'guidegrid-travel' ) : esc_attr__( 'Save to wishlist', 'guidegrid-travel' ); ?>">
				<?php echo tg_lucide( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>

			<div class="tg-tour-card-body">
				<h3 class="tg-tour-card-title"><a href="<?php echo esc_url( get_permalink( $tour_id ) ); ?>"><?php echo esc_html( get_the_title( $tour_id ) ); ?></a></h3>

				<div class="tg-tour-card-meta">
					<?php if ( $dest ) : ?>
						<span class="tg-meta-item"><?php echo tg_svg( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $dest ); ?></span>
					<?php endif; ?>
					<?php if ( $duration ) : ?>
						<span class="tg-meta-item"><?php echo tg_svg( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $duration ); ?></span>
					<?php endif; ?>
					<?php if ( $rating['count'] > 0 ) : ?>
						<span class="tg-meta-item"><?php echo tg_star_html( $rating['avg'], $rating['count'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php endif; ?>
				</div>

				<div class="tg-tour-card-foot">
					<div class="tg-price">
						<span class="tg-price-label"><?php esc_html_e( 'From', 'guidegrid-travel' ); ?></span>
						<?php if ( $price['base'] > 0 ) : ?>
							<span class="tg-price-amount"><?php echo esc_html( tg_format_price( $price['base'], $price['currency'] ) ); ?></span>
							<?php if ( $price['previous'] > 0 ) : ?>
								<span class="tg-price-old"><?php echo esc_html( tg_format_price( $price['previous'], $price['currency'] ) ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<span class="tg-price-amount"><?php esc_html_e( 'Contact us', 'guidegrid-travel' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="tg-tour-card-actions">
						<a class="tg-btn tg-btn--ghost tg-btn--sm" href="<?php echo esc_url( get_permalink( $tour_id ) ); ?>"><?php esc_html_e( 'View Details', 'guidegrid-travel' ); ?></a>
						<a class="tg-btn tg-btn--primary tg-btn--sm" href="<?php echo esc_url( tg_tour_book_url( $tour_id ) ); ?>"><?php esc_html_e( 'Book Now', 'guidegrid-travel' ); ?></a>
					</div>
				</div>
			</div>
		</article>
		<?php
	}
}

if ( ! function_exists( 'tg_destination_card' ) ) {
	/**
	 * Render a destination card.
	 *
	 * @param WP_Post $dest Destination post.
	 * @return void
	 */
		function tg_destination_card( WP_Post $dest ) {
		$tour_count = (int) get_post_meta( $dest->ID, '_tg_tour_count', true );
		$image      = get_the_post_thumbnail_url( $dest->ID, 'tg-card' ) ? get_the_post_thumbnail_url( $dest->ID, 'tg-card' ) : tg_get_meta( $dest->ID, '_tg_demo_image', tg_demo_img( 'tg-dest-' . $dest->ID, 1200, 700 ) );

		if ( ! $tour_count ) {
			$tour_count = count(
				(array) get_posts(
					array(
						'post_type'      => 'tour',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'meta_key'       => '_tg_destination_id', // phpcs:ignore WordPress.DB.SlowDBQuery
						'meta_value'     => $dest->ID,           // phpcs:ignore WordPress.DB.SlowDBQuery
						'no_found_rows'  => true,
					)
				)
			);
		}
		?>
		<a class="tg-dest-card" href="<?php echo esc_url( get_permalink( $dest->ID ) ); ?>" style="<?php echo $image ? 'background-image:url(' . esc_url( $image ) . ');' : ''; ?>">
			<span class="tg-dest-card-body">
				<h3><?php echo esc_html( get_the_title( $dest->ID ) ); ?></h3>
				<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $dest->ID ), 14 ) ); ?></p>
				<span class="tg-dest-card-count">
					<?php
					printf(
						/* translators: %d: number of tours */
						esc_html( _n( '%d tour', '%d tours', $tour_count, 'guidegrid-travel' ) ),
						$tour_count
					);
					?>
				</span>
			</span>
		</a>
		<?php
	}
}

if ( ! function_exists( 'tg_blog_card' ) ) {
	/**
	 * Render a blog card.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	function tg_blog_card( int $post_id ) {
		$categories = get_the_category( $post_id );
		$cat        = $categories ? $categories[0] : null;
		$excerpt    = get_the_excerpt( $post_id );
		?>
		<article class="tg-blog-card">
			<a class="tg-blog-card-media" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" tabindex="-1" aria-hidden="true">
				<?php if ( has_post_thumbnail( $post_id ) ) : ?>
					<?php echo get_the_post_thumbnail( $post_id, 'tg-card', array( 'loading' => 'lazy' ) ); ?>
				<?php else : ?>
					<span class="tg-skeleton" style="display:block;width:100%;height:100%;"></span>
				<?php endif; ?>
			</a>
			<div class="tg-blog-card-body">
				<div class="tg-blog-card-meta">
					<?php if ( $cat ) : ?>
						<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
					<?php endif; ?>
					<span><?php echo esc_html( get_the_date( '', $post_id ) ); ?></span>
					<span><?php echo esc_html( get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ) ); ?></span>
				</div>
				<h3 class="tg-blog-card-title"><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
				<p class="tg-blog-card-excerpt"><?php echo esc_html( wp_trim_words( $excerpt, 22 ) ); ?></p>
				<a class="tg-read-more" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php esc_html_e( 'Read more', 'guidegrid-travel' ); ?> →</a>
			</div>
		</article>
		<?php
	}
}

if ( ! function_exists( 'tg_the_pagination' ) ) {
	/**
	 * Themed pagination.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	function tg_the_pagination( WP_Query $query ) {
		$pages    = (int) $query->max_num_pages;
		if ( $pages <= 1 ) {
			return;
		}
		$current  = max( 1, (int) $query->get( 'paged' ) );
		$base     = esc_url( remove_query_arg( 'paged' ) );
		?>
		<nav class="tg-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'guidegrid-travel' ); ?>">
			<?php if ( $current > 1 ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'paged', $current - 1, $base ) ); ?>" rel="prev" aria-label="<?php esc_attr_e( 'Previous page', 'guidegrid-travel' ); ?>">←</a>
			<?php endif; ?>

			<?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
				<?php
				if ( $i === $current ) {
					echo '<span class="current" aria-current="page">' . esc_html( (string) $i ) . '</span>';
				} elseif ( 1 === $i || $pages === $i || abs( $i - $current ) <= 2 ) {
					echo '<a href="' . esc_url( add_query_arg( 'paged', $i, $base ) ) . '">' . esc_html( (string) $i ) . '</a>';
				} elseif ( abs( $i - $current ) === 3 ) {
					echo '<span class="dots">…</span>';
				}
				?>
			<?php endfor; ?>

			<?php if ( $current < $pages ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'paged', $current + 1, $base ) ); ?>" rel="next" aria-label="<?php esc_attr_e( 'Next page', 'guidegrid-travel' ); ?>">→</a>
			<?php endif; ?>
		</nav>
		<?php
	}
}

if ( ! function_exists( 'tg_empty_state' ) ) {
	/**
	 * Empty state block.
	 *
	 * @param string $icon   Icon key.
	 * @param string $title  Title.
	 * @param string $text   Text.
	 * @param string $url    Action URL.
	 * @param string $label  Action label.
	 * @return void
	 */
	function tg_empty_state( string $icon, string $title, string $text, string $url = '', string $label = '' ) {
		?>
		<div class="tg-empty">
			<span class="tg-empty-icon"><?php echo tg_svg( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<h3><?php echo esc_html( $title ); ?></h3>
			<p><?php echo esc_html( $text ); ?></p>
			<?php if ( $url && $label ) : ?>
				<a class="tg-btn tg-btn--primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'tg_tour_facts' ) ) {
	/**
	 * Quick facts grid for a tour.
	 *
	 * @param int $tour_id Tour post ID.
	 * @return void
	 */
	function tg_tour_facts( int $tour_id ) {
		$difficulty = tg_get_meta( $tour_id, '_tg_difficulty', '' );
		$language   = tg_get_meta( $tour_id, '_tg_language', '' );
		$min_age    = (int) tg_get_meta( $tour_id, '_tg_min_age', 0 );
		$max_group  = (int) tg_get_meta( $tour_id, '_tg_max_group', 0 );
		$min_group  = (int) tg_get_meta( $tour_id, '_tg_min_group', 0 );
		$mode       = tg_get_meta( $tour_id, '_tg_tour_mode', 'group' );
		$next_date  = tg_get_next_available_date( $tour_id );

		$mode_labels = array(
			'group'   => __( 'Group', 'guidegrid-travel' ),
			'private' => __( 'Private', 'guidegrid-travel' ),
			'both'    => __( 'Group & Private', 'guidegrid-travel' ),
		);

		$facts = array();
		$facts[] = array(
			'icon'  => 'clock',
			'label' => __( 'Duration', 'guidegrid-travel' ),
			'value' => tg_tour_duration_text( $tour_id ),
		);
		$facts[] = array(
			'icon'  => 'pin',
			'label' => __( 'Destination', 'guidegrid-travel' ),
			'value' => tg_tour_destination_name( $tour_id ),
		);
		$facts[] = array(
			'icon'  => 'users',
			'label' => __( 'Group Size', 'guidegrid-travel' ),
			'value' => ( $min_group || $max_group ) ? $min_group . '–' . ( $max_group ? $max_group : '' ) . ( $max_group && ! $min_group ? '' : '' ) : ( $mode_labels[ $mode ] ?? '' ),
		);
		if ( $difficulty ) {
			$facts[] = array(
				'icon'  => 'flag',
				'label' => __( 'Difficulty', 'guidegrid-travel' ),
				'value' => tg_difficulty_label( $difficulty ),
			);
		}
		if ( $language ) {
			$facts[] = array(
				'icon'  => 'language',
				'label' => __( 'Language', 'guidegrid-travel' ),
				'value' => $language,
			);
		}
		$facts[] = array(
			'icon'  => 'calendar',
			'label' => __( 'Next Departure', 'guidegrid-travel' ),
			'value' => $next_date ? tg_format_date( $next_date ) : __( 'On request', 'guidegrid-travel' ),
		);
		if ( $min_age ) {
			$facts[] = array(
				'icon'  => 'child',
				'label' => __( 'Minimum Age', 'guidegrid-travel' ),
				'value' => sprintf( /* translators: %d: age */ __( '%d years', 'guidegrid-travel' ), $min_age ),
			);
		}

		echo '<div class="card section-gap" aria-label="' . esc_attr__( 'Quick facts', 'guidegrid-travel' ) . '">';
		echo '<div class="tg-tour-section"><h2><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--tg-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles preview-icon"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/><path d="M20 2v4"/><path d="M22 4h-4"/><circle cx="4" cy="20" r="2"/></svg>Tour details: </h2></div><div class="tg-facts">';
		foreach ( $facts as $fact ) {
			if ( '' === $fact['value'] ) {
				continue;
			}
			echo '<div class="tg-fact"><span class="tg-fact-icon">' . tg_svg( $fact['icon'] ) . '</span><div><strong>' . esc_html( $fact['value'] ) . '</strong><span>' . esc_html( $fact['label'] ) . '</span></div></div>';
		}
		echo '</div></div>';
	}
}

if ( ! function_exists( 'tg_tour_gallery' ) ) {
	/**
	 * Tour gallery (main + thumbnails + lightbox triggers).
	 *
	 * @param int $tour_id Tour post ID.
	 * @return void
	 */
	function tg_tour_gallery( int $tour_id ) {
		// Build a unified list of image URLs (attachment IDs first, demo URLs as fallback).
		$images = array();

		$ids      = tg_tour_gallery_ids( $tour_id );
		$featured = get_post_thumbnail_id( $tour_id );
		if ( $featured && ! in_array( $featured, $ids, true ) ) {
			array_unshift( $ids, $featured );
		}
		foreach ( $ids as $id ) {
			$url = wp_get_attachment_image_url( $id, 'tg-gallery' );
			if ( $url ) {
				$images[] = $url;
			}
		}

		if ( empty( $images ) ) {
			$demo_gallery = (array) tg_get_meta( $tour_id, '_tg_demo_gallery', array() );
			$demo_main    = (string) tg_get_meta( $tour_id, '_tg_demo_image', '' );
			foreach ( $demo_gallery as $url ) {
				$images[] = (string) $url;
			}
			if ( $demo_main && ! in_array( $demo_main, $images, true ) ) {
				array_unshift( $images, $demo_main );
			}
		}

		if ( empty( $images ) ) {
			$images[] = tg_tour_image_url( $tour_id );
		}

		$video_url = (string) tg_get_meta( $tour_id, '_tg_video_url', '' );
		?>
		<div class="tg-gallery" data-tg-gallery>
			<div class="tg-gallery-main" data-tg-full="<?php echo esc_url( $images[0] ); ?>">
				<img class="tg-gallery-img" src="<?php echo esc_url( $images[0] ); ?>" alt="<?php echo esc_attr( get_the_title( $tour_id ) ); ?>" loading="eager" />
				<?php if ( $video_url ) : ?>
					<a class="tg-gallery-video-badge" href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Watch tour video', 'guidegrid-travel' ); ?>"><?php echo tg_svg( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<?php endif; ?>
			</div>
			<?php if ( count( $images ) > 1 ) : ?>
				<div class="tg-gallery-thumbs" role="list" aria-label="<?php esc_attr_e( 'Gallery thumbnails', 'guidegrid-travel' ); ?>">
					<?php foreach ( $images as $i => $url ) : ?>
						<button type="button" class="<?php echo 0 === $i ? 'is-active' : ''; ?>" data-tg-thumb data-tg-full="<?php echo esc_url( $url ); ?>" role="listitem" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: image number */ __( 'View image %d', 'guidegrid-travel' ), $i + 1 ) ); ?>">
							<img src="<?php echo esc_url( $url ); ?>" alt="" loading="lazy" />
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'tg_tour_itinerary' ) ) {
	/**
	 * Tour itinerary accordion.
	 *
	 * @param int $tour_id Tour post ID.
	 * @return void
	 */
	function tg_tour_itinerary( int $tour_id ) {
		$itinerary = (array) tg_get_meta( $tour_id, '_tg_itinerary', array() );
		if ( empty( $itinerary ) ) {
			return;
		}
		?>
		<div class="tg-itinerary">
			<?php foreach ( $itinerary as $i => $day ) : ?>
				<?php
				$title   = isset( $day['title'] ) ? $day['title'] : '';
				$open    = 0 === $i ? ' open' : '';
				?>
				<details class="tg-itinerary-item"<?php echo 0 === $i ? ' open' : ''; ?>>
					<summary>
						<span class="tg-itinerary-day"><small><?php esc_html_e( 'Day', 'guidegrid-travel' ); ?></small><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
						<h3><?php echo esc_html( $title ? $title : sprintf( /* translators: %d: day number */ __( 'Day %d', 'guidegrid-travel' ), $i + 1 ) ); ?></h3>
						<span class="tg-itinerary-chevron"><?php echo tg_svg( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</summary>
					<div class="tg-itinerary-body">
						<?php if ( ! empty( $day['description'] ) ) : ?>
							<p><?php echo esc_html( $day['description'] ); ?></p>
						<?php endif; ?>
						<div class="tg-itinerary-meta">
							<?php if ( ! empty( $day['meals'] ) ) : ?>
								<span class="tg-badge tg-badge--success"><?php echo esc_html( $day['meals'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $day['accommodation'] ) ) : ?>
								<span class="tg-badge tg-badge--info"><?php echo esc_html( $day['accommodation'] ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $day['notes'] ) ) : ?>
							<p class="tg-itinerary-extra"><?php echo esc_html( $day['notes'] ); ?></p>
						<?php endif; ?>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'tg_tour_included_excluded' ) ) {
	/**
	 * Included / not included columns.
	 *
	 * @param int $tour_id Tour post ID.
	 * @return void
	 */
	function tg_tour_included_excluded( int $tour_id ) {
		$included = tg_mb_lines( $tour_id, '_tg_included' );
		$excluded = tg_mb_lines( $tour_id, '_tg_excluded' );
		if ( empty( $included ) && empty( $excluded ) ) {
			return;
		}
		?>
		<div class="tg-inc-exc">
			<div class="tg-inc-exc-box tg-inc-exc-box--in">
				<h3><?php esc_html_e( 'Included', 'guidegrid-travel' ); ?></h3>
				<ul>
					<?php foreach ( $included as $item ) : ?>
						<li><?php echo tg_svg( 'check-circle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="tg-inc-exc-box tg-inc-exc-box--out">
				<h3><?php esc_html_e( 'Not Included', 'guidegrid-travel' ); ?></h3>
				<ul>
					<?php foreach ( $excluded as $item ) : ?>
						<li><?php echo tg_svg( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'tg_tour_map' ) ) {
	/**
	 * Destination map (keyless embed) + meeting point.
	 *
	 * @param int $tour_id Tour post ID.
	 * @return void
	 */
	function tg_tour_map( int $tour_id ) {
		$dest    = tg_tour_destination( $tour_id );
		$lat     = tg_get_meta( $tour_id, '_tg_lat', '' ) ? tg_get_meta( $tour_id, '_tg_lat', '' ) : ( $dest ? tg_get_meta( $dest->ID, '_tg_lat', '' ) : '' );
		$lng     = tg_get_meta( $tour_id, '_tg_lng', '' ) ? tg_get_meta( $tour_id, '_tg_lng', '' ) : ( $dest ? tg_get_meta( $dest->ID, '_tg_lng', '' ) : '' );
		$meeting = tg_get_meta( $tour_id, '_tg_meeting_point', '' );

		if ( ! $lat || ! $lng ) {
			return;
		}
		$lat = floatval( $lat );
		$lng = floatval( $lng );
		if ( ! $lat || ! $lng ) {
			return;
		}
		$embed = add_query_arg(
			array(
				'q'   => $lat . ',' . $lng,
				'z'   => 11,
				'hl'  => get_locale(),
				'iwloc' => 'n',
			),
			'https://www.google.com/maps/embed/v1/place'
		);
		// Use the simple output embed that needs no API key.
		$embed = add_query_arg(
			array(
				'q'         => $lat . ',' . $lng,
				'z'         => 11,
				'output'    => 'embed',
			),
			'https://maps.google.com/maps'
		);
		?>
		<div class="tg-tour-map">
			<iframe title="<?php echo esc_attr__( 'Tour location map', 'guidegrid-travel' ); ?>" src="<?php echo esc_url( $embed ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
		</div>
		<?php if ( $meeting ) : ?>
			<p class="tg-map-note"><?php echo tg_svg( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Meeting point:', 'guidegrid-travel' ); ?> <strong><?php echo esc_html( $meeting ); ?></strong></p>
		<?php endif; ?>
		<?php
	}
}

if ( ! function_exists( 'tg_tour_reviews' ) ) {
	/**
	 * Reviews section (summary, list, form).
	 *
	 * @param int $tour_id Tour post ID.
	 * @return void
	 */
	function tg_tour_reviews( int $tour_id ) {
		$summary = TG_Reviews::summary( $tour_id );
		$reviews = TG_Reviews::get_for_tour( $tour_id, 5, 1 );
		$user_id = get_current_user_id();
		$review_form_id = 'tg-review-form-' . $tour_id;
		?>
		<div class="tg-reviews-layout" id="tg-reviews">
			<aside class="tg-review-summary" aria-label="<?php esc_attr_e( 'Rating summary', 'guidegrid-travel' ); ?>">
				<div class="tg-review-score">
					<span class="tg-score-num"><?php echo esc_html( number_format( $summary['avg'], 1 ) ); ?></span>
					<br />
					<?php echo tg_star_html( $summary['avg'], null, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="tg-score-count">
						<?php
						printf(
							/* translators: %d: number of reviews */
							esc_html( _n( '%d review', '%d reviews', $summary['count'], 'guidegrid-travel' ) ),
							$summary['count']
						);
						?>
					</div> 
				</div>
				<?php for ( $star = 5; $star >= 1; $star-- ) : ?>
					<?php
					$count  = isset( $summary['distribution'][ $star ] ) ? $summary['distribution'][ $star ] : 0;
					$pct    = $summary['count'] > 0 ? round( ( $count / $summary['count'] ) * 100 ) : 0;
					?>
					<div class="tg-dist-row">
						<span class="tg-dist-label"><?php echo esc_html( (string) $star ); ?> ★</span>
						<span class="tg-dist-bar"><span class="tg-dist-fill" style="width:<?php echo esc_attr( (string) $pct ); ?>%"></span></span>
						<span class="tg-dist-count"><?php echo esc_html( (string) $count ); ?></span>
					</div>
				<?php endfor; ?>

				<div class="padding" style="text-align:center;padding-top:20px;">
					<?php if ( $user_id ) : ?>
						<button
							type="button"
							class="tg-btn tg-btn--ghost"
							data-tg-review-toggle
							aria-controls="<?php echo esc_attr( $review_form_id ); ?>"
							aria-expanded="false"
						>
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-star-plus preview-icon"><path d="M11.013 18.582 6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.12 2.12 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.12 2.12 0 0 0 1.597-1.16l2.309-4.679a.53.53 0 0 1 .95 0l2.31 4.679a2.12 2.12 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904L20 11.5"/><path d="M15 18h6"/><path d="M18 15v6"/></svg>
							<?php esc_html_e( 'Write a Review', 'guidegrid-travel' ); ?>
						</button>
					<?php else : ?>
						<a
							class="tg-btn tg-btn--ghost"
							href="<?php echo esc_url( tg_auth_url( get_permalink( $tour_id ) . '#tg-reviews', 'login' ) ); ?>"
						>
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-star-plus preview-icon"><path d="M11.013 18.582 6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.12 2.12 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.12 2.12 0 0 0 1.597-1.16l2.309-4.679a.53.53 0 0 1 .95 0l2.31 4.679a2.12 2.12 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904L20 11.5"/><path d="M15 18h6"/><path d="M18 15v6"/></svg>
							<?php esc_html_e( 'Write a Review', 'guidegrid-travel' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</aside>

			<div>
				<div class="tg-review-list">
					<?php if ( empty( $reviews['items'] ) ) : ?>
						<?php tg_empty_state( 'award', __( 'No reviews yet', 'guidegrid-travel' ), __( 'Be the first to review this tour after your trip.', 'guidegrid-travel' ) ); ?>
					<?php else : ?>
						<?php foreach ( $reviews['items'] as $review ) : ?>
							<div class="tg-review">
								<div class="tg-review-head">
									<span class="avatar-fallback" aria-hidden="true"><?php echo esc_html( mb_substr( $review->name ? $review->name : 'T', 0, 1 ) ); ?></span>
									<div>
										<strong><?php echo esc_html( $review->name ? $review->name : __( 'Traveler', 'guidegrid-travel' ) ); ?></strong>
										<time datetime="<?php echo esc_attr( $review->created_at ); ?>"><?php echo esc_html( tg_format_date( $review->created_at ) ); ?></time>
									</div>
									<span class="tg-stars" aria-hidden="true"><?php echo esc_html( str_repeat( '★', (int) $review->rating ) . str_repeat( '☆', 5 - (int) $review->rating ) ); ?></span>
									<?php if ( (int) $review->verified ) : ?>
										<span class="tg-verified"><?php echo tg_svg( 'check-circle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Verified booking', 'guidegrid-travel' ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( $review->title ) : ?>
									<h4><?php echo esc_html( $review->title ); ?></h4>
								<?php endif; ?>
								<p><?php echo esc_html( $review->content ); ?></p>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<?php if ( $user_id ) : ?>
					<div
						id="<?php echo esc_attr( $review_form_id ); ?>"
						class="tg-review-form"
						data-tg-review-form
						data-tour="<?php echo esc_attr( (string) $tour_id ); ?>"
						hidden
						 style="scroll-margin-top: 45px;"
					>
						<div class="card"> 
							<h3 style="margin-bottom:20px;"><?php esc_html_e( 'Write a review', 'guidegrid-travel' ); ?></h3>
							<form method="post" data-tg-ajax-form data-action="tg_submit_review">
								<div class="tg-form-row">
									<label class="tg-label" for="tg-review-rating"><?php esc_html_e( 'Your rating', 'guidegrid-travel' ); ?></label>
									<div class="tg-rating-input" id="tg-review-rating">
										<?php for ( $star = 5; $star >= 1; $star-- ) : ?>
											<input type="radio" id="tg-rate-<?php echo esc_attr( (string) $star ); ?>" name="rating" value="<?php echo esc_attr( (string) $star ); ?>" <?php echo 5 === $star ? 'checked' : ''; ?> />
											<label for="tg-rate-<?php echo esc_attr( (string) $star ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: stars */ _n( '%d star', '%d stars', $star, 'guidegrid-travel' ), $star ) ); ?>">★</label>
										<?php endfor; ?>
									</div>
								</div>
								<div class="tg-form-row">
									<label class="tg-label" for="tg-review-title"><?php esc_html_e( 'Title (optional)', 'guidegrid-travel' ); ?></label>
									<input type="text" id="tg-review-title" name="title" class="tg-input" maxlength="150" />
								</div>
								<div class="tg-form-row">
									<label class="tg-label" for="tg-review-content"><?php esc_html_e( 'Your review', 'guidegrid-travel' ); ?></label>
									<textarea id="tg-review-content" name="content" class="tg-textarea" required></textarea>
									<span class="tg-field-error" role="alert"></span>
								</div>
								<button type="submit" class="tg-btn tg-btn--primary"><?php esc_html_e( 'Submit Review', 'guidegrid-travel' ); ?><span class="tg-spinner" aria-hidden="true"></span></button>
								<p class="tg-bw-note"><?php esc_html_e( 'Reviews are checked by our team before publishing.', 'guidegrid-travel' ); ?></p>
							</form>
						</div>
					</div>
				<?php else : ?>
					<p class="tg-mt-4"><a class="tg-btn tg-btn--secondary" href="<?php echo esc_url( tg_auth_url( get_permalink( $tour_id ) . '#tg-reviews', 'login' ) ); ?>"><?php esc_html_e( 'Log in to write a review', 'guidegrid-travel' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'tg_related_tours' ) ) {
	/**
	 * Related tours by destination / category.
	 *
	 * @param int $tour_id Tour post ID.
	 * @param int $count   Number of cards.
	 * @return void
	 */
	function tg_related_tours( int $tour_id, int $count = 3 ) {
		$dest_id = (int) tg_get_meta( $tour_id, '_tg_destination_id', 0 );
		$terms   = wp_get_post_terms( $tour_id, 'tour_category', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$collected = array();

		if ( $dest_id ) {
			$by_dest = new WP_Query(
				array(
					'post_type'      => 'tour',
					'posts_per_page' => $count,
					'post__not_in'   => array( $tour_id ),
					'no_found_rows'  => true,
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						array(
							'key'   => '_tg_destination_id',
							'value' => $dest_id,
						),
					),
				)
			);
			foreach ( $by_dest->posts as $found ) {
				$collected[] = $found->ID;
			}
			wp_reset_postdata();
		}

		if ( $terms ) {
			$by_cat = new WP_Query(
				array(
					'post_type'      => 'tour',
					'posts_per_page' => $count,
					'post__not_in'   => array_merge( array( $tour_id ), $collected ),
					'no_found_rows'  => true,
					'tax_query'      => array(
						array(
							'taxonomy' => 'tour_category',
							'field'    => 'term_id',
							'terms'    => $terms,
						),
					),
				)
			);
			foreach ( $by_cat->posts as $found ) {
				$collected[] = $found->ID;
			}
			wp_reset_postdata();
		}

		$collected = array_slice( array_unique( $collected ), 0, $count );

		$args = array(
			'post_type' => 'tour',
			'post__in'  => $collected,
			'orderby'   => 'post__in',
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			// Fallback: latest tours.
			$query = new WP_Query(
				array(
					'post_type'      => 'tour',
					'posts_per_page' => $count,
					'post__not_in'   => array( $tour_id ),
					'orderby'        => 'date',
				)
			);
		}

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return;
		}
		?>
		<div class="tg-card-grid">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				tg_tour_card( get_the_ID() );
			endwhile;
			?>
		</div>
		<?php
		wp_reset_postdata();
	}
}

if ( ! function_exists( 'tg_get_tour_options_for_select' ) ) {
	/**
	 * <option> markup for all tours (admin selects).
	 *
	 * @param int $selected Selected ID.
	 * @return string
	 */
	function tg_get_tour_options_for_select( int $selected ): string {
		$posts = get_posts(
			array(
				'post_type'      => 'tour',
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$html = '';
		foreach ( $posts as $post ) {
			$html .= '<option value="' . esc_attr( (string) $post->ID ) . '" ' . selected( $selected, $post->ID, false ) . '>' . esc_html( $post->post_title ) . '</option>';
		}
		return $html;
	}
}
