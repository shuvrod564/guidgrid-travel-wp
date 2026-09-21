/**
 * GuideGrid Travel — booking widget, checkout and lookup.
 * Prices are ALWAYS rendered from server responses (tg_quote);
 * nothing submitted by the client is trusted for money.
 */
(function () {
	'use strict';

	var D = window.tgData || {};
	var T = window.tgTour || {};
	var I18N = T.i18n || {};

	function post(action, payload) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('tg_nonce', D.nonce || '');
		Object.keys(payload || {}).forEach(function (k) {
			var v = payload[k];
			if (v === null || v === undefined) {
				return;
			}
			if (Array.isArray(v)) {
				body.append(k + '[]', v.join(','));
			} else {
				body.append(k, String(v));
			}
		});
		return fetch(D.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (r) {
			return r.json();
		});
	}

	function formatLocalDate(iso) {
		if (!iso) {
			return '—';
		}
		var parts = iso.split('-');
		if (parts.length !== 3) {
			return iso;
		}
		var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		var m = months[parseInt(parts[1], 10) - 1] || parts[1];
		return parseInt(parts[2], 10) + ' ' + m + ' ' + parts[0];
	}

	/* ===================== Booking widget (tour page) ===================== */
	var widgetState = {
		date: '',
		adults: 1,
		children: 0,
		infants: 0,
		addons: [],
		coupon: '',
		quote: null
	};

	function widget() {
		return document.querySelector('[data-tg-widget]');
	}

	function selectedAddons() {
		var w = widget();
		var out = [];
		if (!w) {
			return out;
		}
		w.querySelectorAll('[data-tg-addon]:checked').forEach(function (cb) {
			out.push(cb.getAttribute('value'));
		});
		return out;
	}

	function populateDates() {
		var w = widget();
		if (!w) {
			return;
		}
		var select = w.querySelector('[data-tg-field="date"]');
		var empty = w.querySelector('[data-tg-dates-empty]');
		var dates = (T.availableDates || []).filter(function (d) {
			return d && d.date;
		});
		if (empty) {
			empty.hidden = dates.length > 0;
			if (!dates.length) {
				empty.textContent = I18N.noDates || '';
			}
		}
		if (!select) {
			return;
		}
		dates.forEach(function (d, i) {
			var opt = document.createElement('option');
			opt.value = d.date;
			var label = formatLocalDate(d.date);
			if (typeof d.remaining === 'number' && d.remaining >= 0 && d.remaining <= 5) {
				label += ' · ' + d.remaining + ' ' + (I18N.left || 'left');
			}
			opt.textContent = label;
			if (i === 0) {
				opt.selected = true;
			}
			select.appendChild(opt);
		});
		if (dates.length) {
			widgetState.date = dates[0].date;
			scheduleQuote();
		}
	}

	function renderQuote() {
		var w = widget();
		if (!w) {
			return;
		}
		var q = widgetState.quote;
		if (!q) {
			return;
		}
		function setLine(key, value, optional) {
			var row = w.querySelector('[data-tg-line="' + key + '"]');
			if (!row) {
				return;
			}
			var cell = row.querySelectorAll('span');
			if (cell.length >= 2) {
				cell[cell.length - 1].textContent = value;
			}
			if (optional) {
				row.hidden = value === '' || value === null;
			}
		}
		if (q.valid) {
			setLine('subtotal', q.lines.subtotal);
			setLine('discount', q.discount > 0 ? '−' + q.lines.discount : '', true);
			setLine('tax', q.lines.tax, true);
			setLine('fee', q.lines.fee, true);
			var total = w.querySelector('[data-tg-total]');
			if (total) {
				total.textContent = q.lines.total;
			}
		} else if (q.errors && q.errors.length) {
			var total = w.querySelector('[data-tg-total]');
			if (total) {
				total.textContent = q.errors[0];
			}
		}
	}

	var quoteTimer = null;
	function scheduleQuote() {
		if (quoteTimer) {
			window.clearTimeout(quoteTimer);
		}
		quoteTimer = window.setTimeout(sendQuote, 350);
	}

	function sendQuote() {
		var w = widget();
		if (!w) {
			return;
		}
		var payload = {
			tour_id: T.tourId || w.getAttribute('data-tour'),
			date: widgetState.date,
			adults: widgetState.adults,
			children: widgetState.children,
			infants: widgetState.infants,
			addons: selectedAddons(),
			coupon: widgetState.coupon
		};
		return post('tg_quote', payload).then(function (res) {
			if (res && res.success) {
				widgetState.quote = res.data;
				renderQuote();
				var msg = w.querySelector('[data-tg-coupon-msg]');
				if (msg && widgetState.coupon) {
					if (res.data.valid) {
						msg.textContent = I18N.couponOk || 'Coupon applied.';
						msg.className = 'tg-coupon-msg ok';
					} else if (res.data.errors && res.data.errors.length) {
						msg.textContent = res.data.errors.join(' ');
						msg.className = 'tg-coupon-msg err';
					}
				}
			}
		});
	}

	function bookNow() {
		var w = widget();
		if (!w) {
			return;
		}
		var btn = w.querySelector('[data-tg-book-now]');
		var date = widgetState.date;
		if (!date) {
			window.tgToast && window.tgToast(I18N.selectDate || 'Select a date first.', 'error');
			return;
		}
		if (btn) {
			btn.setAttribute('aria-busy', 'true');
		}
		// Re-validate server-side before leaving the page.
		post('tg_quote', {
			tour_id: T.tourId || w.getAttribute('data-tour'),
			date: date,
			adults: widgetState.adults,
			children: widgetState.children,
			infants: widgetState.infants,
			addons: selectedAddons(),
			coupon: widgetState.coupon
		}).then(function (res) {
			if (btn) {
				btn.removeAttribute('aria-busy');
			}
			if (!res || !res.success || !res.data.valid) {
				var message = (res && res.data && res.data.errors && res.data.errors.length) ? res.data.errors.join(' ') : (I18N.error || 'Something went wrong.');
				window.tgToast && window.tgToast(message, 'error');
				renderQuote();
				return;
			}
			var base = T.bookingUrl || window.location.pathname;
			var sep = base.indexOf('?') === -1 ? '?' : '&';
			var url = base + sep +
				'date=' + encodeURIComponent(date) +
				'&adults=' + encodeURIComponent(widgetState.adults) +
				'&children=' + encodeURIComponent(widgetState.children) +
				'&infants=' + encodeURIComponent(widgetState.infants);
			var addons = selectedAddons();
			if (addons.length) {
				url += '&addons=' + encodeURIComponent(addons.join(','));
			}
			if (widgetState.coupon) {
				url += '&coupon=' + encodeURIComponent(widgetState.coupon);
			}
			window.location.href = url;
		}).catch(function () {
			if (btn) {
				btn.removeAttribute('aria-busy');
			}
			window.tgToast && window.tgToast(I18N.error || 'Something went wrong.', 'error');
		});
	}

	function initWidget() {
		var w = widget();
		if (!w) {
			return;
		}
		populateDates();

		var select = w.querySelector('[data-tg-field="date"]');
		if (select) {
			select.addEventListener('change', function () {
				widgetState.date = select.value;
				scheduleQuote();
			});
		}

		w.querySelectorAll('[data-tg-addon]').forEach(function (cb) {
			cb.addEventListener('change', scheduleQuote);
		});

		var couponBtn = w.querySelector('[data-tg-apply-coupon]');
		if (couponBtn) {
			couponBtn.addEventListener('click', function () {
				var input = w.querySelector('[data-tg-field="coupon"]');
				if (input) {
					widgetState.coupon = input.value.trim();
				}
				sendQuote();
			});
		}
		var couponInput = w.querySelector('[data-tg-field="coupon"]');
		if (couponInput) {
			couponInput.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					widgetState.coupon = couponInput.value.trim();
					sendQuote();
				}
			});
		}

		var bookBtn = w.querySelector('[data-tg-book-now]');
		if (bookBtn) {
			bookBtn.addEventListener('click', bookNow);
		}

		// Keep state in sync with steppers (theme.js already updates hidden inputs).
		var syncState = function () {
			var adults = w.querySelector('[data-tg-field="adults"]');
			var children = w.querySelector('[data-tg-field="children"]');
			var infants = w.querySelector('[data-tg-field="infants"]');
			if (adults) {
				widgetState.adults = parseInt(adults.value, 10) || 1;
			}
			if (children) {
				widgetState.children = parseInt(children.value, 10) || 0;
			}
			if (infants) {
				widgetState.infants = parseInt(infants.value, 10) || 0;
			}
		};
		var interval = window.setInterval(function () {
			var currentAdults = parseInt((w.querySelector('[data-tg-field="adults"]') || {}).value || '1', 10);
			if (currentAdults !== widgetState.adults ||
				(parseInt((w.querySelector('[data-tg-field="children"]') || {}).value || '0', 10) !== widgetState.children) ||
				(parseInt((w.querySelector('[data-tg-field="infants"]') || {}).value || '0', 10) !== widgetState.infants)) {
				syncState();
				scheduleQuote();
			}
		}, 400);
		w.addEventListener('click', function () {
			window.clearInterval(interval);
		}, { once: true });
	}
	window.tgBooking = {
		onChanged: function () {
			scheduleQuote();
		}
	};

	/* ===================== Checkout page ===================== */
	var co = {
		tour: null,
		date: '',
		adults: 1,
		children: 0,
		infants: 0,
		addons: [],
		coupon: '',
		quote: null
	};

	function coEls() {
		var root = document.getElementById('tg-checkout');
		return root || null;
	}

	function renderSummary() {
		var root = coEls();
		if (!root) {
			return;
		}
		function set(sel, value, hideIfEmpty) {
			var el = root.querySelector(sel);
			if (!el) {
				return;
			}
			el.textContent = value;
			if (hideIfEmpty) {
				var row = el.closest('.tg-summary-row') || el.closest('[data-tg-sum-discount-row]');
				if (row) {
					row.hidden = value === '' || value === null;
				}
			}
		}
		set('[data-tg-sum-date]', co.date ? formatLocalDate(co.date) : '—');
		set('[data-tg-sum-adults]', String(co.adults));
		set('[data-tg-sum-children]', String(co.children));
		set('[data-tg-sum-infants]', String(co.infants));
		var addonsWrap = root.querySelector('[data-tg-sum-addons-wrap]');
		if (addonsWrap) {
			addonsWrap.hidden = co.addons.length === 0;
		}
		var q = co.quote;
		if (q && q.valid) {
			set('[data-tg-sum-subtotal]', q.lines.subtotal);
			set('[data-tg-sum-discount]', q.discount > 0 ? '−' + q.lines.discount : '', true);
			set('[data-tg-sum-tax]', q.lines.tax, true);
			set('[data-tg-sum-fee]', q.lines.fee, true);
			set('[data-tg-sum-total]', q.lines.total);
			set('[data-tg-sum-deposit]', q.deposit > 0 ? q.lines.deposit : '', true);
			var note = root.querySelector('[data-tg-summary-note]');
			if (note) {
				note.textContent = '';
			}
		}
	}

	function coQuote() {
		if (!co.tour) {
			return Promise.resolve();
		}
		return post('tg_quote', {
			tour_id: co.tour,
			date: co.date,
			adults: co.adults,
			children: co.children,
			infants: co.infants,
			addons: co.addons,
			coupon: co.coupon
		}).then(function (res) {
			if (res && res.success) {
				co.quote = res.data;
				renderSummary();
				return res.data;
			}
			return null;
		});
	}

	function submitCheckout(e) {
		e.preventDefault();
		var root = coEls();
		if (!root) {
			return;
		}
		var form = root.querySelector('[data-tg-checkout-form]');
		var btn = root.querySelector('[data-tg-submit-booking]');
		if (!form || !btn) {
			return;
		}

		// Basic client validation (server re-validates everything).
		var first = form.querySelector('[name="customer[first_name]"]');
		var email = form.querySelector('[name="customer[email]"]');
		var terms = form.querySelector('[id="tg-co-terms"]');
		var valid = true;
		[first, email].forEach(function (input) {
			if (input && !input.value.trim()) {
				input.setAttribute('aria-invalid', 'true');
				var row = input.closest('.tg-field');
				var err = row ? row.querySelector('.tg-field-error') : null;
				if (err) {
					err.textContent = D.i18n && D.i18n.required || 'Required';
					row.classList.add('has-error');
				}
				valid = false;
			}
		});
		if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
			email.setAttribute('aria-invalid', 'true');
			valid = false;
		}
		if (terms && !terms.checked) {
			valid = false;
		}
		if (!co.date) {
			valid = false;
		}
		if (!valid) {
			window.tgToast && window.tgToast((D.i18n && D.i18n.checkFields) || 'Please complete the highlighted fields.', 'error');
			return;
		}

		btn.setAttribute('aria-busy', 'true');
		btn.disabled = true;

		var fd = new FormData(form);
		var customer = {
			first_name: fd.get('customer[first_name]') || '',
			last_name: fd.get('customer[last_name]') || '',
			email: fd.get('customer[email]') || '',
			phone: fd.get('customer[phone]') || '',
			country: fd.get('customer[country]') || '',
			address: fd.get('customer[address]') || '',
			special_request: fd.get('customer[special_request]') || '',
			emergency_contact: fd.get('customer[emergency_contact]') || ''
		};
		var methodEl = form.querySelector('[name="payment_method"]:checked');

		post('tg_create_booking', {
			tour_id: co.tour,
			date: co.date,
			adults: co.adults,
			children: co.children,
			infants: co.infants,
			addons: co.addons,
			coupon: co.coupon,
			payment_method: methodEl ? methodEl.value : 'bank',
			'customer[first_name]': customer.first_name,
			'customer[last_name]': customer.last_name,
			'customer[email]': customer.email,
			'customer[phone]': customer.phone,
			'customer[country]': customer.country,
			'customer[address]': customer.address,
			'customer[special_request]': customer.special_request,
			'customer[emergency_contact]': customer.emergency_contact
		}).then(function (res) {
			btn.removeAttribute('aria-busy');
			btn.disabled = false;
			if (res && res.success) {
				window.location.href = res.data.confirmation_url || (D.confirmationUrl || '/booking-confirmation/?booking=' + encodeURIComponent(res.data.booking_number));
			} else {
				var message = (res && res.data && res.data.message) || (D.i18n && D.i18n.error) || 'Booking could not be created.';
				window.tgToast && window.tgToast(message, 'error');
				var notice = document.createElement('div');
				notice.className = 'tg-notice tg-notice--error';
				notice.setAttribute('role', 'alert');
				notice.textContent = message;
				form.parentNode.insertBefore(notice, form);
			}
		}).catch(function () {
			btn.removeAttribute('aria-busy');
			btn.disabled = false;
			window.tgToast && window.tgToast((D.i18n && D.i18n.error) || 'Something went wrong.', 'error');
		});
	}

	function initCheckout() {
		var root = coEls();
		if (!root) {
			return;
		}
		co.tour = root.getAttribute('data-tour') || '';
		co.date = root.getAttribute('data-date') || '';
		co.adults = parseInt(root.getAttribute('data-adults'), 10) || 1;
		co.children = parseInt(root.getAttribute('data-children'), 10) || 0;
		co.infants = parseInt(root.getAttribute('data-infants'), 10) || 0;
		co.coupon = root.getAttribute('data-coupon') || '';
		var rawAddons = root.getAttribute('data-addons') || '';
		if (rawAddons) {
			co.addons = rawAddons.split(',').filter(Boolean);
		}
		renderSummary();
		coQuote();

		var form = root.querySelector('[data-tg-checkout-form]');
		if (form) {
			form.addEventListener('submit', submitCheckout);
		}
	}

	/* ===================== Booking lookup ===================== */
	function initLookup() {
		var form = document.querySelector('[data-tg-lookup-form]');
		var result = document.querySelector('[data-tg-lookup-result]');
		if (!form || !result) {
			return;
		}
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var btn = form.querySelector('button[type="submit"]');
			if (btn) {
				btn.setAttribute('aria-busy', 'true');
				btn.disabled = true;
			}
			result.innerHTML = '<p class="tg-loading-text">' + (D.i18n && D.i18n.searching || 'Searching…') + '</p>';
			var fd = new FormData(form);
			post('tg_lookup_booking', {
				number: fd.get('number'),
				email: fd.get('email')
			}).then(function (res) {
				if (btn) {
					btn.removeAttribute('aria-busy');
					btn.disabled = false;
				}
				if (res && res.success) {
					var b = res.data;
					var html = '<div class="tg-confirmation-box" style="padding:22px;">' +
						'<h3 style="margin-top:0;">' + escapeHtml(b.booking_number) + '</h3>' +
						'<p>' +
						'<strong>' + escapeHtml(b.tour || '') + '</strong><br>' +
						escapeHtml(b.date) + ' · ' + escapeHtml(String(b.guests)) + ' guests<br>' +
						escapeHtml(b.total) + '<br>' +
						'<span style="display:inline-block;margin-top:8px;">' + escapeHtml(b.booking_status) + ' · ' + escapeHtml(b.payment_status) + '</span>' +
						'</p>';
					if (b.tour_url) {
						html += '<p><a class="tg-btn tg-btn--secondary tg-btn--sm" href="' + escapeHtml(b.tour_url) + '">' + escapeHtml(D.i18n && D.i18n.viewTour || 'View tour') + '</a></p>';
					}
					html += '</div>';
					result.innerHTML = html;
				} else {
					result.innerHTML = '<div class="tg-notice tg-notice--error">' + escapeHtml((res && res.data && res.data.message) || (D.i18n && D.i18n.notFound) || 'Booking not found.') + '</div>';
				}
			}).catch(function () {
				if (btn) {
					btn.removeAttribute('aria-busy');
					btn.disabled = false;
				}
				result.innerHTML = '<div class="tg-notice tg-notice--error">' + escapeHtml((D.i18n && D.i18n.error) || 'Something went wrong.') + '</div>';
			});
		});
	}

	function escapeHtml(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	/* ===================== Review form (tour page) ===================== */
	function initReviewForm() {
		document.querySelectorAll('[data-tg-review-form]').forEach(function (wrap) {
			var form = wrap.querySelector('form');
			if (!form) {
				return;
			}
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var btn = form.querySelector('button[type="submit"]');
				var fd = new FormData(form);
				if (btn) {
					btn.setAttribute('aria-busy', 'true');
					btn.disabled = true;
				}
				post('tg_submit_review', {
					tour_id: wrap.getAttribute('data-tour'),
					rating: fd.get('rating') || 5,
					title: fd.get('title') || '',
					content: fd.get('content') || ''
				}).then(function (res) {
					if (btn) {
						btn.removeAttribute('aria-busy');
						btn.disabled = false;
					}
					if (res && res.success) {
						window.tgToast && window.tgToast(res.data.message, 'success');
						form.reset();
					} else {
						window.tgToast && window.tgToast((res && res.data && res.data.message) || (D.i18n && D.i18n.error) || 'Could not submit review.', 'error');
					}
				}).catch(function () {
					if (btn) {
						btn.removeAttribute('aria-busy');
						btn.disabled = false;
					}
					window.tgToast && window.tgToast((D.i18n && D.i18n.error) || 'Something went wrong.', 'error');
				});
			});
		});
	}

	/* ===================== Init ===================== */
	function init() {
		initWidget();
		initCheckout();
		initLookup();
		initReviewForm();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
