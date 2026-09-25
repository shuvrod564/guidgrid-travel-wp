/**
 * GuideGrid Travel — admin behaviors (repeaters, bulk select).
 */
(function () {
	'use strict';

	function initRepeaters() {
		document.querySelectorAll('[data-tg-repeater]').forEach(function (rep) {
			var name = rep.getAttribute('data-tg-repeater');
			var addBtn = rep.querySelector('.tg-rep-add');
			var defaults = {};
			try {
				defaults = JSON.parse(addBtn ? (addBtn.getAttribute('data-defaults') || '{}') : '{}');
			} catch (e) {
				defaults = {};
			}

			function nextIndex() {
				var max = 0;
				rep.querySelectorAll('.tg-rep-row').forEach(function (row) {
					var i = parseInt(row.getAttribute('data-index'), 10) || 0;
					if (i > max) {
						max = i;
					}
				});
				return max + 1;
			}

			if (addBtn) {
				addBtn.addEventListener('click', function () {
					var idx = nextIndex();
					var row = document.createElement('div');
					row.className = 'tg-rep-row';
					row.setAttribute('data-index', String(idx));
					row.innerHTML =
						'<div class="tg-rep-row-head"><strong>Row ' + idx + '</strong>' +
						'<button type="button" class="button button-small tg-rep-remove" aria-label="Remove row">×</button></div>' +
						'<div class="tg-rep-fields"></div>';
					var fields = row.querySelector('.tg-rep-fields');
					Object.keys(defaults).forEach(function (key) {
						var p = document.createElement('p');
						p.innerHTML = '<label class="tg-label">' + key + '</label><input type="text" class="widefat tg-rep-value" name="' + name + '[' + idx + '][' + key + ']" value="' + String(defaults[key] || '').replace(/"/g, '&quot;') + '" />';
						fields.appendChild(p);
					});
					// addBtn.parentNode.insertBefore(row, addBtn.parentNode);
					var addButtonWrapper = addBtn.closest('p');

					if (addButtonWrapper && addButtonWrapper.parentNode === rep) {
						rep.insertBefore(row, addButtonWrapper);
					} else {
						rep.appendChild(row);
					}
					bindRemove(row);
				});
			}

			function bindRemove(row) {
				var btn = row.querySelector('.tg-rep-remove');
				if (btn) {
					btn.addEventListener('click', function () {
						row.parentNode.removeChild(row);
					});
				}
			}

			rep.querySelectorAll('.tg-rep-remove').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var row = btn.closest('.tg-rep-row');
					if (row) {
						row.parentNode.removeChild(row);
					}
				});
			});

			var postbox = rep.closest('.postbox');
			var postboxHeader = postbox ? postbox.querySelector('.postbox-header') : null;

			if (postbox && postboxHeader && addBtn && !postboxHeader.querySelector('.tg-rep-add-header')) {
				postbox.classList.add('tg-sticky-repeater-box');

				var headerAddBtn = document.createElement('button');
				headerAddBtn.type = 'button';
				headerAddBtn.className = 'button button-primary tg-rep-add-header';
				headerAddBtn.textContent = addBtn.textContent;

				headerAddBtn.addEventListener('click', function (e) {
					e.preventDefault();
					e.stopPropagation();
					addBtn.click();
				});

				var handleActions = postboxHeader.querySelector('.handle-actions');

				if (handleActions) {
					postboxHeader.insertBefore(headerAddBtn, handleActions);
				} else {
					postboxHeader.appendChild(headerAddBtn);
				}
			}
		});
	}

	function initMediaFields() {
		document.querySelectorAll('[data-tg-media-field]').forEach(function (field) {
			var selectButton = field.querySelector('[data-tg-media-select]');
			var removeButton = field.querySelector('[data-tg-media-remove]');
			var idInput = field.querySelector('[data-tg-media-id]');
			var preview = field.querySelector('[data-tg-media-preview]');
			var empty = field.querySelector('[data-tg-media-empty]');
			var frame;

			if (!selectButton || !idInput || !preview || !window.wp || !wp.media) {
				return;
			}

			selectButton.addEventListener('click', function () {
				if (frame) {
					frame.open();
					return;
				}

				frame = wp.media({
					title: field.getAttribute('data-frame-title') || 'Select image',
					button: {
						text: field.getAttribute('data-button-label') || 'Use image'
					},
					library: { type: 'image' },
					multiple: false
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					var source = attachment.url || '';
					if (attachment.sizes) {
						source = (attachment.sizes.medium_large || attachment.sizes.large || attachment.sizes.medium || {}).url || source;
					}
					idInput.value = String(attachment.id || 0);
					preview.querySelectorAll('img').forEach(function (image) {
						image.remove();
					});
					var image = document.createElement('img');
					image.src = source;
					image.alt = '';
					preview.insertBefore(image, preview.firstChild);
					preview.classList.remove('is-empty');
					if (empty) {
						empty.hidden = true;
					}
					if (removeButton) {
						removeButton.hidden = false;
					}
				});

				frame.open();
			});

			if (removeButton) {
				removeButton.addEventListener('click', function () {
					idInput.value = '0';
					preview.querySelectorAll('img').forEach(function (image) {
						image.remove();
					});
					preview.classList.add('is-empty');
					if (empty) {
						empty.hidden = false;
					}
					removeButton.hidden = true;
				});
			}
		});
	}

	function initBulkSelect() {
		var checkAll = document.getElementById('tg-check-all');
		var select = document.getElementById('tg-bulk-select');
		var applyBtn = document.querySelector('#tg-bookings-bulk button.action');
		var hiddenAction = document.getElementById('tg-bulk-action');

		if (checkAll) {
			checkAll.addEventListener('change', function () {
				document.querySelectorAll('.tg-row-id').forEach(function (cb) {
					cb.checked = checkAll.checked;
				});
			});
		}
		if (select && applyBtn) {
			applyBtn.addEventListener('click', function () {
				if (hiddenAction) {
					hiddenAction.value = select.value || '';
				}
				if (!select.value) {
					alert('Choose a bulk action first.');
					return false;
				}
				var anyChecked = document.querySelector('.tg-row-id:checked');
				if (!anyChecked) {
					alert('Select at least one booking.');
					return false;
				}
				if (select.value === 'cancel' && !confirm('Cancel all selected bookings?')) {
					return false;
				}
			});
		}
	}

	function initTourGallery() {
		document.querySelectorAll('[data-tg-tour-gallery]').forEach(function (gallery) {
			var addButton = gallery.querySelector('[data-tg-gallery-add]');
			var clearButton = gallery.querySelector('[data-tg-gallery-clear]');
			var list = gallery.querySelector('[data-tg-gallery-list]');
			var frame;

			if (!addButton || !list || !window.wp || !wp.media) {
				return;
			}

			function containsImage(id) {
				return !!list.querySelector(
					'[data-attachment-id="' + String(id) + '"]'
				);
			}

			function updateClearButton() {
				if (clearButton) {
					clearButton.hidden = !list.querySelector(
						'.tg-gallery-admin-item'
					);
				}
			}

			function appendImage(attachment) {
				if (!attachment.id || containsImage(attachment.id)) {
					return;
				}

				var imageUrl = attachment.url || '';

				if (attachment.sizes) {
					imageUrl = (
						attachment.sizes.thumbnail ||
						attachment.sizes.medium ||
						attachment.sizes.full ||
						{}
					).url || imageUrl;
				}

				var item = document.createElement('div');
				item.className = 'tg-gallery-admin-item';
				item.setAttribute(
					'data-attachment-id',
					String(attachment.id)
				);

				var image = document.createElement('img');
				image.src = imageUrl;
				image.alt = '';

				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'tg_gallery_ids[]';
				input.value = String(attachment.id);

				var removeButton = document.createElement('button');
				removeButton.type = 'button';
				removeButton.className =
					'button-link-delete tg-gallery-remove';
				removeButton.textContent = 'Remove';

				item.appendChild(image);
				item.appendChild(input);
				item.appendChild(removeButton);
				list.appendChild(item);

				updateClearButton();
			}

			addButton.addEventListener('click', function (event) {
				event.preventDefault();

				if (!frame) {
					frame = wp.media({
						title: 'Select Tour Gallery Images',
						button: {
							text: 'Add to Tour Gallery'
						},
						library: {
							type: 'image'
						},
						multiple: true
					});

					frame.on('select', function () {
						frame
							.state()
							.get('selection')
							.each(function (attachment) {
								appendImage(attachment.toJSON());
							});
					});
				}

				frame.open();
			});

			list.addEventListener('click', function (event) {
				var removeButton = event.target.closest(
					'.tg-gallery-remove'
				);

				if (!removeButton) {
					return;
				}

				event.preventDefault();

				var item = removeButton.closest(
					'.tg-gallery-admin-item'
				);

				if (item) {
					item.remove();
				}

				updateClearButton();
			});

			if (clearButton) {
				clearButton.addEventListener('click', function (event) {
					event.preventDefault();

					list.querySelectorAll(
						'.tg-gallery-admin-item'
					).forEach(function (item) {
						item.remove();
					});

					updateClearButton();
				});
			}
		});
	}

	function init() {
		initRepeaters();
		initMediaFields();
		initTourGallery();
		initBulkSelect();
	}


	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
