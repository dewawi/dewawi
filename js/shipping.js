(function ($) {
	function refresh(form) {
		var model = $(form).find('[name="model"]').val();
		$(form).find('[data-shipping-model]').each(function () {
			var active = $(this).attr('data-shipping-model') === model;
			$(this).prop('hidden', !active).find(':input').prop('disabled', !active);
		});
	}
	$(function () { $('[data-shipping-form]').each(function () { refresh(this); }); });
	$(document).on('change', '[data-shipping-form] [name="model"]', function () { refresh(this.form); });
	$(document).on('click', '[data-shipping-add]', function () {
		var $body = $(this).closest('fieldset').find('[data-shipping-tiers]');
		if ($body.children().length >= 100) return;
		var next = 0;
		$body.find('input').each(function () { var match = this.name.match(/^tiers\[(\d+)\]/); if (match) next = Math.max(next, Number(match[1]) + 1); });
		var $row = $body.children().first().clone();
		$row.find('input').val('').each(function () { this.name = this.name.replace(/^tiers\[\d+\]/, 'tiers[' + next + ']'); });
		$body.append($row); $row.find('input').first().focus();
	});
	$(document).on('click', '[data-shipping-remove]', function () {
		var $row = $(this).closest('tr'), $body = $row.parent();
		if ($body.children().length === 1) $row.find('input').val('').first().focus();
		else { $row.remove(); $body.find('input').first().focus(); }
	});
	$(document).on('submit', '[data-shipping-form]', function (event) {
		var $form = $(this);
		if ($form.data('dw-submit-complete') || !prepareFormSubmit($form, $form.find(':submit'), event.originalEvent && event.originalEvent.submitter)) { event.preventDefault(); return; }
		$form.attr('aria-busy', 'true').find('[data-shipping-pending]').prop('hidden', false);
	});
	$(window).on('pageshow', function () { $('[data-shipping-form]').attr('aria-busy', 'false').find('[data-shipping-pending]').prop('hidden', true); });
})(jQuery);
