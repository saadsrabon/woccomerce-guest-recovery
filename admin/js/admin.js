(function ($) {
	'use strict';

	/**
	 * Prepare a WordPress admin table for DataTables (fix TN/18 column mismatch).
	 *
	 * @param {HTMLTableElement} table Table element.
	 * @return {boolean} Whether the table has data rows to initialize.
	 */
	function prepareTableForDataTables(table) {
		var $table = $(table);

		$table.find('tfoot').remove();

		var colCount = $table.find('thead tr').first().children('th, td').length;
		if (!colCount) {
			return false;
		}

		$table.find('tbody tr').each(function () {
			var $row = $(this);
			var $cells = $row.children('th, td');

			if ($cells.length === 1 && $cells.first().attr('colspan')) {
				$row.remove();
				return;
			}

			if ($cells.length !== colCount) {
				$row.remove();
			}
		});

		return $table.find('tbody tr').length > 0;
	}

	/**
	 * Initialize DataTables on GCRM tables.
	 */
	function initGcrmDataTables() {
		if (!$.fn.DataTable) {
			return;
		}

		$('table.gcrm-datatable').each(function () {
			var table = this;

			if ($.fn.DataTable.isDataTable(table)) {
				return;
			}

			if (!prepareTableForDataTables(table)) {
				return;
			}

			var hasWpPagination = $(table).closest('.gcrm-wrap, .wrap').find('.tablenav').length > 0;

			$(table).DataTable({
				pageLength: 20,
				order: [],
				paging: !hasWpPagination,
				searching: true,
				info: !hasWpPagination
			});
		});
	}

	$(document).ready(function () {
		initGcrmDataTables();

		var chartEl = document.getElementById('gcrm-revenue-chart');
		var chartDataEl = document.getElementById('gcrm-chart-data');
		if (chartEl && chartDataEl && typeof Chart !== 'undefined') {
			var data = JSON.parse(chartDataEl.textContent);
			new Chart(chartEl, {
				type: 'line',
				data: {
					labels: data.labels,
					datasets: [{
						label: 'Revenue',
						data: data.values,
						borderColor: '#2271b1',
						tension: 0.3
					}]
				},
				options: { responsive: true }
			});
		}

		$('#gcrm-preview-segment').on('click', function (e) {
			e.preventDefault();
			var rules = {
				logic: $('select[name="logic"]').val(),
				conditions: [{
					field: $('select[name="field"]').val(),
					operator: $('select[name="operator"]').val(),
					value: $('input[name="value"]').val()
				}]
			};
			$.ajax({
				url: gcrmAdmin.restUrl + 'segments/preview',
				method: 'POST',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', gcrmAdmin.nonce);
				},
				contentType: 'application/json',
				data: JSON.stringify(rules),
				success: function (res) {
					$('#gcrm-segment-count').text(res.count + ' customers match');
				}
			});
		});

		$('.gcrm-test-email').on('click', function (e) {
			e.preventDefault();
			var subject = $('input[name="email_subject"]').val();
			var body = typeof tinyMCE !== 'undefined' && tinyMCE.get('gcrm_email_body')
				? tinyMCE.get('gcrm_email_body').getContent()
				: $('textarea[name="email_body"]').val();
			$.post(ajaxurl, {
				action: 'gcrm_send_test_email',
				nonce: gcrmAdmin.nonce,
				subject: subject,
				body: body
			}).done(function () {
				alert('Test email sent to your admin address.');
			});
		});
	});
})(jQuery);
