<?php
/**
 * Contact form (AJAX, honeypot protected).
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;
?>
<form class="tg-checkout-form" method="post" data-tg-ajax-form data-action="tg_submit_contact" novalidate>
	<h2><?php esc_html_e( 'Send us a message', 'guidegrid-travel' ); ?></h2>
	<div class="tg-form-grid">
		<div class="tg-field tg-form-row">
			<label class="tg-label" for="tg-cf-name"><?php esc_html_e( 'Name', 'guidegrid-travel' ); ?> *</label>
			<input type="text" id="tg-cf-name" name="name" class="tg-input" required autocomplete="name" />
			<span class="tg-field-error" role="alert"></span>
		</div>
		<div class="tg-field tg-form-row">
			<label class="tg-label" for="tg-cf-email"><?php esc_html_e( 'Email', 'guidegrid-travel' ); ?> *</label>
			<input type="email" id="tg-cf-email" name="email" class="tg-input" required autocomplete="email" />
			<span class="tg-field-error" role="alert"></span>
		</div>
		<div class="tg-field tg-form-row">
			<label class="tg-label" for="tg-cf-phone"><?php esc_html_e( 'Phone (optional)', 'guidegrid-travel' ); ?></label>
			<input type="tel" id="tg-cf-phone" name="phone" class="tg-input" autocomplete="tel" />
		</div>
		<div class="tg-field tg-form-row">
			<label class="tg-label" for="tg-cf-subject"><?php esc_html_e( 'Subject', 'guidegrid-travel' ); ?></label>
			<input type="text" id="tg-cf-subject" name="subject" class="tg-input" />
		</div>
	</div>
	<div class="tg-field tg-form-row">
		<label class="tg-label" for="tg-cf-message"><?php esc_html_e( 'Message', 'guidegrid-travel' ); ?> *</label>
		<textarea id="tg-cf-message" name="message" class="tg-textarea" required></textarea>
		<span class="tg-field-error" role="alert"></span>
	</div>
	<p class="screen-reader-text" id="tg-cf-hp">
		<label for="tg-website"><?php esc_html_e( 'Leave this field empty', 'guidegrid-travel' ); ?></label>
		<input type="text" id="tg-website" name="tg_website" tabindex="-1" autocomplete="off" />
	</p>
	<button type="submit" class="tg-btn tg-btn--primary"><?php esc_html_e( 'Send Message', 'guidegrid-travel' ); ?><span class="tg-spinner" aria-hidden="true"></span></button>
</form>
