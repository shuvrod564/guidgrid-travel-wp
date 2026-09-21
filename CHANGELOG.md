# Changelog

All notable changes to the GuideGrid Travel theme.

## 1.0.0 — Initial release

### Added
- Tour, Destination, Travel Guide and Add-on post types with dedicated taxonomies (`tour_category`, `activity`) and term meta.
- Server-side booking engine: pricing (specific date → seasonal → weekly → base), availability (fixed dates / ranges / weekly / any, capacity, blackouts, hold expiry), coupons (percent/fixed, limits, scoping), add-ons with 5 pricing units, price snapshots, controlled status transitions, booking numbers `TG-YYYYMMDD-XXXXX`.
- Payment layer with manual methods (bank transfer, cash, pay later), pluggable gateway adapter interface, example skeleton gateway, HMAC-signed webhook endpoint (`POST /tg/v1/payments/webhook`) that verifies transactions server-side.
- Custom tables (`{prefix}tg_*`): bookings, booking_items, payments, availability, pricing_rules, coupons, coupon_usage, customers, reviews, booking_notes, booking_meta, enquiries — created via `dbDelta`, versioned.
- Frontend: homepage (hero search, destinations, popular tours, offers, categories, testimonials, blog), tour archive with 10 filters + 6 sorts, tour single (gallery, facts, itinerary, included/excluded, FAQ, map, reviews, booking widget, related tours), destination pages, travel guides, checkout, confirmation (printable), booking lookup, My Account (dashboard/bookings/wishlist/profile), contact, offers page.
- AJAX endpoints (nonce + rate limiting): available dates, quote, create booking, lookup, wishlist, review, contact, enquiry, newsletter, profile.
- REST API `tg/v1`: tours, tour detail, availability (public); bookings create; my bookings (auth); payment webhook (signature-verified).
- Cron: hourly hold expiry + daily reminders (7/3/1 days, configurable).
- Emails: booking received, payment received, confirmed, cancelled, refund, reminder, review request, new booking/enquiry/review (admin) — all filterable.
- Admin console: bookings list (filters, bulk + row actions, CSV export), booking detail (notes, actions, payment log), manual booking (same engine as frontend), availability calendar, reports, coupons CRUD, reviews moderation, enquiries, settings, dashboard widget, one-click demo importer (6 destinations, 6 categories, 8 activities, 5 add-ons, 12 tours, 3 guides, 2 posts, coupon WELCOME10, sample reviews, core pages + menu).
- Roles: Tour Manager (content + bookings, no WP settings), Booking Manager (bookings only).
- Customizer: branding colors, header toggles, contact & social links.
- Structured data: TouristTrip + Offer + AggregateRating + BreadcrumbList + FAQPage.
- Design system per spec: CSS custom properties, Inter→system font stack, 44px touch targets, visible focus, status text+color (never color-only), reduced-motion support, RTL-safe layout hooks.
