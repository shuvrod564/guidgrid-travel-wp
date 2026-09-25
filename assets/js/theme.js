/**
 * GuideGrid Travel — site-wide behaviors.
 * Vanilla JS, no dependencies. All i18n strings come from tgData.
 */
(function () {
	'use strict';

	var d = window.tgData || {};
	var I18N = d.i18n || {};

	/* ================= Toasts ================= */
	function toast(message, type) {
		var region = document.querySelector('[data-tg-toast-region]');
		if (!region) {
			return;
		}
		var el = document.createElement('div');
		el.className = 'tg-toast' + (type ? ' tg-toast--' + type : '');
		el.setAttribute('role', 'status');
		el.textContent = message;
		region.appendChild(el);
		window.setTimeout(function () {
			el.classList.add('is-leaving');
			window.setTimeout(function () {
				if (el.parentNode) {
					el.parentNode.removeChild(el);
				}
			}, 350);
		}, 4200);
	}
	window.tgToast = toast;

	/* ================= Mobile nav ================= */
	function initNav() {
		var toggle = document.querySelector('[data-tg-toggle-nav]');
		var panel = document.getElementById('tg-mobile-nav');
		var backdrop = document.querySelector('.tg-nav-backdrop');
		if (!toggle || !panel) {
			return;
		}
		function setOpen(open) {
			panel.classList.toggle('is-open', open);
			if (backdrop) {
				backdrop.classList.toggle('is-open', open);
			}
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			document.body.style.overflow = open ? 'hidden' : '';
		}
		toggle.addEventListener('click', function () {
			setOpen(!panel.classList.contains('is-open'));
		});
		document.querySelectorAll('[data-tg-close-nav]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				setOpen(false);
			});
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && panel.classList.contains('is-open')) {
				setOpen(false);
				toggle.focus();
			}
		});
	}

	/* ================= Header search ================= */
	function initSearch() {
		var toggle = document.querySelector('[data-tg-toggle-search]');
		var panel = document.getElementById('tg-search-panel');
		if (!toggle || !panel) {
			return;
		}
		toggle.addEventListener('click', function () {
			var open = panel.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) {
				var input = panel.querySelector('input[type="search"]');
				if (input) {
					input.focus();
				}
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && panel.classList.contains('is-open')) {
				panel.classList.remove('is-open');
				toggle.setAttribute('aria-expanded', 'false');
			}
		});
	}

	/* ================= Filter drawer (tour archive) ================= */
	function initFilters() {
		var opener = document.querySelector('[data-tg-open-filters]');
		var panel = document.getElementById('tg-filters');
		var closer = document.querySelector('[data-tg-close-filters]');
		if (!opener || !panel) {
			return;
		}
		opener.addEventListener('click', function () {
			panel.classList.add('is-open');
			document.body.style.overflow = 'hidden';
		});
		if (closer) {
			closer.addEventListener('click', function () {
				panel.classList.remove('is-open');
				document.body.style.overflow = '';
			});
		}
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && panel.classList.contains('is-open')) {
				panel.classList.remove('is-open');
				document.body.style.overflow = '';
			}
		});
	}

	/* ================= Guest steppers ================= */
	function initSteppers() {
		document.querySelectorAll('[data-tg-stepper]').forEach(function (stepper) {
			var value = stepper.querySelector('.tg-stepper-value');
			if (!value) {
				return;
			}
			stepper.querySelectorAll('button[data-step]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var current = parseInt(value.textContent, 10) || 0;
					var step = parseInt(btn.getAttribute('data-step'), 10) || 0;
					var field = stepper.getAttribute('data-field') || '';
					var next = current + step;
					var min = field === 'adults' ? 1 : 0;
					if (next < min) {
						next = min;
					}
					value.textContent = String(next);
					// Sync hidden inputs in the booking widget.
					var widget = stepper.closest('[data-tg-widget]');
					if (widget) {
						var hidden = widget.querySelector('[data-tg-field="' + field + '"]');
						if (hidden) {
							hidden.value = String(next);
						}
						window.tgBooking && window.tgBooking.onChanged && window.tgBooking.onChanged();
					}
				});
			});
		});
	}

	/* ================= Gallery + lightbox ================= */
	function initGallery() {
		var lightbox = document.querySelector('[data-tg-lightbox]');
		var lightboxImg = lightbox ? lightbox.querySelector('img') : null;
		var lightboxClose = lightbox ? lightbox.querySelector('[data-tg-lightbox-close]') : null;

		function openLightbox(src, alt) {
			if (!lightbox || !lightboxImg) {
				return;
			}
			lightboxImg.src = src;
			lightboxImg.alt = alt || '';
			lightbox.classList.add('is-open');
			document.body.style.overflow = 'hidden';
			if (lightboxClose) {
				lightboxClose.focus();
			}
		}
		function closeLightbox() {
			if (!lightbox) {
				return;
			}
			lightbox.classList.remove('is-open');
			document.body.style.overflow = '';
		}

		if (lightboxClose) {
			lightboxClose.addEventListener('click', closeLightbox);
		}
		if (lightbox) {
			lightbox.addEventListener('click', function (e) {
				if (e.target === lightbox) {
					closeLightbox();
				}
			});
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && lightbox.classList.contains('is-open')) {
					closeLightbox();
				}
			});
		}

		document.querySelectorAll('[data-tg-gallery]').forEach(function (gallery) {
			var main = gallery.querySelector('.tg-gallery-main');
			var thumbs = gallery.querySelectorAll('[data-tg-thumb]');
			if (main) {
				main.addEventListener('click', function () {
					var full = main.getAttribute('data-tg-full');
					var img = main.querySelector('img');
					if (full && img) {
						openLightbox(full, img.alt);
					}
				});
			}
			thumbs.forEach(function (thumb) {
				thumb.addEventListener('click', function () {
					console.log('clicked');
					thumbs.forEach(function (t) {
						t.classList.remove('is-active');
					});
					thumb.classList.add('is-active');
					var full = thumb.getAttribute('data-tg-full');
					if (main && full) {
						var img = main.querySelector('img');
						if (img) {
							img.src = full;
						}
						main.setAttribute('data-tg-full', full);
					}
				});
			});
		});
	}

	/* ================= Wishlist ================= */
	function guestWishlist() {
		try {
			return JSON.parse(window.localStorage.getItem('tg_wishlist') || '[]') || [];
		} catch (e) {
			return [];
		}
	}
	function guestSaveWishlist(ids) {
		try {
			window.localStorage.setItem('tg_wishlist', JSON.stringify(ids));
		} catch (e) {
			/* storage unavailable */
		}
	}
	function allWishlistIds() {
		var server = (d.wishlist || []).map(String);
		var guest = guestWishlist().map(String);
		return server.concat(guest.filter(function (id) {
			return server.indexOf(id) === -1;
		}));
	}
	function updateWishlistCount() {
		var count = allWishlistIds().length;
		document.querySelectorAll('[data-tg-wishlist-count]').forEach(function (el) {
			el.textContent = String(count);
			if (count === 0 && el.parentNode) {
				el.parentNode.removeChild(el);
			}
		});
	}
	function syncWishlistButton(btn, inList) {
		btn.classList.toggle('is-active', inList);
		btn.setAttribute('aria-pressed', inList ? 'true' : 'false');
	}
	function initWishlist() {
		var ids = allWishlistIds();
		document.querySelectorAll('[data-tour]').forEach(function (btn) {
			var id = btn.getAttribute('data-tour');
			if (ids.indexOf(id) !== -1) {
				syncWishlistButton(btn, true);
			}
		});
		document.querySelectorAll('[data-tg-wishlist-single]').forEach(function (btn) {
			var id = btn.getAttribute('data-tour');
			if (ids.indexOf(id) !== -1) {
				syncWishlistButton(btn, true);
				btn.lastChild && (btn.lastChild.textContent = I18N.saved || 'Saved');
			}
		});

		document.addEventListener('click', function (e) {
			var btn = e.target.closest ? e.target.closest('[data-tour]') : null;
			if (!btn || !btn.classList.contains('tg-wishlist-btn') && !btn.hasAttribute('data-tg-wishlist-single')) {
				return;
			}
			if (!btn.hasAttribute('data-tour')) {
				return;
			}
			e.preventDefault();
			var id = btn.getAttribute('data-tour');

			if (d.loggedIn) {
				fetch(d.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: 'action=tg_toggle_wishlist&tour_id=' + encodeURIComponent(id) + '&tg_nonce=' + encodeURIComponent(d.nonce || '')
				})
					.then(function (r) {
						return r.json();
					})
					.then(function (res) {
						if (res && res.success) {
							syncWishlistButton(btn, res.data.added);
							toast(res.data.message || (res.data.added ? I18N.added : I18N.removed), res.data.added ? 'success' : '');
							updateWishlistCount();
						} else {
							toast((res && res.data && res.data.message) || I18N.error, 'error');
						}
					})
					.catch(function () {
						toast(I18N.error, 'error');
					});
			} else {
				var list = guestWishlist();
				var idx = list.indexOf(id);
				if (idx === -1) {
					list.push(id);
					syncWishlistButton(btn, true);
					toast(I18N.added || 'Added to wishlist', 'success');
				} else {
					list.splice(idx, 1);
					syncWishlistButton(btn, false);
					toast(I18N.removed || 'Removed from wishlist');
				}
				guestSaveWishlist(list);
				updateWishlistCount();
			}
		});
	}

	/* ================= Generic AJAX forms ================= */
	function initAjaxForms() {
		document.querySelectorAll('[data-tg-ajax-form]').forEach(function (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var action = form.getAttribute('data-action');
				var btn = form.querySelector('button[type="submit"]');
				// var data = new URLSearchParams(new FormData(form));
				// data.append('action', action);
				// data.append('tg_nonce', d.nonce || '');
				var formData = new FormData(form);
				var data = new URLSearchParams();

				formData.forEach(function (value, key) {
					data.append(key, value);
				});

				data.set('action', action || '');
				data.set('tg_nonce', d.nonce || '');

				if (btn) {
					btn.setAttribute('aria-busy', 'true');
					btn.disabled = true;
				}

				fetch(d.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: data.toString()
				})
					.then(function (r) {
						return r.json();
					})
					.then(function (res) {
						var message = (res && res.data && res.data.message) || (res && res.data ? res.data.message : '');
						if (res && res.success) {
							toast(message || I18N.saved, 'success');
							form.reset();
							form.querySelectorAll('.tg-field-error').forEach(function (el) {
								el.textContent = '';
							});
							form.querySelectorAll('.has-error').forEach(function (el) {
								el.classList.remove('has-error');
							});
						} else {
							toast(message || I18N.error, 'error');
						}
					})
					.catch(function () {
						toast(I18N.error, 'error');
					})
					.finally(function () {
						if (btn) {
							btn.removeAttribute('aria-busy');
							btn.disabled = false;
						}
					});
			});
		});
	}

	/* ================= Mobile CTA body class ================= */
	function initMobileCta() {
		var cta = document.querySelector('.tg-mobile-cta');
		if (cta) {
			document.body.classList.add('tg-has-mobile-cta');
			var target = document.getElementById('tg-booking-widget');
			cta.querySelectorAll('a[href="#tg-booking-widget"]').forEach(function (a) {
				a.addEventListener('click', function (e) {
					e.preventDefault();
					if (target) {
						target.scrollIntoView({ behavior: 'smooth', block: 'start' });
					}
				});
			});
		}
	}

	/* ================= Init ================= */
	function init() {
		initNav();
		initSearch();
		initFilters();
		initSteppers();
		initGallery();
		initWishlist();
		initAjaxForms();
		initMobileCta();
		updateWishlistCount();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
