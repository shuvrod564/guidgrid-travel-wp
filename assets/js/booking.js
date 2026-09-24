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

	function ajaxPost(action, payload) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('tg_nonce', D.nonce || '');
		Object.keys(payload || {}).forEach(function (k) {
			var v = payload[k];
			if (v === null || v === undefined) {
				return;
			}
			if (Array.isArray(v)) {
				v.forEach(function (item) {
					body.append(k + '[]', String(item));
				});
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
			return r.text().then(function (text) {
				try {
					return JSON.parse(text);
				} catch (e) {
					throw new Error(text || 'Invalid server response.');
				}
			});
		});
	}

	function restUrl(path) {
		var base = D.restUrl || '';
		if (!base && D.ajaxUrl) {
			base = D.ajaxUrl.replace(/wp-admin\/admin-ajax\.php.*$/, 'wp-json/tg/v1/');
		}
		if (!base) {
			base = '/wp-json/tg/v1/';
		}
		return base.replace(/\/+$/, '') + '/' + String(path || '').replace(/^\/+/, '');
	}

	function restPost(path, payload) {
		var headers = {
			'Accept': 'application/json',
			'Content-Type': 'application/json; charset=UTF-8'
		};
		if (D.restNonce) {
			headers['X-WP-Nonce'] = D.restNonce;
		}
		return fetch(restUrl(path), {
			method: 'POST',
			credentials: 'same-origin',
			headers: headers,
			body: JSON.stringify(payload || {})
		}).then(function (r) {
			return r.text().then(function (text) {
				var data = {};
				try {
					data = text ? JSON.parse(text) : {};
				} catch (e) {
					data = { message: text || 'Invalid server response.' };
				}
				if (!r.ok) {
					return { success: false, data: data, status: r.status };
				}
				return { success: true, data: data, status: r.status };
			});
		});
	}

	function restBookingPayload(payload) {
		var out = {};
		var customer = {};
		Object.keys(payload || {}).forEach(function (key) {
			var match = key.match(/^customer\[([^\]]+)\]$/);
			if (match) {
				customer[match[1]] = payload[key];
			} else {
				out[key] = payload[key];
			}
		});
		out.customer = customer;
		return out;
	}

	function post(action, payload) {
		// Byethost and some other shared hosts return "Permission denied" for
		// public requests to wp-admin/admin-ajax.php. Booking traffic therefore
		// uses the equivalent public REST routes; other theme actions keep their
		// existing AJAX endpoints.
		if (action === 'tg_quote') {
			return restPost('quote', payload);
		}
		if (action === 'tg_create_booking') {
			return restPost('bookings', restBookingPayload(payload));
		}
		return ajaxPost(action, payload);
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
		var raw = q.raw || q;
		var total = w.querySelector('[data-tg-total]');
		if (q.valid) {
			setLine('subtotal', q.lines.subtotal);
			setLine('discount', Number(raw.discount || 0) > 0 ? '−' + q.lines.discount : '', true);
			setLine('tax', Number(raw.tax || 0) > 0 ? q.lines.tax : '', true);
			setLine('fee', Number(raw.service_fee || 0) > 0 ? q.lines.fee : '', true);
			if (total) {
				total.textContent = q.lines.total;
			}
		} else if (q.errors && q.errors.length && total) {
			total.textContent = q.errors[0];
		}
	}

	var quoteTimer = null;
	var widgetQuoteRequest = 0;
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
		var requestId = ++widgetQuoteRequest;
		return post('tg_quote', payload).then(function (res) {
			if (requestId !== widgetQuoteRequest) {
				return null;
			}
			if (res && res.success) {
				widgetState.quote = res.data;
			} else {
				widgetState.quote = {
					valid: false,
					errors: [(res && res.data && res.data.message) || (I18N.error || 'Price could not be calculated.')]
				};
			}
			renderQuote();
			var msg = w.querySelector('[data-tg-coupon-msg]');
			if (msg && widgetState.coupon) {
				if (widgetState.quote.valid && Number((widgetState.quote.raw || widgetState.quote).discount || 0) > 0) {
					msg.textContent = I18N.couponOk || 'Coupon applied.';
					msg.className = 'tg-coupon-msg ok';
				} else if (widgetState.quote.errors && widgetState.quote.errors.length) {
					msg.textContent = widgetState.quote.errors.join(' ');
					msg.className = 'tg-coupon-msg err';
				}
			}
			return widgetState.quote;
		}).catch(function () {
			if (requestId === widgetQuoteRequest) {
				widgetState.quote = { valid: false, errors: [I18N.error || 'Price could not be calculated.'] };
				renderQuote();
			}
			return null;
		});
	}

	function bookNow() {
		var w = widget();
		if (!w) {
			return;
		}
		syncWidgetState();
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
				var message = (res && res.data && res.data.errors && res.data.errors.length)
					? res.data.errors.join(' ')
					: ((res && res.data && res.data.message) || I18N.error || 'Something went wrong.');
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

	function syncWidgetState() {
		var w = widget();
		if (!w) {
			return;
		}
		var adults = w.querySelector('[data-tg-field="adults"]');
		var children = w.querySelector('[data-tg-field="children"]');
		var infants = w.querySelector('[data-tg-field="infants"]');
		if (adults) {
			widgetState.adults = Math.max(1, parseInt(adults.value, 10) || 1);
		}
		if (children) {
			widgetState.children = Math.max(0, parseInt(children.value, 10) || 0);
		}
		if (infants) {
			widgetState.infants = Math.max(0, parseInt(infants.value, 10) || 0);
		}
	}

	function initWidget() {
		var w = widget();
		if (!w) {
			return;
		}
		syncWidgetState();
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

		// theme.js updates the hidden guest inputs and then calls this module's
		// onChanged callback. No polling is needed, and state is updated before
		// the next quote is requested.
		w.querySelectorAll('[data-tg-field="adults"], [data-tg-field="children"], [data-tg-field="infants"]').forEach(function (input) {
			input.addEventListener('change', function () {
				syncWidgetState();
				scheduleQuote();
			});
		});
	}
	window.tgBooking = {
		onChanged: function () {
			syncWidgetState();
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

		var addonNames = [];
		root.querySelectorAll('[data-tg-co-addon]:checked').forEach(function (input) {
			addonNames.push(input.getAttribute('data-addon-name') || input.value);
		});
		var addonsWrap = root.querySelector('[data-tg-sum-addons-wrap]');
		if (addonsWrap) {
			addonsWrap.hidden = addonNames.length === 0;
		}
		set('[data-tg-sum-addons]', addonNames.join(', ') || '—');

		var q = co.quote;
		var note = root.querySelector('[data-tg-summary-note]');
		var submit = root.querySelector('[data-tg-submit-booking]');
		if (q && q.valid) {
			var raw = q.raw || q;
			set('[data-tg-sum-subtotal]', q.lines.subtotal);
			set('[data-tg-sum-discount]', Number(raw.discount || 0) > 0 ? '−' + q.lines.discount : '', true);
			set('[data-tg-sum-tax]', Number(raw.tax || 0) > 0 ? q.lines.tax : '', true);
			set('[data-tg-sum-fee]', Number(raw.service_fee || 0) > 0 ? q.lines.fee : '', true);
			set('[data-tg-sum-total]', q.lines.total);
			set('[data-tg-sum-deposit]', Number(raw.deposit || 0) > 0 ? q.lines.deposit : '', true);
			if (note) {
				note.textContent = '';
				note.classList.remove('tg-text-danger');
			}
			if (submit && !submit.hasAttribute('aria-busy')) {
				submit.disabled = root.getAttribute('data-has-payment') !== '1';
			}
		} else {
			set('[data-tg-sum-subtotal]', '—');
			set('[data-tg-sum-discount]', '', true);
			set('[data-tg-sum-tax]', '', true);
			set('[data-tg-sum-fee]', '', true);
			set('[data-tg-sum-total]', '—');
			set('[data-tg-sum-deposit]', '', true);
			if (note) {
				if (!co.date) {
					note.textContent = I18N.selectDate || 'Select a travel date to calculate your total.';
				} else if (q && q.errors && q.errors.length) {
					note.textContent = q.errors.join(' ');
				} else {
					note.textContent = 'Checking availability and price…';
				}
				note.classList.toggle('tg-text-danger', !!(q && q.errors && q.errors.length));
			}
			if (submit && !submit.hasAttribute('aria-busy')) {
				submit.disabled = true;
			}
		}
	}

	var coQuoteTimer = null;
	var coQuoteRequest = 0;

	function coQuote() {
		if (!co.tour || !co.date) {
			co.quote = null;
			renderSummary();
			return Promise.resolve(null);
		}
		var requestId = ++coQuoteRequest;
		co.quote = null;
		renderSummary();
		return post('tg_quote', {
			tour_id: co.tour,
			date: co.date,
			adults: co.adults,
			children: co.children,
			infants: co.infants,
			addons: co.addons,
			coupon: co.coupon
		}).then(function (res) {
			if (requestId !== coQuoteRequest) {
				return null;
			}
			if (res && res.success) {
				co.quote = res.data;
			} else {
				co.quote = {
					valid: false,
					errors: [(res && res.data && res.data.message) || 'Price could not be calculated.']
				};
			}
			renderSummary();
			return co.quote;
		}).catch(function () {
			if (requestId === coQuoteRequest) {
				co.quote = { valid: false, errors: [(D.i18n && D.i18n.error) || 'Price could not be calculated.'] };
				renderSummary();
			}
			return null;
		});
	}

	function scheduleCoQuote() {
		if (coQuoteTimer) {
			window.clearTimeout(coQuoteTimer);
		}
		co.quote = null;
		renderSummary();
		coQuoteTimer = window.setTimeout(coQuote, 250);
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
		if (!co.date || !co.quote || !co.quote.valid) {
			var dateInput = form.querySelector('[data-tg-co-date]');
			if (dateInput && !co.date) {
				dateInput.setAttribute('aria-invalid', 'true');
				var dateRow = dateInput.closest('.tg-field');
				var dateError = dateRow ? dateRow.querySelector('.tg-field-error') : null;
				if (dateError) {
					dateError.textContent = 'Please select an available travel date.';
					dateRow.classList.add('has-error');
				}
			}
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
				if (res.data.payment_url) {
					window.location.href = res.data.payment_url;
					return;
				}
				var confirmation = res.data.confirmation_url || (D.confirmationUrl || '/booking-confirmation/?booking=' + encodeURIComponent(res.data.booking_number));
				if (res.data.payment_error) {
					confirmation += (confirmation.indexOf('?') === -1 ? '?' : '&') + 'payment=failed';
				}
				window.location.href = confirmation;
			} else {
				if (res && res.status === 401 && res.data && res.data.login_url) {
					window.location.href = res.data.login_url;
					return;
				}
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

	function readCheckoutSelections() {
		var root = coEls();
		if (!root) {
			return;
		}
		var date = root.querySelector('[data-tg-co-date]');
		var adults = root.querySelector('[data-tg-co-guests="adults"]');
		var children = root.querySelector('[data-tg-co-guests="children"]');
		var infants = root.querySelector('[data-tg-co-guests="infants"]');
		var coupon = root.querySelector('[data-tg-co-coupon]');
		if (date) {
			co.date = date.value || '';
		}
		if (adults) {
			co.adults = Math.max(1, parseInt(adults.value, 10) || 1);
			adults.value = String(co.adults);
		}
		if (children) {
			co.children = Math.max(0, parseInt(children.value, 10) || 0);
			children.value = String(co.children);
		}
		if (infants) {
			co.infants = Math.max(0, parseInt(infants.value, 10) || 0);
			infants.value = String(co.infants);
		}
		if (coupon) {
			co.coupon = coupon.value.trim();
		}
		co.addons = [];
		root.querySelectorAll('[data-tg-co-addon]:checked').forEach(function (input) {
			co.addons.push(input.value);
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

		var form = root.querySelector('[data-tg-checkout-form]');
		if (form) {
			form.addEventListener('submit', submitCheckout);
			form.querySelectorAll('input[required], select[required]').forEach(function (input) {
				input.addEventListener('input', function () {
					input.removeAttribute('aria-invalid');
					var field = input.closest('.tg-field');
					if (field) {
						field.classList.remove('has-error');
					}
				});
			});
		}

		var dateInput = root.querySelector('[data-tg-co-date]');
		if (dateInput) {
			dateInput.addEventListener('change', function () {
				dateInput.removeAttribute('aria-invalid');
				var row = dateInput.closest('.tg-field');
				if (row) {
					row.classList.remove('has-error');
				}
				readCheckoutSelections();
				scheduleCoQuote();
			});
		}
		root.querySelectorAll('[data-tg-co-guests]').forEach(function (input) {
			input.addEventListener('input', function () {
				readCheckoutSelections();
				renderSummary();
				scheduleCoQuote();
			});
		});
		root.querySelectorAll('[data-tg-co-addon]').forEach(function (input) {
			input.addEventListener('change', function () {
				readCheckoutSelections();
				renderSummary();
				scheduleCoQuote();
			});
		});

		var couponInput = root.querySelector('[data-tg-co-coupon]');
		var couponButton = root.querySelector('[data-tg-co-apply-coupon]');
		var applyCoupon = function () {
			readCheckoutSelections();
			coQuote().then(function (quote) {
				var message = root.querySelector('[data-tg-co-coupon-msg]');
				if (!message) {
					return;
				}
				if (!co.coupon) {
					message.textContent = '';
					message.className = 'tg-coupon-msg';
				} else if (quote && quote.valid && Number((quote.raw || quote).discount || 0) > 0) {
					message.textContent = 'Coupon applied.';
					message.className = 'tg-coupon-msg ok';
				} else {
					message.textContent = (quote && quote.errors && quote.errors.join(' ')) || 'Coupon could not be applied.';
					message.className = 'tg-coupon-msg err';
				}
			});
		};
		if (couponButton) {
			couponButton.addEventListener('click', applyCoupon);
		}
		if (couponInput) {
			couponInput.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					applyCoupon();
				}
			});
		}

		readCheckoutSelections();
		renderSummary();
		coQuote();
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
