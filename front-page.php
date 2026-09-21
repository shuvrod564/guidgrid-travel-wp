<?php
/**
 * Front page: hero + search, destinations, popular tours, offers,
 * categories, experience, testimonials, blog, newsletter.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

get_header();

$settings      = tg_settings();

// FIX: Use !empty() instead of direct boolean evaluation to prevent PHP 8 "Undefined array key" warnings
$hero_image    = ! empty( $settings['hero_image_url'] ) ? $settings['hero_image_url'] : tg_demo_img( 'tg-hero-travel', 1600, 900 );

$tours_query   = new WP_Query(
    array(
        'post_type'      => 'tour',
        'posts_per_page' => 6,
        'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
            'relation'   => 'OR',
            array(
                'key'   => '_tg_featured',
                'value' => 1,
            ),
            array(
                'key'     => '_tg_booking_count',
                'value'   => 0,
                'compare' => '>',
            ),
        ),
        'orderby'        => array(
            'date' => 'DESC',
        ),
    )
);

$offers_query  = new WP_Query(
    array(
        'post_type'      => 'tour',
        'posts_per_page' => 4,
        'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
            array(
                'key'     => '_tg_previous_price',
                'value'   => 0,
                'compare' => '>',
            ),
        ),
    )
);

$destinations  = get_posts(
    array(
        'post_type'      => 'destination',
        'posts_per_page' => 4,
        'orderby'        => 'title',
        'order'          => 'ASC',
    )
);

$categories    = get_terms(
    array(
        'taxonomy'   => 'tour_category',
        'hide_empty' => false,
        'number'     => 10,
    )
);

$blog_posts    = get_posts(
    array(
        'post_type'      => 'post',
        'posts_per_page' => 3,
    )
);

$testimonial_rows = $GLOBALS['wpdb']->get_results( 'SELECT * FROM ' . TG_Database::table( 'reviews' ) . " WHERE status = 'approved' ORDER BY created_at DESC LIMIT 6" );
?>

<!-- ============ Hero ============ -->
<section class="tg-hero" style="background-image:url(<?php echo esc_url( $hero_image ); ?>);">
    <div class="tg-container">
        <!-- FIX: Apply !empty() check here as well to prevent similar warnings for hero_title -->
        <h1><?php echo esc_html( ! empty( $settings['hero_title'] ) ? $settings['hero_title'] : __( 'Explore the World, One Tour at a Time', 'guidegrid-travel' ) ); ?></h1>

        <!-- FIX: Apply !empty() check here as well to prevent similar warnings for hero_text -->
        <p><?php echo esc_html( ! empty( $settings['hero_text'] ) ? $settings['hero_text'] : __( 'Handcrafted tours, local guides and transparent pricing — from island escapes to Himalayan treks.', 'guidegrid-travel' ) ); ?></p>

        <?php get_template_part( 'template-parts/components/hero-search' ); ?>
    </div>
</section>

<!-- ============ Destinations ============ -->
<section class="tg-section">
    <div class="tg-container">
        <?php
        tg_section_heading(
            __( 'Popular Destinations', 'guidegrid-travel' ),
            __( 'Where would you like to go next?', 'guidegrid-travel' ),
            'center',
            __( 'Destinations', 'guidegrid-travel' )
        );
        ?>
        <?php if ( empty( $destinations ) ) : ?>
            <?php tg_empty_state( 'pin', __( 'No destinations yet', 'guidegrid-travel' ), __( 'Add destinations in the dashboard to show them here.', 'guidegrid-travel' ), admin_url( 'post-new.php?post_type=destination' ), __( 'Add a destination', 'guidegrid-travel' ) ); ?>
        <?php else : ?>
            <div class="tg-row">
                <?php
                foreach ( $destinations as $dest ) :
                    ?>
                    <div class="tg-col"><?php tg_destination_card( $dest ); ?></div>
                <?php endforeach; ?>
            </div>
            <p class="tg-text-center tg-mt-6">
                <a class="tg-btn tg-btn--secondary" href="<?php echo esc_url( get_post_type_archive_link( 'destination' ) ); ?>"><?php esc_html_e( 'View all destinations', 'guidegrid-travel' ); ?> →</a>
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- ============ Popular tours ============ -->
<section class="tg-section tg-section--surface">
    <div class="tg-container">
        <?php
        tg_section_heading(
            __( 'Popular Tours', 'guidegrid-travel' ),
            __( 'Most loved packages, booked by travelers like you.', 'guidegrid-travel' ),
            'center',
            __( 'Top Picks', 'guidegrid-travel' )
        );
        ?>
        <?php if ( ! $tours_query->have_posts() ) : ?>
            <?php tg_empty_state( 'compass', __( 'No tours published yet', 'guidegrid-travel' ), __( 'Create tours in the dashboard (or import demo data) to show them here.', 'guidegrid-travel' ), admin_url( 'post-new.php?post_type=tour' ), __( 'Create a tour', 'guidegrid-travel' ) ); ?>
        <?php else : ?>
            <div class="tg-card-grid">
                <?php
                while ( $tours_query->have_posts() ) :
                    $tours_query->the_post();
                    tg_tour_card( get_the_ID() );
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
            <p class="tg-text-center tg-mt-6">
                <a class="tg-btn tg-btn--secondary" href="<?php echo esc_url( get_post_type_archive_link( 'tour' ) ); ?>"><?php esc_html_e( 'Browse all tours', 'guidegrid-travel' ); ?> →</a>
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- ============ Why choose us ============ -->
<section class="tg-section">
    <div class="tg-container">
        <?php
        tg_section_heading(
            __( 'Why Travel with GuideGrid', 'guidegrid-travel' ),
            __( 'Everything you need for a safe, well-planned trip.', 'guidegrid-travel' ),
            'center',
            __( 'Our Promise', 'guidegrid-travel' )
        );
        ?>
        <div class="tg-feature-grid">
            <div class="tg-feature">
                <span class="tg-feature-icon"><?php echo tg_svg( 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <h3><?php esc_html_e( 'Verified & Safe', 'guidegrid-travel' ); ?></h3>
                <p><?php esc_html_e( 'Licensed operators, vetted guides and clear safety information.', 'guidegrid-travel' ); ?></p>
            </div>
            <div class="tg-feature">
                <span class="tg-feature-icon"><?php echo tg_svg( 'wallet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <h3><?php esc_html_e( 'Fair Pricing', 'guidegrid-travel' ); ?></h3>
                <p><?php esc_html_e( 'Transparent quotes with the full breakdown before you pay.', 'guidegrid-travel' ); ?></p>
            </div>
            <div class="tg-feature">
                <span class="tg-feature-icon"><?php echo tg_svg( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <h3><?php esc_html_e( 'Real Availability', 'guidegrid-travel' ); ?></h3>
                <p><?php esc_html_e( 'Live seat counts — what you book is actually available.', 'guidegrid-travel' ); ?></p>
            </div>
            <div class="tg-feature">
                <span class="tg-feature-icon"><?php echo tg_svg( 'support' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <h3><?php esc_html_e( '24/7 Support', 'guidegrid-travel' ); ?></h3>
                <p><?php esc_html_e( 'A human on the other side, before and during your trip.', 'guidegrid-travel' ); ?></p>
            </div>
            <div class="tg-feature">
                <span class="tg-feature-icon"><?php echo tg_svg( 'check-circle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <h3><?php esc_html_e( 'Easy Changes', 'guidegrid-travel' ); ?></h3>
                <p><?php esc_html_e( 'Clear cancellation rules and a simple lookup for every booking.', 'guidegrid-travel' ); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- ============ Special offers ============ -->
<?php if ( $offers_query->have_posts() ) : ?>
    <section class="tg-section tg-section--surface">
        <div class="tg-container">
            <?php
            tg_section_heading(
                __( 'Special Offers', 'guidegrid-travel' ),
                __( 'Limited-time deals on selected tours.', 'guidegrid-travel' ),
                'center',
                __( 'Deals', 'guidegrid-travel' )
            );
            ?>
            <div class="tg-offer-grid">
                <?php
                while ( $offers_query->have_posts() ) :
                    $offers_query->the_post();
                    $oid   = get_the_ID();
                    $price = tg_tour_price_info( $oid );
                    if ( $price['previous'] <= $price['base'] ) {
                        continue;
                    }
                    $pct   = (int) round( ( 1 - $price['base'] / $price['previous'] ) * 100 );
                    $valid = tg_get_next_available_date( $oid );
                    ?>
                    <a class="tg-offer" href="<?php echo esc_url( get_permalink( $oid ) ); ?>">
                        <span class="tg-offer-media">
                            <?php
                            if ( has_post_thumbnail( $oid ) ) {
                                echo get_the_post_thumbnail( $oid, 'tg-card' );
                            } else {
                                $demo_img = tg_get_meta( $oid, '_tg_demo_image', '' );
                                echo $demo_img ? '<img src="' . esc_url( $demo_img ) . '" alt="' . esc_attr( get_the_title( $oid ) ) . '" loading="lazy" />' : '<span class="tg-skeleton" style="position:absolute;inset:0;"></span>';
                            }
                            ?>
                            <span class="tg-offer-pct">−<?php echo esc_html( (string) $pct ); ?>%</span>
                        </span>
                        <span class="tg-offer-body">
                            <h3><?php echo esc_html( get_the_title( $oid ) ); ?></h3>
                            <span class="tg-offer-valid">
                                <?php
                                printf(
                                    /* translators: %s: date */
                                    esc_html__( 'Next departure: %s', 'guidegrid-travel' ),
                                    esc_html( $valid ? tg_format_date( $valid ) : __( 'On request', 'guidegrid-travel' ) )
                                );
                                ?>
                            </span>
                            <span class="tg-offer-price">
                                <span class="now"><?php echo esc_html( tg_format_price( $price['base'], $price['currency'] ) ); ?></span>
                                <span class="was"><?php echo esc_html( tg_format_price( $price['previous'], $price['currency'] ) ); ?></span>
                            </span>
                        </span>
                    </a>
                <?php endwhile;
                wp_reset_postdata();
                ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- ============ Categories ============ -->
<section class="tg-section">
    <div class="tg-container">
        <?php
        tg_section_heading(
            __( 'Browse by Category', 'guidegrid-travel' ),
            __( 'Find the kind of trip that fits you.', 'guidegrid-travel' ),
            'center',
            __( 'Categories', 'guidegrid-travel' )
        );
        ?>
        <?php if ( is_wp_error( $categories ) || empty( $categories ) ) : ?>
            <?php tg_empty_state( 'filter', __( 'No categories yet', 'guidegrid-travel' ), __( 'Create tour categories in the dashboard.', 'guidegrid-travel' ), admin_url( 'edit-tags.php?taxonomy=tour_category' ), __( 'Manage categories', 'guidegrid-travel' ) ); ?>
        <?php else : ?>
            <div class="tg-cat-grid">
                <?php
                foreach ( $categories as $cat ) :
                    ?>
                    <a class="tg-cat-tile" href="<?php echo esc_url( get_term_link( $cat ) ); ?>">
                        <span class="tg-cat-icon"><?php echo esc_html( tg_term_icon( $cat->term_id ) ); ?></span>
                        <strong><?php echo esc_html( $cat->name ); ?></strong>
                        <span><?php echo esc_html( sprintf( /* translators: %d: number of tours */ _n( '%d tour', '%d tours', $cat->count, 'guidegrid-travel' ), $cat->count ) ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============ Experience / editorial ============ -->
<section class="tg-section tg-section--surface">
    <div class="tg-container">
        <div class="tg-editorial">
            <div class="tg-editorial-media">
                <?php
                $editorial_img = get_post_meta( get_the_ID(), '_tg_editorial_image', true );
                if ( $editorial_img ) {
                    echo '<img src="' . esc_url( $editorial_img ) . '" alt="' . esc_attr__( 'Travel experience', 'guidegrid-travel' ) . '" loading="lazy" />';
                } else {
                    echo '<img src="' . esc_url( tg_demo_img( 'tg-editorial-guide', 900, 700 ) ) . '" alt="' . esc_attr__( 'Local guide with travelers', 'guidegrid-travel' ) . '" loading="lazy" />';
                }
                ?>
            </div>
            <div>
                <span class="tg-section-kicker"><?php esc_html_e( 'The GuideGrid Way', 'guidegrid-travel' ); ?></span>
                <h2><?php esc_html_e( 'Trips planned by people who actually went', 'guidegrid-travel' ); ?></h2>
                <p><?php esc_html_e( 'Every route we sell is scouted by our own team or partner guides. That means honest itineraries, realistic pace and no "surprises" on the road.', 'guidegrid-travel' ); ?></p>
                <ul>
                    <li><?php echo tg_svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Small groups with real local guides', 'guidegrid-travel' ); ?></li>
                    <li><?php echo tg_svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Flexible dates for private departures', 'guidegrid-travel' ); ?></li>
                    <li><?php echo tg_svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Instant booking confirmation & reminders', 'guidegrid-travel' ); ?></li>
                    <li><?php echo tg_svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Verified traveler reviews only', 'guidegrid-travel' ); ?></li>
                </ul>
                <a class="tg-btn tg-btn--primary" href="<?php echo esc_url( get_post_type_archive_link( 'travel_guide' ) ? get_post_type_archive_link( 'travel_guide' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Read travel guides', 'guidegrid-travel' ); ?></a>
            </div>
        </div>
    </div>
</section>

<!-- ============ Testimonials ============ -->
<section class="tg-section">
    <div class="tg-container">
        <?php
        tg_section_heading(
            __( 'What Travelers Say', 'guidegrid-travel' ),
            __( 'Real reviews from verified bookings.', 'guidegrid-travel' ),
            'center',
            __( 'Testimonials', 'guidegrid-travel' )
        );
        ?>
        <?php if ( empty( $testimonial_rows ) ) : ?>
            <?php tg_empty_state( 'award', __( 'No reviews yet', 'guidegrid-travel' ), __( 'Reviews from completed bookings will appear here.', 'guidegrid-travel' ) ); ?>
        <?php else : ?>
            <div class="tg-testimonial-grid">
                <?php
                foreach ( $testimonial_rows as $review ) :
                    $tour = get_post( (int) $review->tour_id );
                    ?>
                    <div class="tg-testimonial">
                        <span class="tg-stars" aria-hidden="true"><?php echo esc_html( str_repeat( '★', (int) $review->rating ) . str_repeat( '☆', 5 - (int) $review->rating ) ); ?></span>
                        <blockquote>“<?php echo esc_html( wp_trim_words( (string) $review->content, 32 ) ); ?>”</blockquote>
                        <div class="tg-testimonial-person">
                            <span class="avatar-fallback" aria-hidden="true"><?php echo esc_html( mb_substr( $review->name ? $review->name : 'T', 0, 1 ) ); ?></span>
                            <div>
                                <strong><?php echo esc_html( $review->name ? $review->name : __( 'Traveler', 'guidegrid-travel' ) ); ?></strong>
                                <span><?php echo $tour ? esc_html( get_the_title( $tour ) ) : ''; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============ Blog ============ -->
<?php if ( ! empty( $blog_posts ) ) : ?>
    <section class="tg-section tg-section--surface">
        <div class="tg-container">
            <?php
            tg_section_heading(
                __( 'From the Travel Blog', 'guidegrid-travel' ),
                __( 'Tips, guides and stories from the road.', 'guidegrid-travel' ),
                'center',
                __( 'Journal', 'guidegrid-travel' )
            );
            ?>
            <div class="tg-card-grid">
                <?php
                foreach ( $blog_posts as $blog_post ) :
                    tg_blog_card( $blog_post->ID );
                endforeach;
                ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php get_footer(); ?>
