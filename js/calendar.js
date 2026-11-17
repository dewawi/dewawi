/* Calendar dates are saved together so validation never sees partial time ranges. */
jQuery(function ($) {
	function timeValues($form) {
		return {start: $form.find('[name=start]').val(), end: $form.find('[name=end]').val(), startfold: $form.find('[name=startfold]').val(), endfold: $form.find('[name=endfold]').val(), timezone: $form.find('[name=timezone]').val(), allday: $form.find('[data-calendar-allday]').prop('checked') ? '1' : '0'};
	}
	function resetDuration($form) {
		$form.data('calendar-time-generation', ($form.data('calendar-time-generation') || 0) + 1);
		var request = $form.data('calendar-time-request'); if (request) request.abort();
		$form.data('calendar-time-pending', false).data('calendar-time-before', timeValues($form));
		$form.find('button[type=submit]').prop('disabled', false);
		$form.find('[data-calendar-time-result]').text('');
	}
	$('[data-calendar-form]').each(function () { resetDuration($(this)); });
	$(document).on('input', '[data-calendar-form] [name=end]', function () { resetDuration($(this).closest('form')); });
	$(document).on('change', '[data-calendar-form] [name=end], [data-calendar-form] [name=endfold], [data-calendar-form] [name=timezone]', function () { resetDuration($(this).closest('form')); });
	$(document).on('change', '[data-calendar-form] [name=start], [data-calendar-form] [name=startfold]', function () {
		var $form = $(this).closest('form'), before = $form.data('calendar-time-before'), current = timeValues($form);
		if (!before || !current.start || (before.start === current.start && before.startfold === current.startfold)) return;
		var generation = ($form.data('calendar-time-generation') || 0) + 1;
		$form.data('calendar-time-generation', generation);
		var previous = $form.data('calendar-time-request'); if (previous) previous.abort();
		$form.data('calendar-time-pending', true).find('button[type=submit]').prop('disabled', true);
		$form.find('[data-calendar-time-result]').text('');
		var data = $.extend({}, before, {newstart: current.start, newstartfold: current.startfold, csrf_token: $form.find('[name=csrf_token]').val()});
		$form.data('calendar-time-request', $.ajax({url: $form.attr('data-endtime-url'), type: 'POST', data: data, dataType: 'json', timeout: 15000})
			.done(function (result) {
				if ($form.data('calendar-time-generation') !== generation) return;
				$form.find('[name=end]').val(result.end); $form.find('[name=endfold]').val(result.endfold);
				$form.data('calendar-time-before', timeValues($form));
			})
			.fail(function (xhr, status) {
				if (status !== 'abort' && $form.data('calendar-time-generation') === generation) $form.find('[data-calendar-time-result]').text(xhr.responseJSON && xhr.responseJSON.message || Dewawi.message('MESSAGES_REQUEST_FAILED'));
			})
			.always(function () {
				if ($form.data('calendar-time-generation') === generation) { $form.data('calendar-time-pending', false); $form.find('button[type=submit]').prop('disabled', false); }
			}));
	});
	function updateInvitation($form) {
		var $checkbox = $form.find('[data-calendar-send-on-add]'); if (!$checkbox.length) return;
		var valid;
		if ($form.is('[data-calendar-add-internal]')) valid = !!($form.data('user-email-availability') || {})[$form.find('[name=userid]').val()];
		else { var email = $form.find('[name=email]')[0]; valid = !!email.value && email.validity.valid; }
		$checkbox.prop('disabled', !valid).prop('checked', valid && $form.data('calendar-mail-choice') !== false);
	}
	$('[data-calendar-add-internal], [data-calendar-add-external]').each(function () {
		var $form = $(this), choice = $form.attr('data-mail-choice');
		if (choice === '0' || choice === '1') $form.data('calendar-mail-choice', choice === '1');
		updateInvitation($form);
	});
	$(document).on('change', '[data-calendar-send-on-add]', function () { $(this).closest('form').data('calendar-mail-choice', this.checked); });
	$(document).on('input change', '[data-calendar-add-external] [name=email], [data-calendar-add-internal] [name=userid]', function () { updateInvitation($(this).closest('form')); });
	$(document).on('input change', '[data-calendar-add-external] [name=email], [data-calendar-add-external] [name=name]', function () { $(this).closest('form').find('[name=contactemailid]').val('0'); });
	$(document).on('submit', '[data-calendar-add-internal], [data-calendar-add-external]', function (event) {
		var $form = $(this);
		if ($form.data('calendar-pending')) { event.preventDefault(); return; }
		$form.data('calendar-pending', true).find('button[type=submit]').prop('disabled', true);
	});
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
		resetDuration($form);
	});
	$(document).on('submit', '[data-calendar-delete]', function (event) {
		if (!window.confirm($(this).attr('data-calendar-delete'))) event.preventDefault();
	});
	$(document).on('submit', '[data-calendar-form]', function (event) {
		if ($(this).data('calendar-time-pending')) { event.preventDefault(); return; }
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
		updateInvitation($form);
	});
});
