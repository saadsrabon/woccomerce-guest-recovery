(function ($) {
	'use strict';

	$(document).ready(function () {
		if ($.fn.DataTable && $('.wp-list-table').length) {
			$('.wp-list-table').DataTable({ pageLength: 20, order: [] });
		}

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
