(function ($) {
	'use strict';

	function trackCart() {
		if (!window.gcrmTracker) {
			return;
		}
		var email = $('#billing_email').val() || '';
		var phone = $('#billing_phone').val() || '';
		var name = ($('#billing_first_name').val() || '') + ' ' + ($('#billing_last_name').val() || '');

		$.ajax({
			url: gcrmTracker.restUrl,
			method: 'POST',
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', gcrmTracker.nonce);
			},
			contentType: 'application/json',
			data: JSON.stringify({
				email: email.trim(),
				phone: phone.trim(),
				name: name.trim()
			})
		});
	}

	$(document.body).on('updated_cart_totals updated_checkout', trackCart);
	$('#billing_email, #billing_phone').on('change blur', trackCart);
	setInterval(trackCart, 60000);
})(jQuery);
