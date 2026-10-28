(function ($) {
	'use strict';
	var token = window.dewawiFileManagerToken;
	$.ajaxPrefilter(function (options, originalOptions, xhr) {
		var url = document.createElement('a');
		url.href = options.url;
		if (url.protocol === location.protocol && url.host === location.host && /\/(dialog|upload|execute|ajax_calls|force_download)\.php$/.test(url.pathname)) {
			xhr.setRequestHeader('X-CSRF-Token', token);
		}
	});
	$(function () {
		$('form[method="post"], form[method="POST"]').each(function () {
			$('<input>', { type: 'hidden', name: 'csrf_token', value: token }).appendTo(this);
		});
		$('#fileupload').on('fileuploadsubmit', function (event, data) {
			if (Array.isArray(data.formData)) data.formData.push({ name: 'csrf_token', value: token });
			else data.formData = $.extend({}, data.formData, { csrf_token: token });
		});
	});
})(jQuery);
