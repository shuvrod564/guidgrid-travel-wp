# Changelog

All notable changes to the GuideGrid Travel theme.

## 1.0.8 — Settings and payment-receipt reliability

### Added
- Verified PayPal webhook settlement for `CHECKOUT.ORDER.APPROVED` and `PAYMENT.CAPTURE.COMPLETED`, using PayPal's signature-verification API and an app-specific Webhook ID.
- Payment-received notifications for the configured administrator email and visible Stripe/PayPal configuration status on the settings page.
- A cryptographically secure browser-side generator for the optional custom-adapter HMAC secret.

### Changed
- Stripe, PayPal, and custom-HMAC secrets are no longer rendered back into admin HTML; blank fields preserve saved values and explicit checkboxes clear them.
- Customer lifetime spend and revenue reports now count received payments instead of merely created or confirmed bookings; the upgrade rebuilds existing lifetime-spend totals from payment records net of refunds.
- Payment settlement sends one specific customer payment-received message instead of immediately duplicating it with a confirmation message.
- The admin refund action is explicitly labeled as recording a refund completed outside GuideGrid, rather than implying that the theme calls a provider refund API.

### Fixed
- Save Tour Settings during `admin_init` with capability and nonce checks, validated values, post/redirect/get notices, and persistence for the sender email and all payment fields.
- Keep invalid credential replacements from overwriting working values, and automatically leave incomplete Stripe/PayPal configurations disabled.
- Harden Stripe event/session matching, custom HMAC signature parsing and JSON validation, payment-reference/amount checks, and failure handling when a verified payment record cannot be written.
- Prevent duplicate refund recording for non-paid bookings and subtract recorded refunds from customer lifetime spend.
- Keep PayPal's server-side approval-return capture as a fallback while making verified webhooks the reliable asynchronous path.

### Security
- PayPal webhook events are accepted only after provider-side transmission-signature verification with all required headers and the configured Webhook ID.
- The custom endpoint accepts only a valid 64-character HMAC-SHA256 digest (optionally prefixed with `sha256=`) over the untouched raw body and still requires independent adapter transaction verification.
- No browser return, GET request, or unverified provider payload can mark a Stripe or PayPal booking paid.

## 1.0.7 — Booking detail access hotfix

### Fixed
- Keep the hidden booking-detail submenu registered until after WordPress completes `user_can_access_admin_page()`, preventing valid `admin.php?page=tg-booking-detail&booking=…` links from being rejected with “Sorry, you are not allowed to access this page.”

## 1.0.6 — Booking admin and safe payment simulation

### Added
- An explicitly enabled, no-charge payment simulator for local, development, and staging environments, with authenticated nonce-protected approve and decline controls.
- Simulator payment history and clear frontend notices that distinguish test outcomes from real provider payments.

### Fixed
- Process booking row, bulk, detail, note, and refund actions during `admin_init`, before WordPress sends admin output, eliminating late-redirect header warnings.
- Keep booking-detail routes registered and actionable while preserving the hidden submenu behavior.

### Security
- The simulator is unavailable on production environments, never settles from a GET request, verifies booking ownership or booking-management capability, and refuses expired or inactive booking holds.

## 1.0.5 — Workflow page URL hotfix

### Fixed
- Match the full `page-templates/...` values WordPress stores for Booking, Confirmation, Lookup, and My Account templates.
- Prefer existing page permalinks and use query-string fallbacks that work when Apache rewrite rules or pretty permalinks are unavailable.
- Run versioned theme upgrades for already-active theme replacements, creating missing workflow pages and assigning their templates without requiring a theme switch.

## 1.0.4 — WordPress database/admin compatibility hotfix

### Fixed
- Use WordPress's `wpdb::get_charset_collate()` method instead of reading the nonexistent `wpdb::$charset_collate` property during custom-table installation.
- Prevent the CSV export callback from returning “Permission denied” on unrelated WordPress admin requests.
- Explicitly grant the theme management capabilities to WordPress administrators and restore them for existing Tour Manager and Booking Manager roles.

## 1.0.3 — Customer accounts and international payments

### Added
- Branded frontend Log In and Create Account forms with rate limiting, nonce protection, safe checkout redirects and Loginizer social-login buttons.
- Configurable hosted Stripe Checkout and PayPal Orders v2 adapters. Gateways stay hidden until enabled with complete credentials.
- Verified Stripe webhook fulfillment and server-side PayPal capture with booking, currency and amount validation.
- Per-method manual payment enablement and customer instructions in Tour Settings.

### Changed
- Customer authentication is required by default before checkout renders or the booking API accepts a booking.
- Cash-on-arrival and pay-later bookings now reserve seats as confirmed reservations without a short online-payment expiry.
- Confirmation pages and emails show the selected payment method and its instructions rather than listing every manual option.

### Fixed
- Online-payment callbacks are idempotent and no longer treat a browser success redirect as proof of payment.
- Hosted payment failures and cancellations can be retried securely by the authenticated booking owner.

## 1.0.2 — Booking creation hotfix

### Fixed
- Replaced the nonexistent `wp_uniqid()` call with PHP's `uniqid()` plus WordPress randomness when generating booking numbers. This fixes the REST booking endpoint's 500 error.

## 1.0.1 — Booking flow fixes

### Fixed
- Moved live quote and booking creation requests to REST routes so booking works on shared hosts that block public `admin-ajax.php` POST requests.
- Kept tour guest state synchronized with the stepper controls, so adult, child and infant price changes are calculated correctly.
- Added editable date, guest, add-on and coupon controls to the checkout page when it is opened with only a `tour_id`.
- Removed duplicate/wrong `tgTour` data on tour and checkout pages.
- Corrected quote discount, tax, fee and deposit rendering and add-on array serialization.
- Corrected booking rate-limit expiry and the nested availability/booking transaction.
- Fixed the PHP syntax error that prevented the booking confirmation page from rendering.

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
