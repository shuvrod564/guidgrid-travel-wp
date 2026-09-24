<?php
/**
 * Database layer for GuideGrid Travel booking engine.
 *
 * All custom tables are created with the site's WPDB prefix and managed
 * via dbDelta on theme activation / version change. No table name is
 * ever hardcoded to "wp_".
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TG_Database
 */
final class TG_Database {

	/**
	 * Option key storing the installed DB version.
	 */
	const VERSION_OPTION = 'tg_db_version';

	/**
	 * Return the fully prefixed table name.
	 *
	 * @param string $key Short key from self::tables().
	 * @return string
	 */
	public static function table( string $key ): string {
		$global  = $GLOBALS['wpdb'];
		$prefix  = $global->prefix;
		return $prefix . 'tg_' . $key;
	}

	/**
	 * Mapping of table keys to their create SQL.
	 *
	 * @return array<string,string>
	 */
	public static function tables(): array {
		$global = $GLOBALS['wpdb'];
		$char   = $global->get_charset_collate();
		$prefix = $global->prefix;

		return array(
			'bookings'        => "CREATE TABLE {$prefix}tg_bookings (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				booking_number varchar(40) NOT NULL,
				customer_id bigint(20) unsigned DEFAULT 0,
				tour_id bigint(20) unsigned NOT NULL,
				booking_date date NOT NULL,
				tour_end_date date NULL,
				adult_count int(11) unsigned NOT NULL DEFAULT 1,
				child_count int(11) unsigned NOT NULL DEFAULT 0,
				infant_count int(11) unsigned NOT NULL DEFAULT 0,
				adult_unit_price decimal(10,2) NOT NULL DEFAULT 0,
				child_unit_price decimal(10,2) NOT NULL DEFAULT 0,
				infant_unit_price decimal(10,2) NOT NULL DEFAULT 0,
				subtotal decimal(10,2) NOT NULL DEFAULT 0,
				discount decimal(10,2) NOT NULL DEFAULT 0,
				coupon_code varchar(64) NOT NULL DEFAULT '',
				tax decimal(10,2) NOT NULL DEFAULT 0,
				service_fee decimal(10,2) NOT NULL DEFAULT 0,
				deposit decimal(10,2) NOT NULL DEFAULT 0,
				total decimal(10,2) NOT NULL DEFAULT 0,
				currency char(3) NOT NULL DEFAULT 'USD',
				price_snapshot longtext NULL,
				addons longtext NULL,
				payment_status varchar(24) NOT NULL DEFAULT 'unpaid',
				booking_status varchar(24) NOT NULL DEFAULT 'pending',
				hold_expires_at datetime NULL,
				customer_data longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY booking_number (booking_number),
				KEY tour_id (tour_id),
				KEY booking_date (booking_date),
				KEY booking_status (booking_status),
				KEY customer_id (customer_id)
			) $char;",

			'booking_items'   => "CREATE TABLE {$prefix}tg_booking_items (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				booking_id bigint(20) unsigned NOT NULL,
				item_type varchar(16) NOT NULL DEFAULT 'addon',
				ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
				name varchar(255) NOT NULL DEFAULT '',
				unit_price decimal(10,2) NOT NULL DEFAULT 0,
				quantity int(11) unsigned NOT NULL DEFAULT 1,
				amount decimal(10,2) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY booking_id (booking_id)
			) $char;",

			'payments'        => "CREATE TABLE {$prefix}tg_payments (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				booking_id bigint(20) unsigned NOT NULL,
				gateway varchar(40) NOT NULL DEFAULT 'manual',
				transaction_id varchar(190) NOT NULL DEFAULT '',
				amount decimal(10,2) NOT NULL DEFAULT 0,
				currency char(3) NOT NULL DEFAULT 'USD',
				status varchar(24) NOT NULL DEFAULT 'pending',
				payment_method varchar(40) NOT NULL DEFAULT '',
				gateway_response longtext NULL,
				refund_ref varchar(190) NOT NULL DEFAULT '',
				paid_at datetime NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY booking_id (booking_id),
				KEY transaction_id (transaction_id)
			) $char;",

			'availability'    => "CREATE TABLE {$prefix}tg_availability (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				tour_id bigint(20) unsigned NOT NULL,
				availability_date date NOT NULL,
				capacity int(11) unsigned NOT NULL DEFAULT 0,
				reserved int(11) unsigned NOT NULL DEFAULT 0,
				hold int(11) unsigned NOT NULL DEFAULT 0,
				status varchar(24) NOT NULL DEFAULT 'open',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY tour_date (tour_id, availability_date),
				KEY availability_date (availability_date)
			) $char;",

			'pricing_rules'   => "CREATE TABLE {$prefix}tg_pricing_rules (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				tour_id bigint(20) unsigned NOT NULL,
				rule_type varchar(24) NOT NULL DEFAULT 'seasonal',
				start_date date NULL,
				end_date date NULL,
				days_of_week varchar(32) NOT NULL DEFAULT '',
				adult_price decimal(10,2) NOT NULL DEFAULT 0,
				child_price decimal(10,2) NOT NULL DEFAULT 0,
				infant_price decimal(10,2) NOT NULL DEFAULT 0,
				priority tinyint(1) NOT NULL DEFAULT 0,
				status tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY tour_id (tour_id),
				KEY rule_type (rule_type)
			) $char;",

			'coupons'         => "CREATE TABLE {$prefix}tg_coupons (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				code varchar(64) NOT NULL,
				description text NULL,
				discount_type varchar(16) NOT NULL DEFAULT 'percent',
				discount_value decimal(10,2) NOT NULL DEFAULT 0,
				min_amount decimal(10,2) NOT NULL DEFAULT 0,
				max_discount decimal(10,2) NOT NULL DEFAULT 0,
				starts_at date NULL,
				expires_at date NULL,
				usage_limit int(11) unsigned NOT NULL DEFAULT 0,
				per_customer_limit int(11) unsigned NOT NULL DEFAULT 0,
				usage_count int(11) unsigned NOT NULL DEFAULT 0,
				applicable_tours text NULL,
				applicable_categories text NULL,
				applicable_destinations text NULL,
				active tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY code (code)
			) $char;",

			'coupon_usage'    => "CREATE TABLE {$prefix}tg_coupon_usage (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				coupon_id bigint(20) unsigned NOT NULL,
				booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
				customer_key varchar(190) NOT NULL DEFAULT '',
				used_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY coupon_id (coupon_id),
				KEY customer_key (customer_key)
			) $char;",

			'customers'       => "CREATE TABLE {$prefix}tg_customers (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				email varchar(190) NOT NULL,
				name varchar(190) NOT NULL DEFAULT '',
				phone varchar(64) NOT NULL DEFAULT '',
				country varchar(120) NOT NULL DEFAULT '',
				address text NULL,
				notes text NULL,
				account_status varchar(24) NOT NULL DEFAULT 'active',
				booking_count int(11) unsigned NOT NULL DEFAULT 0,
				total_spent decimal(12,2) NOT NULL DEFAULT 0,
				last_booking_at datetime NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY email (email),
				KEY user_id (user_id)
			) $char;",

			'reviews'         => "CREATE TABLE {$prefix}tg_reviews (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				tour_id bigint(20) unsigned NOT NULL,
				booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				customer_email varchar(190) NOT NULL DEFAULT '',
				name varchar(190) NOT NULL DEFAULT '',
				rating tinyint(1) unsigned NOT NULL DEFAULT 5,
				title varchar(255) NOT NULL DEFAULT '',
				content longtext NULL,
				verified tinyint(1) NOT NULL DEFAULT 0,
				status varchar(16) NOT NULL DEFAULT 'pending',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY tour_id (tour_id),
				KEY status (status),
				KEY user_id (user_id)
			) $char;",

			'booking_meta'    => "CREATE TABLE {$prefix}tg_booking_meta (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				booking_id bigint(20) unsigned NOT NULL,
				meta_key varchar(100) NOT NULL,
				meta_value longtext NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY booking_key (booking_id, meta_key)
			) $char;",

			'booking_notes'   => "CREATE TABLE {$prefix}tg_booking_notes (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				booking_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				note text NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY booking_id (booking_id)
			) $char;",

			'enquiries'       => "CREATE TABLE {$prefix}tg_enquiries (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(190) NOT NULL DEFAULT '',
				email varchar(190) NOT NULL DEFAULT '',
				phone varchar(64) NOT NULL DEFAULT '',
				destination varchar(190) NOT NULL DEFAULT '',
				tour_id bigint(20) unsigned NOT NULL DEFAULT 0,
				travel_date date NULL,
				travelers int(11) unsigned NOT NULL DEFAULT 1,
				budget varchar(120) NOT NULL DEFAULT '',
				message text NULL,
				status varchar(16) NOT NULL DEFAULT 'new',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status (status)
			) $char;",
		);
	}

	/**
	 * Install (or upgrade) all custom tables.
	 *
	 * @return void
	 */
	public static function install(): void {
		if ( ! class_exists( 'wpdb' ) && ! isset( $GLOBALS['wpdb'] ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( self::tables() as $key => $sql ) {
			$table_name = self::table( $key );
			dbDelta( $sql, array( $table_name ) );
		}

		update_option( self::VERSION_OPTION, TG_DB_VERSION );
	}

	/**
	 * Install tables if the stored version differs.
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		if ( get_option( self::VERSION_OPTION, '' ) !== TG_DB_VERSION ) {
			self::install();
		}
	}
}
