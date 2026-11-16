/* Calendar dates are saved together so validation never sees partial time ranges. */
jQuery(function ($) {
	$(document).on('input change', '[data-calendar-form] input, [data-calendar-form] textarea, [data-calendar-form] select', function () {
		if (window.Dewawi) Dewawi.setDirty(true);
	});
	$(document).on('change', '[data-calendar-allday]', function () {
		var allDay = this.checked;
		$(this).closest('form').find('[data-calendar-time]').each(function () {
			var value = this.value;
			if (allDay) $(this).data('calendar-clock', value.slice(10) || 'T09:00');
			this.type = allDay ? 'date' : 'datetime-local';
			this.value = allDay ? value.slice(0, 10) : value.slice(0, 10) + ($(this).data('calendar-clock') || 'T09:00');
		});
		var $form = $(this).closest('form'), start = $form.find('[name=start]').val(), $end = $form.find('[name=end]');
		if (allDay && start && $end.val() <= start) {
			var parts = start.split('-'), next = new Date(+parts[0], +parts[1] - 1, +parts[2] + 1, 12);
			$end.val(next.getFullYear() + '-' + ('0' + (next.getMonth() + 1)).slice(-2) + '-' + ('0' + next.getDate()).slice(-2));
		}
	});
	$(document).on('submit', '[data-calendar-delete]', function (event) {
		if (!window.confirm($(this).attr('data-calendar-delete'))) event.preventDefault();
	});
	$(document).on('submit', '[data-calendar-form]', function () {
		if (window.Dewawi) Dewawi.setDirty(false);
	});
	$(document).on('submit', '[data-calendar-response], [data-calendar-mail]', function (event) {
		event.preventDefault();
		var $form = $(this), $button = $form.find('button[type=submit]'), $result = $form.find('[data-calendar-result]');
		if ($form.data('calendar-pending')) return;
		$form.data('calendar-pending', true); $button.prop('disabled', true); $result.text('');
		$.ajax({url: this.action, type: 'POST', data: $form.serialize(), dataType: 'json', timeout: $form.is('[data-calendar-mail]') ? 120000 : 15000})
			.done(function (data) {
				if (data.participants) $.each(data.participants, function (_, participant) {
					if (+participant.removed) return;
					var label = $form.find('select[name=status] option[value="' + participant.status + '"]').text();
					if (label) $('[data-participant-id="' + participant.id + '"] [data-participant-status]').text(label);
				});
				$result.text(data.message || (data.ok ? $form.find('button[type=submit]').text() + ' ✓' : Dewawi.message('MESSAGES_REQUEST_FAILED')));
			})
			.fail(function (xhr) { $result.text(xhr.responseJSON && xhr.responseJSON.message || Dewawi.message('MESSAGES_REQUEST_FAILED')); })
			.always(function () { $form.data('calendar-pending', false); $button.prop('disabled', false); });
	});
	$(document).on('submit', '[data-calendar-cancel]', function (event) {
		if (!window.confirm($(this).attr('data-calendar-cancel'))) event.preventDefault();
	});
	$(document).on('click', '[data-calendar-contact-button]', function () {
		var $area = $(this).closest('[data-calendar-contact-search]'), $select = $area.find('select'), $result = $area.find('[data-calendar-contact-result]');
		var $button = $(this); if ($button.prop('disabled')) return;
		$button.prop('disabled', true);
		$.ajax({url: $area.attr('data-url'), data: {keyword: $area.find('[data-calendar-contact-query]').val()}, dataType: 'json', cache: false})
			.done(function (data) {
				$select.find('option').not(':first').remove(); $result.text('');
				$.each(data.contacts || [], function (_, contact) { $('<option>').val(contact.id).text(contact.name + ' <' + contact.email + '>').data('contact', contact).appendTo($select); });
			})
			.fail(function (xhr) { $result.text(xhr.responseJSON && xhr.responseJSON.message || Dewawi.message('MESSAGES_REQUEST_FAILED')); })
			.always(function () { $button.prop('disabled', false); });
	});
	$(document).on('change', '[data-calendar-contact-search] select', function () {
		var contact = $(this).find(':selected').data('contact'), $form = $(this).closest('form');
		if (contact) { $form.find('[name=name]').val(contact.name); $form.find('[name=email]').val(contact.email); }
	});
});
