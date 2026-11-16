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
});
