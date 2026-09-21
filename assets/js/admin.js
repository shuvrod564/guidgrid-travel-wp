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
					addBtn.parentNode.insertBefore(row, addBtn.parentNode);
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

	function init() {
		initRepeaters();
		initBulkSelect();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
