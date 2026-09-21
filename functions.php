<?php
/**
 * GuideGrid Travel theme bootstrap.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

define( 'TG_VERSION', '1.0.0' );
define( 'TG_DB_VERSION', '1.0.0' );
define( 'TG_DIR', get_template_directory() );
define( 'TG_URI', get_template_directory_uri() );

/**
 * Load theme modules.
 */
require TG_DIR . '/inc/booking/database.php';
require TG_DIR . '/inc/booking/availability.php';
require TG_DIR . '/inc/booking/pricing.php';
require TG_DIR . '/inc/booking/addons.php';
require TG_DIR . '/inc/booking/coupons.php';
require TG_DIR . '/inc/booking/wishlist.php';
require TG_DIR . '/inc/booking/reviews.php';
require TG_DIR . '/inc/booking/payments.php';
require TG_DIR . '/inc/booking/bookings.php';
require TG_DIR . '/inc/booking/emails.php';

require TG_DIR . '/inc/setup.php';
require TG_DIR . '/inc/roles.php';
require TG_DIR . '/inc/helpers.php';
require TG_DIR . '/inc/tour-query.php';
require TG_DIR . '/inc/template-tags.php';
require TG_DIR . '/inc/enqueue.php';
require TG_DIR . '/inc/post-types.php';
require TG_DIR . '/inc/meta-boxes.php';
require TG_DIR . '/inc/customizer.php';
require TG_DIR . '/inc/structured-data.php';
require TG_DIR . '/inc/shortcodes.php';
require TG_DIR . '/inc/ajax.php';
require TG_DIR . '/inc/rest.php';
require TG_DIR . '/inc/cron.php';
require TG_DIR . '/inc/activation.php';

if ( is_admin() ) {
	require TG_DIR . '/inc/admin/admin-menu.php';
	require TG_DIR . '/inc/admin/settings.php';
	require TG_DIR . '/inc/admin/bookings-admin.php';
	require TG_DIR . '/inc/admin/calendar.php';
	require TG_DIR . '/inc/admin/reports.php';
	require TG_DIR . '/inc/admin/coupons-admin.php';
	require TG_DIR . '/inc/admin/reviews-admin.php';
	require TG_DIR . '/inc/admin/enquiries-admin.php';
	require TG_DIR . '/inc/admin/dashboard-widget.php';
	require TG_DIR . '/inc/admin/demo-importer.php';
}
