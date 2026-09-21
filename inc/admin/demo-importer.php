<?php
/**
 * One-click demo content importer.
 *
 * Creates destinations, categories, activities, add-ons, 12 tours with
 * pricing/itineraries/availability, travel guides, blog posts, a welcome
 * coupon, sample reviews and the core pages + primary menu.
 *
 * All demo content is clearly marked as demo content. Remote placeholder
 * images (Picsum/Unsplash, free license) are used — replace them with
 * your own assets before going live.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_render_demo_page' ) ) {
	/**
	 * Render the demo page.
	 *
	 * @return void
	 */
	function tg_render_demo_page() {
		if ( ! current_user_can( 'manage_tg' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'guidegrid-travel' ) );
		}

		$installed = (bool) get_option( 'tg_demo_installed', false );

		if ( isset( $_POST['tg_demo_nonce'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			if ( wp_verify_nonce( sanitize_key( $_POST['tg_demo_nonce'] ), 'tg_demo_import' ) && isset( $_POST['tg_demo_action'] ) ) {
				if ( 'import' === $_POST['tg_demo_action'] ) {
					tg_run_demo_import();
					$installed = true;
				} elseif ( 'reset' === $_POST['tg_demo_action'] ) {
					update_option( 'tg_demo_installed', false );
					$installed = false;
				}
			}
		}
		?>
		<div class="wrap tg-admin">
			<h1><?php esc_html_e( 'Demo Data', 'guidegrid-travel' ); ?></h1>
			<?php if ( $installed ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Demo content is installed.', 'guidegrid-travel' ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-info"><p><?php esc_html_e( 'No demo content installed yet.', 'guidegrid-travel' ); ?></p></div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Import sample destinations, 12 tours with pricing, itineraries and availability, add-ons, reviews, a coupon and core pages. Demo images are free remote placeholders (Picsum/Unsplash) — replace them with your own before going live.', 'guidegrid-travel' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'tg_demo_import', 'tg_demo_nonce' ); ?>
				<p>
					<button type="submit" name="tg_demo_action" value="import" class="button button-primary" <?php echo $installed ? 'disabled' : ''; ?>><?php esc_html_e( 'Import Demo Content', 'guidegrid-travel' ); ?></button>
					<button type="submit" name="tg_demo_action" value="reset" class="button" <?php echo $installed ? '' : 'disabled'; ?>><?php esc_html_e( 'Mark as not installed (re-allow import)', 'guidegrid-travel' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}
}

/**
 * Run the demo import (idempotent per entity).
 *
 * @return void
 */
function tg_run_demo_import() {
	$destinations = array(
		'bali'       => array(
			'title'   => 'Bali',
			'country' => 'Indonesia',
			'region'  => 'Nusa Tenggara',
			'lat'     => '-8.3405',
			'lng'     => '115.0920',
			'best'    => 'April – October (dry season)',
			'temp'    => '27°C',
			'currency' => 'IDR',
			'lang'    => 'Indonesian',
			'tz'      => 'Asia/Makassar',
			'excerpt' => 'Island of the Gods: temples, rice terraces, waterfalls and world-class beaches.',
		),
		'cox-bazar'  => array(
			'title'   => "Cox's Bazar",
			'country' => 'Bangladesh',
			'region'  => 'Chattogram Division',
			'lat'     => '21.4272',
			'lng'     => '92.0058',
			'best'    => 'November – February',
			'temp'    => '26°C',
			'currency' => 'BDT',
			'lang'    => 'Bangla',
			'tz'      => 'Asia/Dhaka',
			'excerpt' => 'The longest natural sea beach in the world, coral islands and hillside escapes.',
		),
		'dubai'      => array(
			'title'   => 'Dubai',
			'country' => 'United Arab Emirates',
			'region'  => 'Gulf Region',
			'lat'     => '25.2048',
			'lng'     => '55.2708',
			'best'    => 'November – March',
			'temp'    => '29°C',
			'currency' => 'AED',
			'lang'    => 'Arabic',
			'tz'      => 'Asia/Dubai',
			'excerpt' => 'Desert dunes, skyline record-breakers and luxury shopping under one sky.',
		),
		'maldives'   => array(
			'title'   => 'Maldives',
			'country' => 'Maldives',
			'region'  => 'Indian Ocean',
			'lat'     => '3.2028',
			'lng'     => '73.2207',
			'best'    => 'December – April',
			'temp'    => '30°C',
			'currency' => 'MVR',
			'lang'    => 'Dhivehi',
			'tz'      => 'Indian/Malé',
			'excerpt' => 'Overwater villas, house reefs and the most turquoise water you have ever seen.',
		),
		'kathmandu'  => array(
			'title'   => 'Kathmandu',
			'country' => 'Nepal',
			'region'  => 'Himalayas',
			'lat'     => '27.7172',
			'lng'     => '85.3240',
			'best'    => 'October – November, March – May',
			'temp'    => '20°C',
			'currency' => 'NPR',
			'lang'    => 'Nepali',
			'tz'      => 'Asia/Kathmandu',
			'excerpt' => 'Gateway to the Himalayas: ancient temples, trekking trails and living culture.',
		),
		'istanbul'   => array(
			'title'   => 'Istanbul',
			'country' => 'Türkiye',
			'region'  => 'Bosphorus',
			'lat'     => '41.0082',
			'lng'     => '28.9784',
			'best'    => 'April – June, September – October',
			'temp'    => '22°C',
			'currency' => 'TRY',
			'lang'    => 'Turkish',
			'tz'      => 'Europe/Istanbul',
			'excerpt' => 'Two continents, bazaars and mosques — a city that never stops surprising.',
		),
	);

	$destination_ids = array();
	foreach ( $destinations as $slug => $data ) {
		$existing = get_page_by_path( $slug, OBJECT, 'destination' );
		if ( $existing ) {
			$destination_ids[ $slug ] = $existing->ID;
			continue;
		}
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'destination',
				'post_status'  => 'publish',
				'post_title'   => $data['title'],
				'post_name'    => $slug,
				'post_content' => $data['excerpt'],
				'post_excerpt' => $data['excerpt'],
			)
		);
		if ( $post_id && ! is_wp_error( $post_id ) ) {
			$destination_ids[ $slug ] = $post_id;
			update_post_meta( $post_id, '_tg_country', $data['country'] );
			update_post_meta( $post_id, '_tg_region', $data['region'] );
			update_post_meta( $post_id, '_tg_lat', $data['lat'] );
			update_post_meta( $post_id, '_tg_lng', $data['lng'] );
			update_post_meta( $post_id, '_tg_best_time', $data['best'] );
			update_post_meta( $post_id, '_tg_avg_temp', $data['temp'] );
			update_post_meta( $post_id, '_tg_currency', $data['currency'] );
			update_post_meta( $post_id, '_tg_language', $data['lang'] );
			update_post_meta( $post_id, '_tg_timezone', $data['tz'] );
			update_post_meta( $post_id, '_tg_travel_info', $data['excerpt'] . ' (demo content)' );
			// Featured image: remote URL stored as meta for display fallback.
			update_post_meta( $post_id, '_tg_demo_image', tg_demo_img( 'tg-dest-' . $slug, 1200, 700 ) );
		}
	}

	// Categories.
	$categories = array(
		'adventure'  => array( __( 'Adventure', 'guidegrid-travel' ), '🧗' ),
		'beach'      => array( __( 'Beach', 'guidegrid-travel' ), '🏖️' ),
		'cultural'   => array( __( 'Cultural', 'guidegrid-travel' ), '🏛️' ),
		'family'     => array( __( 'Family', 'guidegrid-travel' ), '👨‍👩‍👧' ),
		'luxury'     => array( __( 'Luxury', 'guidegrid-travel' ), '💎' ),
		'wildlife'   => array( __( 'Wildlife', 'guidegrid-travel' ), '🦁' ),
	);
	$category_ids = array();
	foreach ( $categories as $slug => $cat ) {
		$term = term_exists( $slug, 'tour_category' );
		if ( ! $term ) {
			$term = wp_insert_term( $cat[0], 'tour_category', array( 'slug' => $slug ) );
		}
		if ( ! is_wp_error( $term ) ) {
			$category_ids[ $slug ] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			update_term_meta( $category_ids[ $slug ], '_tg_term_icon', $cat[1] );
		}
	}

	// Activities.
	$activities = array(
		'hiking'       => __( 'Hiking', 'guidegrid-travel' ),
		'snorkeling'   => __( 'Snorkeling', 'guidegrid-travel' ),
		'scuba-diving' => __( 'Scuba Diving', 'guidegrid-travel' ),
		'safari'       => __( 'Safari', 'guidegrid-travel' ),
		'camping'      => __( 'Camping', 'guidegrid-travel' ),
		'boat-ride'    => __( 'Boat Ride', 'guidegrid-travel' ),
		'city-walking' => __( 'City Walking', 'guidegrid-travel' ),
		'food-tour'    => __( 'Food Tour', 'guidegrid-travel' ),
	);
	$activity_ids = array();
	foreach ( $activities as $slug => $name ) {
		$term = term_exists( $slug, 'activity' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'activity', array( 'slug' => $slug ) );
		}
		if ( ! is_wp_error( $term ) ) {
			$activity_ids[ $slug ] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
		}
	}

	// Add-ons.
	$addons = array(
		'airport-transfer'   => array( __( 'Airport Transfer', 'guidegrid-travel' ), 30, 'per_person', 1 ),
		'private-guide'      => array( __( 'Private Guide', 'guidegrid-travel' ), 80, 'per_booking', 1 ),
		'travel-insurance'   => array( __( 'Travel Insurance', 'guidegrid-travel' ), 20, 'per_person', 0 ),
		'equipment-rental'   => array( __( 'Equipment Rental', 'guidegrid-travel' ), 25, 'per_booking', 1 ),
		'photography'        => array( __( 'Professional Photography', 'guidegrid-travel' ), 60, 'per_booking', 1 ),
	);
	$addon_ids = array();
	foreach ( $addons as $slug => $data ) {
		$existing = get_page_by_path( $slug, OBJECT, 'tg_addon' );
		if ( $existing ) {
			$addon_ids[ $slug ] = $existing->ID;
			continue;
		}
		$addon_id = wp_insert_post(
			array(
				'post_type'    => 'tg_addon',
				'post_status'  => 'publish',
				'post_title'   => $data[0],
				'post_name'    => $slug,
				'post_content' => __( 'Optional extra service for tours. (demo content)', 'guidegrid-travel' ),
			)
		);
		if ( $addon_id && ! is_wp_error( $addon_id ) ) {
			$addon_ids[ $slug ] = $addon_id;
			update_post_meta( $addon_id, '_tg_addon_price', $data[1] );
			update_post_meta( $addon_id, '_tg_addon_unit', $data[2] );
			update_post_meta( $addon_id, '_tg_addon_taxable', $data[3] );
		}
	}

	// Tours.
	$tours = array(
		'bali-adventure-escape' => array(
			'title'      => 'Bali Adventure Escape',
			'dest'       => 'bali',
			'cats'       => array( 'adventure' ),
			'acts'       => array( 'hiking', 'snorkeling', 'camping' ),
			'duration'   => 5,
			'unit'       => 'days',
			'type'       => 'multi_day',
			'mode'       => 'group',
			'min_group'  => 2,
			'max_group'  => 12,
			'difficulty' => 'moderate',
			'adult'      => 499,
			'child'      => 299,
			'infant'     => 99,
			'previous'   => 599,
			'capacity'   => 12,
			'avail_mode' => 'fixed',
			'featured'   => 1,
			'excerpt'    => 'Five days of Ubud jungles, waterfalls, reef snorkeling and local food — Bali at full speed, with ease.',
			'highlights' => array( 'Ubud exploration', 'Tegallalang rice terraces', 'Sekumpul waterfall', 'Nusa Penida snorkeling', 'Balinese cooking class' ),
			'included'   => array( '4 nights boutique hotel', 'Daily breakfast', 'Private AC transport', 'English-speaking guide', 'Entry tickets' ),
			'excluded'   => array( 'International flights', 'Travel insurance', 'Personal expenses', 'Optional activities' ),
			'bring'      => array( 'Passport', 'Swimwear', 'Comfortable walking shoes', 'Rain jacket' ),
		),
		'cox-bazar-beach-escape' => array(
			'title'      => "Cox's Bazar Beach Escape",
			'dest'       => 'cox-bazar',
			'cats'       => array( 'beach', 'family' ),
			'acts'       => array( 'boat-ride', 'snorkeling' ),
			'duration'   => 3,
			'unit'       => 'days',
			'type'       => 'multi_day',
			'mode'       => 'group',
			'min_group'  => 2,
			'max_group'  => 20,
			'difficulty' => 'easy',
			'adult'      => 199,
			'child'      => 129,
			'infant'     => 49,
			'previous'   => 249,
			'capacity'   => 20,
			'avail_mode' => 'range',
			'featured'   => 1,
			'excerpt'    => 'Three days on the world\'s longest beach with Saint Martin\'s Island boat trip and sunset rides.',
			'highlights' => array( 'Haven Bay beachfront', 'Boat trip to coral coves', 'Sunset beach buggy ride', 'Local fish market tour' ),
			'included'   => array( '2 nights beach resort', 'Breakfast + dinner', 'AC van transfers', 'Guide' ),
			'excluded'   => array( 'Flights to Chattogram', 'Snorkeling gear', 'Personal expenses' ),
			'bring'      => array( 'Passport/ID', 'Swimwear', 'Sunscreen', 'Light clothing' ),
		),
		'dubai-desert-luxury' => array(
			'title'      => 'Dubai Desert Luxury Overnight',
			'dest'       => 'dubai',
			'cats'       => array( 'luxury' ),
			'acts'       => array( 'safari', 'camping' ),
			'duration'   => 2,
			'unit'       => 'days',
			'type'       => 'multi_day',
			'mode'       => 'group',
			'min_group'  => 2,
			'max_group'  => 10,
			'difficulty' => 'easy',
			'adult'      => 349,
			'child'      => 219,
			'infant'     => 79,
			'previous'   => 0,
			'capacity'   => 10,
			'avail_mode' => 'weekly',
			'weekly'     => array( 4, 5, 6 ),
			'featured'   => 0,
			'excerpt'    => 'Dune bashing by 4x4, camp dinner under the stars, and a skyline morning — Dubai after dark.',
			'highlights' => array( '4x4 dune safari', 'Luxury desert camp', 'Live tanoura show', 'Burj Khalifa night visit' ),
			'included'   => array( '1 night desert camp', 'Dinner + breakfast', 'Airport transfers', 'Guide' ),
			'excluded'   => array( 'Flights', 'Alcohol', 'Tip' ),
			'bring'      => array( 'Passport', 'Comfortable clothes', 'Camera' ),
		),
		'maldives-honeymoon' => array(
			'title'      => 'Maldives Honeymoon Pearl',
			'dest'       => 'maldives',
			'cats'       => array( 'luxury', 'beach' ),
			'acts'       => array( 'scuba-diving', 'boat-ride' ),
			'duration'   => 4,
			'unit'       => 'days',
			'type'       => 'multi_day',
			'mode'       => 'private',
			'min_group'  => 2,
			'max_group'  => 4,
			'difficulty' => 'easy',
			'adult'      => 1290,
			'child'      => 640,
			'infant'     => 190,
			'previous'   => 1490,
			'capacity'   => 4,
			'avail_mode' => 'fixed',
			'featured'   => 1,
			'excerpt'    => 'Overwater villa, private sandbank picnic and house-reef snorkeling for two.',
			'highlights' => array( 'Overwater villa', 'Private sandbank lunch', 'House reef snorkeling', 'Candlelight dinner' ),
			'included'   => array( '3 nights overwater villa', 'Daily breakfast', 'Speedboat transfers', 'Snorkel gear' ),
			'excluded'   => array( 'International flights', 'Scuba diving', 'Spa treatments' ),
			'bring'      => array( 'Passport', 'Swimwear', 'Light formal wear' ),
		),
		'kathmandu-himalaya-trek' => array(
			'title'      => 'Kathmandu & Himalaya View Trek',
			'dest'       => 'kathmandu',
			'cats'       => array( 'adventure', 'cultural' ),
			'acts'       => array( 'hiking', 'camping' ),
			'duration'   => 7,
			'unit'       => 'days',
			'type'       => 'multi_day',
			'mode'       => 'group',
			'min_group'  => 4,
			'max_group'  => 12,
			'min_age'    => 12,
			'difficulty' => 'challenging',
			'adult'      => 890,
			'child'      => 620,
			'infant'     => 0,
			'previous'   => 0,
			'capacity'   => 12,
			'avail_mode' => 'fixed',
			'featured'   => 0,
			'excerpt'    => 'Seven days of trails, tea houses and Everest-range panoramas from Kathmandu.',
			'highlights' => array( 'Everest panorama views', 'Namaste trekking route', 'Lobuche monastery visit', 'Kathmandu valley UNESCO sites' ),
			'included'   => array( '6 nights hotels/teahouses', 'Breakfast + dinner on trek', 'Permit fees', 'Sherpa guide + porter' ),
			'excluded'   => array( 'Flights', 'Lunches', 'Travel insurance (required)', 'Tips' ),
			'bring'      => array( 'Passport', 'Trekking boots', '40L daypack', 'Thermal layers' ),
		),
		'istanbul-culture-classic' => array(
			'title'      => 'Istanbul Culture Classic',
			'dest'       => 'istanbul',
			'cats'       => array( 'cultural', 'family' ),
			'acts'       => array( 'city-walking', 'boat-ride', 'food-tour' ),
			'duration'   => 4,
			'unit'       => 'days',
			'type'       => 'multi_day',
			'mode'       => 'group',
			'min_group'  => 2,
			'max_group'  => 16,
			'difficulty' => 'easy',
			'adult'      => 429,
			'child'      => 259,
			'infant'     => 89,
			'previous'   => 499,
			'capacity'   => 16,
			'avail_mode' => 'range',
			'featured'   => 0,
			'excerpt'    => 'Hagia Sophia, Grand Bazaar, Bosphorus cruise and a street-food crawl through Karaköy.',
			'highlights' => array( 'Hagia Sophia + Blue Mosque', 'Grand Bazaar shopping', 'Bosphorus sunset cruise', 'Street food tour' ),
			'included'   => array( '3 nights 4-star hotel', 'Breakfast', 'Bosphorus cruise ticket', 'Guide' ),
			'excluded'   => array( 'Flights', 'Lunches', 'Entry tips' ),
			'bring'      => array( 'Passport', 'Comfortable shoes', 'Scarf for mosques' ),
		),
		'bali-temple-wellness' => array(
			'title'      => 'Bali Temple & Wellness Retreat',
			'dest'       => 'bali',
			'cats'       => array( 'cultural', 'family' ),
			'acts'       => array( 'city-walking' ),
			'duration'   => 4,
			'unit'       => 'days',
			'type'       => 'multi_day',
			'mode'       => 'group',
			'min_group'  => 2,
			'max_group'  => 8,
			'difficulty' => 'easy',
			'adult'      => 640,
			'child'      => 380,
			'infant'     => 120,
			'previous'   => 0,
			'capacity'   => 8,
			'avail_mode' => 'weekly',
			'weekly'     => array( 1, 4 ),
			'featured'   => 0,
			'excerpt'    => 'Sunrise temple visits, yoga, spa days and flower-bath rituals in the Ubud green heart.',
			'highlights' => array( 'Tirta Empul water temple', 'Daily yoga sessions', 'Balinese spa day', 'Flower bath ritual' ),
			'included'   => array( '3 nights yoga villa', 'Breakfast + herbal tea', 'Spa credits (1x60min)', 'Guide' ),
			'excluded'   => array( 'Flights', 'Lunches & dinners', 'Extra spa treatments' ),
			'bring'      => array( 'Passport', 'Yoga mat (optional)', 'Modest wear for temples' ),
		),
		'cox-bazar-hill-trek' => array(
			'title'      => 'Sajek Valley & Hill Trek',
			'dest'       => 'cox-bazar',
			'cats'       => array( 'adventure', 'wildlife' ),
			'acts'       => array( 'hiking', 'camping' ),
			'duration'   => 4,
			'unit'       => 'days',
			'type'       => 'multi_day',
			'mode'       => 'group',
			'min_group'  => 3,
			'max_group'  => 10,
			'min_age'    => 8,
			'difficulty' => 'moderate',
			'adult'      => 289,
			'child'      => 189,
			'infant'     => 59,
			'previous'   => 349,
			'capacity'   => 10,
			'avail_mode' => 'fixed',
			'featured'   => 0,
			'excerpt'    => 'From beach fog to Sajek\'s alpine meadows: a short, stunning Bengal hill circuit.',
			'highlights' => array( 'Sajek lake viewpoints', 'Tea garden walks', 'Sunrise from Phulbari', 'Local homestay dinner' ),
			'included'   => array( '3 nights (hotel + homestay)', 'Breakfast + 2 dinners', 'Jeep transfers', 'Local guide' ),
			'excluded'   => array( 'Flights', 'Lunches', 'Camping gear rental' ),
			'bring'      => array( 'ID', 'Warm layer', 'Headlamp', 'Rain cover' ),
		),
		'dubai-city-skyline' => array(
			'title'      => 'Dubai City & Skyline Day Tour',
			'dest'       => 'dubai',
			'cats'       => array( 'family', 'cultural' ),
			'acts'       => array( 'city-walking' ),
			'duration'   => 1,
			'unit'       => 'days',
			'type'       => 'day_tour',
			'mode'       => 'group',
			'min_group'  => 2,
			'max_group'  => 24,
			'difficulty' => 'easy',
			'adult'      => 129,
			'child'      => 79,
			'infant'     => 25,
			'previous'   => 0,
			'capacity'   => 24,
			'avail_mode' => 'any',
			'featured'   => 0,
			'excerpt'    => 'Burj Khalifa, Dubai Fountain, old Dubai creek and a skyline lunch — a full day, zero stress.',
			'highlights' => array( 'Burj Khalifa 124th floor', 'Dubai Fountain show', 'Abra ride across the creek', 'Gold & spice souks' ),
			'included'   => array( 'Burj Khalifa ticket', 'Lunch at Burj View restaurant', 'AC coach + guide' ),
			'excluded'   => array( 'Hotel pickup (optional add-on)', 'Shopping' ),
			'bring'      => array( 'ID', 'Camera', 'Comfortable shoes' ),
		),
		'maldives-scuba-safari' => array(
			'title'      => 'Maldives Scuba Safari',
			'dest'       => 'maldives',
			'cats'       => array( 'adventure', 'wildlife' ),
			'acts'       => array( 'scuba-diving', 'snorkeling' ),
			'duration'   => 5,
			'unit'       => 'days',
			'type'       => 'multi_day',
			'mode'       => 'group',
			'min_group'  => 4,
			'max_group'  => 8,
			'min_age'    => 14,
			'difficulty' => 'moderate',
			'adult'      => 1150,
			'child'      => 890,
			'infant'     => 0,
			'previous'   => 1290,
			'capacity'   => 8,
			'avail_mode' => 'fixed',
			'featured'   => 0,
			'excerpt'    => 'Five days, twelve dives across atolls — manta season reefs, house-reef walls and night dives.',
			'highlights' => array( '12 guided dives', 'Manta ray season reefs', 'Night dive', 'PADI instructors on board' ),
			'included'   => array( '4 nights liveaboard', 'All meals on boat', 'Divings + gear', 'Nitrox' ),
			'excluded'   => array( 'Flights + seaplane', 'Alcohol', 'PADI certification' ),
			'bring'      => array( 'Dive log', 'Passport', 'Reef-safe sunscreen' ),
		),
		'kathmandu-valley-heritage' => array(
			'title'      => 'Kathmandu Valley Heritage Walk',
			'dest'       => 'kathmandu',
			'cats'       => array( 'cultural' ),
			'acts'       => array( 'city-walking', 'food-tour' ),
			'duration'   => 2,
			'unit'       => 'days',
			'type'       => 'multi_day',
			'mode'       => 'group',
			'min_group'  => 2,
			'max_group'  => 14,
			'difficulty' => 'easy',
			'adult'      => 219,
			'child'      => 139,
			'infant'     => 49,
			'previous'   => 0,
			'capacity'   => 14,
			'avail_mode' => 'weekly',
			'weekly'     => array( 0, 3, 6 ),
			'featured'   => 0,
			'excerpt'    => 'Durbar squares, Swayambhunath and a Newar cooking lesson — the valley in 48 hours.',
			'highlights' => array( 'Kathmandu + Patan Durbar squares', 'Swayambhunath sunset', 'Boudhanath stupa', 'Newar cooking class' ),
			'included'   => array( '1 night heritage hotel', 'Breakfast', 'Entry fees', 'Guide' ),
			'excluded'   => array( 'Flights', 'Lunches (cooking class includes dinner)' ),
			'bring'      => array( 'Passport', 'Comfortable shoes', 'Small cash for tips' ),
		),
		'istanbul-gastronomy-crawl' => array(
			'title'      => 'Istanbul Gastronomy Crawl',
			'dest'       => 'istanbul',
			'cats'       => array( 'family', 'cultural' ),
			'acts'       => array( 'food-tour' ),
			'duration'   => 1,
			'unit'       => 'hours',
			'type'       => 'day_tour',
			'mode'       => 'group',
			'min_group'  => 2,
			'max_group'  => 10,
			'difficulty' => 'easy',
			'adult'      => 89,
			'child'      => 49,
			'infant'     => 0,
			'previous'   => 109,
			'capacity'   => 10,
			'avail_mode' => 'weekly',
			'weekly'     => array( 5, 6 ),
			'featured'   => 0,
			'excerpt'    => 'Six hours, ten stops: baklava at dawn, balik ekmek at noon, lokum and Turkish coffee after.',
			'highlights' => array( '10 food stops', 'Baklava masterclass', 'Grand Bazaar tastings', 'Turkish coffee ceremony' ),
			'included'   => array( 'All tastings', 'Food guide', 'Small-group (max 10)' ),
			'excluded'   => array( 'Drinks', 'Hotel pickup' ),
			'bring'      => array( 'Appetite', 'Comfortable shoes' ),
		),
	);

	$tour_ids = array();
	$today_ts = time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

	foreach ( $tours as $slug => $t ) {
		$existing = get_page_by_path( $slug, OBJECT, 'tour' );
		if ( $existing ) {
			$tour_ids[ $slug ] = $existing->ID;
			continue;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'tour',
				'post_status'  => 'publish',
				'post_title'   => $t['title'],
				'post_name'    => $slug,
				'post_content' => $t['excerpt'] . "\n\n" . __( 'This is demo content created by the GuideGrid Travel demo importer. Replace it with your own packages.', 'guidegrid-travel' ),
				'post_excerpt' => $t['excerpt'],
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			continue;
		}
		$tour_ids[ $slug ] = $post_id;

		update_post_meta( $post_id, '_tg_destination_id', $destination_ids[ $t['dest'] ] ?? 0 );
		$dest_data       = $destinations[ $t['dest'] ] ?? array();
		update_post_meta( $post_id, '_tg_country', $dest_data['country'] ?? '' );
		update_post_meta( $post_id, '_tg_lat', $dest_data['lat'] ?? '' );
		update_post_meta( $post_id, '_tg_lng', $dest_data['lng'] ?? '' );
		update_post_meta( $post_id, '_tg_meeting_point', $dest_data['title'] . ' city center (demo)' );
		update_post_meta( $post_id, '_tg_duration', $t['duration'] );
		update_post_meta( $post_id, '_tg_duration_unit', $t['unit'] );
		update_post_meta( $post_id, '_tg_tour_type', $t['type'] );
		update_post_meta( $post_id, '_tg_tour_mode', $t['mode'] );
		update_post_meta( $post_id, '_tg_min_group', $t['min_group'] );
		update_post_meta( $post_id, '_tg_max_group', $t['max_group'] );
		if ( isset( $t['min_age'] ) ) {
			update_post_meta( $post_id, '_tg_min_age', $t['min_age'] );
		}
		update_post_meta( $post_id, '_tg_difficulty', $t['difficulty'] );
		update_post_meta( $post_id, '_tg_language', 'English' );
		update_post_meta( $post_id, '_tg_adult_price', $t['adult'] );
		update_post_meta( $post_id, '_tg_child_price', $t['child'] );
		update_post_meta( $post_id, '_tg_infant_price', $t['infant'] );
		update_post_meta( $post_id, '_tg_currency', 'USD' );
		update_post_meta( $post_id, '_tg_previous_price', $t['previous'] );
		update_post_meta( $post_id, '_tg_capacity', $t['capacity'] );
		update_post_meta( $post_id, '_tg_featured', $t['featured'] );

		// Availability.
		update_post_meta( $post_id, '_tg_availability_mode', $t['avail_mode'] );
		if ( 'fixed' === $t['avail_mode'] ) {
			$dates = array();
			foreach ( array( 7, 14, 21, 28, 35, 42, 49, 56 ) as $offset ) {
				$dates[] = gmdate( 'Y-m-d', $today_ts + ( $offset * DAY_IN_SECONDS ) );
			}
			update_post_meta( $post_id, '_tg_availability_dates', implode( "\n", $dates ) );
		} elseif ( 'range' === $t['avail_mode'] ) {
			update_post_meta( $post_id, '_tg_range_start', gmdate( 'Y-m-d', $today_ts + 3 * DAY_IN_SECONDS ) );
			update_post_meta( $post_id, '_tg_range_end', gmdate( 'Y-m-d', $today_ts + 60 * DAY_IN_SECONDS ) );
		} elseif ( 'weekly' === $t['avail_mode'] ) {
			update_post_meta( $post_id, '_tg_weekly_days', $t['weekly'] );
		}

		// Terms.
		wp_set_object_terms( $post_id, array_map( fn( $s ) => $category_ids[ $s ] ?? 0, $t['cats'] ), 'tour_category' );
		wp_set_object_terms( $post_id, array_map( fn( $s ) => $activity_ids[ $s ] ?? 0, $t['acts'] ), 'activity' );

		// Add-ons (all of them for the demo).
		update_post_meta( $post_id, '_tg_addons', array_values( $addon_ids ) );

		// Content lists.
		update_post_meta( $post_id, '_tg_highlights', implode( "\n", $t['highlights'] ) );
		update_post_meta( $post_id, '_tg_included', implode( "\n", $t['included'] ) );
		update_post_meta( $post_id, '_tg_excluded', implode( "\n", $t['excluded'] ) );
		update_post_meta( $post_id, '_tg_what_to_bring', implode( "\n", $t['bring'] ) );
		update_post_meta( $post_id, '_tg_important_info', __( 'Arrive 30 minutes before departure. Carry a valid ID/passport. The operator may adjust the itinerary due to weather or local conditions. (demo)', 'guidegrid-travel' ) );
		update_post_meta( $post_id, '_tg_cancellation_policy', __( "More than 14 days before travel: 100% refundable.\n7–13 days: 50% refundable.\nLess than 7 days: non-refundable. (demo policy)", 'guidegrid-travel' ) );

		// Itinerary: build from duration.
		$itinerary = array();
		$day_count = ( 'hours' === $t['unit'] ) ? 1 : $t['duration'];
		$it_templates = array(
			0 => array( 'title' => 'Arrival & Briefing', 'description' => 'Welcome transfer, hotel check-in and an orientation walk with your guide.', 'meals' => '—', 'accommodation' => 'Hotel', 'notes' => 'Travel distance: ~20 km.' ),
			1 => array( 'title' => 'Guided Exploration Day', 'description' => 'Full-day guided sightseeing covering the top highlights of the route.', 'meals' => 'Breakfast', 'accommodation' => 'Hotel', 'notes' => '' ),
			2 => array( 'title' => 'Adventure & Local Experience', 'description' => 'Morning activity, afternoon free time and an evening local experience.', 'meals' => 'Breakfast + Dinner', 'accommodation' => 'Hotel', 'notes' => '' ),
			3 => array( 'title' => 'Scenic Highlights', 'description' => 'Scenic drives, viewpoints and a visit to a signature location.', 'meals' => 'Breakfast', 'accommodation' => 'Hotel', 'notes' => '' ),
			4 => array( 'title' => 'Free Day & Markets', 'description' => 'Optional activities, market visits and souvenir shopping.', 'meals' => 'Breakfast', 'accommodation' => 'Hotel', 'notes' => '' ),
			5 => array( 'title' => 'Final Morning & Excursion', 'description' => 'Last excursion in the morning, then free time before checkout.', 'meals' => 'Breakfast', 'accommodation' => 'Hotel', 'notes' => '' ),
			6 => array( 'title' => 'Departure', 'description' => 'Checkout and transfer to the airport/station. Safe travels!', 'meals' => '—', 'accommodation' => '—', 'notes' => '' ),
		);
		for ( $d = 0; $d < $day_count && $d < 7; $d++ ) {
			$itinerary[] = $it_templates[ $d ];
		}
		if ( $itinerary ) {
			update_post_meta( $post_id, '_tg_itinerary', $itinerary );
		}

		// FAQ.
		update_post_meta(
			$post_id,
			'_tg_faq',
			array(
				array(
					'question' => __( 'Do I need a visa?', 'guidegrid-travel' ),
					'answer'   => __( 'Visa requirements depend on your nationality — check with the embassy or our team before booking. (demo)', 'guidegrid-travel' ),
				),
				array(
					'question' => __( 'What is the cancellation policy?', 'guidegrid-travel' ),
					'answer'   => __( 'See the Cancellation Policy section on the tour page. (demo)', 'guidegrid-travel' ),
				),
			)
		);

		// Demo image reference (remote placeholders — no media library upload).
		update_post_meta( $post_id, '_tg_demo_image', tg_demo_img( 'tg-tour-' . $slug, 1200, 700 ) );
		update_post_meta( $post_id, '_tg_demo_gallery', array(
			tg_demo_img( 'tg-tour-' . $slug . '-1', 1200, 700 ),
			tg_demo_img( 'tg-tour-' . $slug . '-2', 1200, 700 ),
			tg_demo_img( 'tg-tour-' . $slug . '-3', 1200, 700 ),
		) );
	}

	// Travel guides.
	$guides = array(
		array( 'bali-when-to-visit', 'When to Visit Bali', 'bali', 'planning' ),
		array( 'packing-for-himalaya', 'Packing for a Himalaya Trek', 'kathmandu', 'packing' ),
		array( 'istanbul-food-map', 'Istanbul: A Food Lover\'s Map', 'istanbul', 'food' ),
	);
	foreach ( $guides as $g ) {
		$existing = get_page_by_path( $g[0], OBJECT, 'travel_guide' );
		if ( $existing ) {
			continue;
		}
		$gid = wp_insert_post(
			array(
				'post_type'    => 'travel_guide',
				'post_status'  => 'publish',
				'post_title'   => $g[1],
				'post_name'    => $g[0],
				'post_content' => __( 'Demo guide content. Expand with sections, tips and practical information.', 'guidegrid-travel' ),
			)
		);
		if ( $gid && ! is_wp_error( $gid ) ) {
			update_post_meta( $gid, '_tg_guide_destination', $destination_ids[ $g[2] ] ?? 0 );
			update_post_meta( $gid, '_tg_guide_type', $g[3] );
		}
	}

	// Blog posts.
	$posts = array(
		array( 'best-time-cox-bazar', 'Best Time to Visit Cox\'s Bazar', 'Winter is when the Bay of Bengal goes calm: November to February brings the clearest water and the gentlest breeze. Here is how to plan a perfect coastal week.' ),
		array( 'maldives-bucket-list', 'A Maldives Bucket List in 48 Hours', 'Speedboat to a sandbank, sunrise on the house reef, fish curry on the jetty — here is how we squeeze the islands into two glorious days.' ),
	);
	foreach ( $posts as $p ) {
		$existing = get_page_by_path( $p[0], OBJECT, 'post' );
		if ( $existing ) {
			continue;
		}
		$pid = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => $p[1],
				'post_name'    => $p[0],
				'post_content' => $p[2] . ' (demo post)',
			)
		);
		if ( $pid && ! is_wp_error( $pid ) ) {
			// Replaced undefined function get_category_id() with core WordPress function get_cat_ID()
			$cat_id = get_cat_ID( 'Uncategorized' );
			if ( $cat_id ) {
				wp_set_post_categories( $pid, array( $cat_id ) );
			}
		}
	}

	// Coupon.
	$coupon_exists = TG_Coupons::get( 'WELCOME10' );
	if ( ! $coupon_exists ) {
		TG_Coupons::save(
			array(
				'code'           => 'WELCOME10',
				'description'    => __( 'Welcome coupon — 10% off your first booking (demo).', 'guidegrid-travel' ),
				'discount_type'  => 'percent',
				'discount_value' => 10,
				'min_amount'     => 100,
				'max_discount'   => 100,
				'starts_at'      => gmdate( 'Y-m-d', $today_ts - 5 * DAY_IN_SECONDS ),
				'expires_at'     => gmdate( 'Y-m-d', $today_ts + 60 * DAY_IN_SECONDS ),
				'usage_limit'    => 0,
				'per_customer_limit' => 1,
				'active'         => true,
			)
		);
	}

	// Sample reviews (spread across tours).
	$tour_list = array_values( $tour_ids );
	$reviewers = array(
		array( 'Amina Rahman', 'amina.r@example.com', 5, 'Unforgettable trip!', 'The guide was brilliant, hotels were spotless and every day felt effortless. Booked another tour already.' ),
		array( 'Daniel Carter', 'daniel.c@example.com', 4, 'Great value', 'Very well organized. A couple of small delays but the team handled everything smoothly.' ),
		array( 'Sofia Mendes', 'sofia.m@example.com', 5, 'Perfect honeymoon', 'Overwater villa was even better than the photos. The private sandbank lunch was magical.' ),
		array( 'Liam O\'Brien', 'liam.ob@example.com', 4, 'Solid trek', 'Challenging in the right places. Porters and sherpa were incredible. Bring warm layers!' ),
		array( 'Fatima Al-Sayed', 'fatima.s@example.com', 5, 'Desert dreams', 'Dune bashing at sunset, dinner under the stars — we will be back for the summer camp.' ),
		array( 'Kenji Watanabe', 'kenji.w@example.com', 5, 'Dives of a lifetime', 'Manta season was at its peak. Twelve dives with perfect visibility. Instructors were top class.' ),
	);
	$global     = $GLOBALS['wpdb'];
	$reviews_tbl = TG_Database::table( 'reviews' );
	$existing_reviews = (int) $global->get_var( 'SELECT COUNT(*) FROM ' . $reviews_tbl );
	if ( $existing_reviews < 5 && $tour_list ) {
		foreach ( $reviewers as $i => $r ) {
			$tour_id = $tour_list[ $i % count( $tour_list ) ];
			$global->insert(
				$reviews_tbl,
				array(
					'tour_id'        => $tour_id,
					'booking_id'     => 0,
					'user_id'        => 0,
					'customer_email' => $r[1],
					'name'           => $r[0],
					'rating'         => $r[2],
					'title'          => $r[3],
					'content'        => $r[4],
					'verified'       => 1,
					'status'         => 'approved',
					'created_at'     => gmdate( 'Y-m-d H:i:s', $today_ts - ( ( $i + 3 ) * DAY_IN_SECONDS ) ),
				),
				array( '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
			);
			TG_Reviews::recalc_tour_meta( $tour_id );
		}
	}

	// Pages + menu (only when missing).
	tg_create_workflow_pages();
	$demo_pages = array(
		array( 'about', __( 'About Us', 'guidegrid-travel' ) ),
		array( 'faq', __( 'FAQ', 'guidegrid-travel' ) ),
		array( 'offers', __( 'Special Offers', 'guidegrid-travel' ) ),
	);
	foreach ( $demo_pages as $dp ) {
		if ( ! get_page_by_path( $dp[0], OBJECT, 'page' ) ) {
			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $dp[1],
					'post_name'    => $dp[0],
					'post_content' => __( 'Demo page — replace with your own content.', 'guidegrid-travel' ),
				)
			);
		}
	}

	// Primary menu.
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	if ( empty( $locations['primary'] ) ) {
		$menu_id = wp_create_nav_menu( __( 'Primary', 'guidegrid-travel' ) );
		if ( ! is_wp_error( $menu_id ) ) {
			$items = array(
				array( __( 'Home', 'guidegrid-travel' ), home_url( '/' ), 0 ),
				array( __( 'Tours', 'guidegrid-travel' ), get_post_type_archive_link( 'tour' ) ? get_post_type_archive_link( 'tour' ) : '#', 0 ),
				array( __( 'Destinations', 'guidegrid-travel' ), get_post_type_archive_link( 'destination' ) ? get_post_type_archive_link( 'destination' ) : '#', 0 ),
				array( __( 'Travel Guides', 'guidegrid-travel' ), get_post_type_archive_link( 'travel_guide' ) ? get_post_type_archive_link( 'travel_guide' ) : '#', 0 ),
				array( __( 'Blog', 'guidegrid-travel' ), home_url( '/blog/' ), 0 ),
				array( __( 'About', 'guidegrid-travel' ), get_permalink( tg_page_id_by_path( 'about' ) ), 0 ),
				array( __( 'Contact', 'guidegrid-travel' ), get_permalink( tg_page_id_by_path( 'contact' ) ), 0 ),
			);
			foreach ( $items as $item ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'     => $item[0],
						'menu-item-url'       => $item[1],
						'menu-item-status'    => 'publish',
						'menu-item-type'      => 'custom',
					)
				);
			}
			set_theme_mod( 'nav_menu_locations', array( 'primary' => (int) $menu_id ) );
		}
	}

	// Front page.
	$home = get_page_by_path( 'home', OBJECT, 'page' );
	if ( ! $home ) {
		$home_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Home', 'guidegrid-travel' ),
				'post_name'    => 'home',
				'post_content' => '',
			)
		);
		if ( $home_id && ! is_wp_error( $home_id ) ) {
			$home = get_post( $home_id );
		}
	}
	if ( $home ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home->ID );
	}

	update_option( 'tg_demo_installed', time() );
	do_action( 'tg_demo_imported' );
}
